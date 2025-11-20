<?php
namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestResource;
use App\Models\Challenge;
use App\Models\Course;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\TestItem;
use App\Models\TestItemSubmission;
use App\Services\TestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestController extends Controller
{
    private TestService $testService;

    public function __construct(TestService $testService)
    {
        $this->testService = $testService;
    }

    /**
     * Display a listing of tests
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'course_slug' => 'sometimes|exists:courses,slug',
            'status'      => 'sometimes|in:draft,scheduled,active,closed,archived',
            'per_page'    => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Test::query();

        // Teacher can see their own tests
        if ($user->tokenCan('tests:view') && ! $user->tokenCan('admin:*')) {
            $query->where('teacher_id', $user->id);
        }

        // Student can see assigned tests
        if (! $user->tokenCan('tests:view') && ! $user->tokenCan('admin:*')) {
            $query->whereHas('students', function ($q) use ($user) {
                $q->where('student_id', $user->id);
            });
        }

        // Filter by course
        if ($request->filled('course_slug')) {
            $course = Course::where('slug', $request->course_slug)->first();
            $query->where('course_id', $course->id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $query->with(['teacher', 'course'])
            ->withCount(['items', 'students', 'attempts']);

        $perPage = $request->input('per_page', 15);
        $tests   = $query->paginate($perPage);

        return TestResource::collection($tests)->additional([
            'success' => true,
        ]);
    }

    /**
     * Store a newly created test
     */
    public function store(Request $request)
    {
        if (! Auth::user()->tokenCan('tests:create')) {
            abort(403, 'Unauthorized. You do not have permission to create tests.');
        }

        $validated = $request->validate([
            'course_id'                => 'nullable|exists:courses,id',
            'title'                    => 'required|string|max:255',
            'description'              => 'nullable|string',
            'instructions'             => 'nullable|string',
            'duration_minutes'         => 'nullable|integer|min:1',
            'start_time'               => 'nullable|date|after:now',
            'end_time'                 => 'nullable|date|after:start_time',
            'status'                   => 'sometimes|in:draft,scheduled,active,closed,archived',
            'shuffle_questions'        => 'sometimes|boolean',
            'show_results_immediately' => 'sometimes|boolean',
            'allow_review'             => 'sometimes|boolean',
            'max_attempts'             => 'sometimes|integer|min:1',
        ]);

        $validated['teacher_id']   = Auth::id();
        $validated['slug']         = $this->generateUniqueSlug($validated['title']);
        $validated['total_points'] = 0;

        // Auto-set status based on times
        if (! isset($validated['status'])) {
            if (isset($validated['start_time']) && now()->lt($validated['start_time'])) {
                $validated['status'] = 'scheduled';
            } else {
                $validated['status'] = 'draft';
            }
        }

        $test = Test::create($validated);
        $test->load(['teacher', 'course']);

        return (new TestResource($test))->additional([
            'success' => true,
            'message' => 'Test created successfully.',
        ]);
    }

    /**
     * Display the specified test
     */
    public function show(Test $test)
    {
        $user = Auth::user();

        // Check access permission
        if (! $this->testService->canUserAccessTest($user, $test)) {
            abort(403, 'Unauthorized. You do not have access to this test.');
        }

        $test->load(['teacher', 'course', 'items.itemable']);

        $additionalData = ['success' => true];

        // For teachers, include statistics
        if ($test->teacher_id === $user->id || $user->tokenCan('admin:*')) {
            $test->loadCount(['items', 'students', 'attempts']);
            $statistics                   = $this->testService->getTestStatisticsForTeacher($test);
            $additionalData['statistics'] = $statistics;
        }

        // For students, include their attempt data
        if (! $user->tokenCan('tests:view') && ! $user->tokenCan('admin:*')) {
            $studentStats                        = $this->testService->getStudentTestStats($user, $test);
            $additionalData['student_stats']     = $studentStats;
            $additionalData['can_start_attempt'] = $test->canStartAttempt($user);
        }

        return (new TestResource($test))->additional($additionalData);
    }

    /**
     * Update the specified test
     */
    public function update(Request $request, Test $test)
    {
        $user = Auth::user();

        // Only teacher who created the test or admin can update
        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission to update this test.');
        }

        $validated = $request->validate([
            'course_id'                => 'sometimes|nullable|exists:courses,id',
            'title'                    => 'sometimes|string|max:255',
            'description'              => 'nullable|string',
            'instructions'             => 'nullable|string',
            'duration_minutes'         => 'nullable|integer|min:1',
            'start_time'               => 'nullable|date',
            'end_time'                 => 'nullable|date|after:start_time',
            'status'                   => 'sometimes|in:draft,scheduled,active,closed,archived',
            'shuffle_questions'        => 'sometimes|boolean',
            'show_results_immediately' => 'sometimes|boolean',
            'allow_review'             => 'sometimes|boolean',
            'max_attempts'             => 'sometimes|integer|min:1',
        ]);

        if (isset($validated['title']) && $validated['title'] !== $test->title) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $test->id);
        }

        $test->update($validated);
        $test->load(['teacher', 'course', 'items.itemable']);

        return (new TestResource($test))->additional([
            'success' => true,
            'message' => 'Test updated successfully.',
        ]);
    }

    /**
     * Remove the specified test
     */
    public function destroy(Test $test)
    {
        $user = Auth::user();

        // Only teacher who created the test or admin can delete
        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission to delete this test.');
        }

        DB::beginTransaction();

        try {
            $test->delete();
            DB::commit();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete test.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add items to test
     */
    public function addItems(Request $request, Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'items'                 => 'required|array|min:1',
            'items.*.itemable_type' => 'required|string|in:App\Models\CodingChallenge,App\Models\CtfChallenge,App\Models\QuizQuestion,App\Models\EssayQuestion',
            'items.*.itemable_id'   => 'required|integer',
            'items.*.order'         => 'sometimes|integer|min:0',
            'items.*.points'        => 'required|integer|min:0',
        ]);

        try {
            $this->testService->addTestItems($test, $validated['items']);
            $test->load(['items.itemable']);

            return response()->json([
                'success' => true,
                'message' => 'Items added successfully.',
                'data'    => [
                    'items'        => $test->items,
                    'total_points' => $test->total_points,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add items.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create quiz question for test
     */
    public function createQuizQuestion(Request $request, Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'question'       => 'required|string',
            'type'           => 'required|in:multiple_choice,fill_blank,boolean',
            'options'        => 'nullable|array',
            'correct_answer' => 'required|string',
            'explanation'    => 'nullable|string',
            'points'         => 'required|integer|min:1',
            'order'          => 'sometimes|integer|min:0',
            'item_order'     => 'sometimes|integer|min:0',
        ]);

        try {
            $testItem = $this->testService->createQuizQuestion($test, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Quiz question created successfully.',
                'data'    => $testItem,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create quiz question.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create essay question for test
     */
    public function createEssayQuestion(Request $request, Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'question'       => 'required|string',
            'grading_rubric' => 'nullable|string',
            'min_words'      => 'nullable|integer|min:1',
            'max_words'      => 'nullable|integer|min:1',
            'points'         => 'required|integer|min:1',
            'order'          => 'sometimes|integer|min:0',
        ]);

        try {
            $testItem = $this->testService->createEssayQuestion($test, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Essay question created successfully.',
                'data'    => $testItem,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create essay question.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create coding challenge for test
     */
    public function createCodingChallenge(Request $request, Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'problem_statement'                    => 'required|string',
            'test_cases'                           => 'required|array',
            'test_cases.*.input'                   => 'nullable',
            'test_cases.*.expected_output'         => 'required',

            // Programming languages and pivot data
            'programming_languages'                => 'required|array|min:1',
            'programming_languages.*.language_id'  => 'required|exists:programming_languages,id',
            'programming_languages.*.starter_code' => 'nullable|string',
        ]);

        $validated['teacher_id'] = $user->id;

        try {
            $testItem = $this->testService->createCodingChallengeForTest($test, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Coding challenge created successfully.',
                'data'    => $testItem,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create coding challenge.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add existing challenge to test
     */
    public function addExistingChallenge(Request $request, Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'challenge_id' => 'required|exists:challenges,id',
            'points'       => 'required|integer|min:1',
            'order'        => 'sometimes|integer|min:0',
        ]);

        try {
            $challenge = Challenge::findOrFail($validated['challenge_id']);

            $testItem = $this->testService->addExistingChallengeToTest(
                $test,
                $challenge,
                $validated['points'],
                $validated['order'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Challenge added to test successfully.',
                'data'    => $testItem->load('itemable'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add challenge to test.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a test item
     */
    public function updateItem(Request $request, Test $test, TestItem $testItem)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        if ($testItem->test_id !== $test->id) {
            abort(404, 'Test item not found in this test.');
        }

        $itemType = $testItem->itemable_type;

        try {
            $updatedItem = null;

            // Handle QuizQuestion updates
            if ($itemType === 'App\\Models\\QuizQuestion') {
                $validated = $request->validate([
                    'question'       => 'sometimes|string',
                    'type'           => 'sometimes|in:multiple_choice,fill_blank,boolean',
                    'options'        => 'nullable|array',
                    'correct_answer' => 'sometimes|string',
                    'explanation'    => 'nullable|string',
                    'points'         => 'sometimes|integer|min:0',
                    'order'          => 'sometimes|integer|min:0',
                ]);
                $updatedItem = $this->testService->updateQuizQuestion($testItem, $validated);
            }
            // Handle EssayQuestion updates
            elseif ($itemType === 'App\\Models\\EssayQuestion') {
                $validated = $request->validate([
                    'question'   => 'sometimes|string',
                    'min_words'  => 'nullable|integer|min:1',
                    'max_words'  => 'nullable|integer|min:1',
                    'rubric'     => 'nullable|string',
                    'max_points' => 'sometimes|integer|min:1',
                    'points'     => 'sometimes|integer|min:0',
                    'order'      => 'sometimes|integer|min:0',
                ]);
                $updatedItem = $this->testService->updateEssayQuestion($testItem, $validated);
            }
            // Handle CodingChallenge updates
            elseif ($itemType === 'App\\Models\\CodingChallenge') {
                $validated = $request->validate([
                    'problem_statement'                    => 'required|string',
                    'test_cases'                           => 'required|array',
                    'test_cases.*.input'                   => 'nullable|string',
                    'test_cases.*.expected_output'         => 'required|string',

                    // Programming languages and pivot data
                    'programming_languages'                => 'required|array|min:1',
                    'programming_languages.*.language_id'  => 'required|exists:programming_languages,id',
                    'programming_languages.*.starter_code' => 'nullable|string',

                    'points'                               => 'sometimes|integer|min:0',
                    'order'                                => 'sometimes|integer|min:0',
                ]);
                $updatedItem = $this->testService->updateCodingChallenge($testItem, $validated);
            }
            // Fallback for basic updates (order, points only)
            else {
                $validated = $request->validate([
                    'order'  => 'sometimes|integer|min:0',
                    'points' => 'sometimes|integer|min:0',
                ]);
                $updatedItem = $this->testService->updateTestItem($testItem, $validated);
            }

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully.',
                'data'    => $updatedItem->load('itemable'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a test item
     */
    public function removeItem(Test $test, TestItem $testItem)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        if ($testItem->test_id !== $test->id) {
            abort(404, 'Test item not found in this test.');
        }

        try {
            $this->testService->removeTestItem($testItem);

            return response()->json([
                'success' => true,
                'message' => 'Item removed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign students to test
     */
    public function assignStudents(Request $request, Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'exists:users,id',
        ]);

        try {
            $this->testService->assignStudents($test, $validated['student_ids']);
            $test->loadCount('students');

            return response()->json([
                'success' => true,
                'message' => 'Students assigned successfully.',
                'data'    => [
                    'total_students' => $test->students_count,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign students.',
                'error'   => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove students from test
     */
    public function removeStudents(Request $request, Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'exists:users,id',
        ]);

        try {
            $this->testService->removeStudents($test, $validated['student_ids']);
            $test->loadCount('students');

            return response()->json([
                'success' => true,
                'message' => 'Students removed successfully.',
                'data'    => [
                    'total_students' => $test->students_count,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove students.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get test students with their attempts
     */
    public function getStudents(Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $students = $test->students()
            ->with(['testAttempts' => function ($query) use ($test) {
                $query->where('test_id', $test->id)
                    ->orderBy('attempt_number', 'desc');
            }])
            ->get()
            ->map(function ($student) use ($test) {
                $attempts  = $student->testAttempts;
                $bestScore = $attempts->where('status', 'graded')->max('total_score');

                return [
                    'id'              => $student->id,
                    'username'        => $student->username,
                    'first_name'      => $student->first_name,
                    'last_name'       => $student->last_name,
                    'email'           => $student->email,
                    'student_id'      => $student->student_id,
                    'total_attempts'  => $attempts->count(),
                    'best_score'      => $bestScore,
                    'best_percentage' => $bestScore ? round(($bestScore / $test->total_points) * 100, 2) : 0,
                    'latest_attempt'  => $attempts->first(),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $students,
        ]);
    }

    /**
     * Start a test attempt (Student)
     */
    public function startAttempt(Test $test)
    {
        $user = Auth::user();

        if (! $this->testService->canUserAccessTest($user, $test)) {
            abort(403, 'You do not have access to this test.');
        }

        if (! $test->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Test is not currently active.',
            ], 422);
        }

        try {
            $attempt = $this->testService->startAttempt($user, $test);
            $attempt->load(['test.items.itemable']);

            // Load programming languages for coding challenges
            $attempt->test->items->each(function ($item) {
                if ($item->itemable instanceof \App\Models\CodingChallenge) {
                    $item->itemable->load('programmingLanguages');
                    $item->itemable->programmingLanguages->each(function ($lang) {
                        $lang->starter_code = $lang->pivot->starter_code;
                        $lang->makeHidden('pivot');
                    });
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Test attempt started successfully.',
                'data'    => $attempt,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Submit answer for a test item (Student)
     */
    public function submitItemAnswer(Request $request, Test $test, TestAttempt $attempt, TestItem $testItem)
    {
        $user = Auth::user();

        if ($attempt->student_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }

        if ($attempt->test_id !== $test->id || $testItem->test_id !== $test->id) {
            abort(404, 'Invalid test, attempt, or item.');
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Test attempt is not in progress.',
            ], 422);
        }

        $validated = $request->validate([
            'answer'      => 'required',
        ]);

        try {
            $submission = $this->testService->submitItemAnswer(
                $attempt,
                $testItem,
                $validated['answer'],
            );

            return response()->json([
                'success' => true,
                'message' => 'Answer submitted successfully.',
                'data'    => $submission,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit answer.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit the entire test (Student)
     */
    public function submitTest(Test $test, TestAttempt $attempt)
    {
        $user = Auth::user();

        if ($attempt->student_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }

        if ($attempt->test_id !== $test->id) {
            abort(404, 'Invalid test or attempt.');
        }

        try {
            $result = $this->testService->submitTest($attempt);

            return response()->json([
                'success' => true,
                'message' => 'Test submitted successfully.',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get student's attempts for a test
     */
    public function myAttempts(Test $test)
    {
        $user = Auth::user();

        if (! $this->testService->canUserAccessTest($user, $test)) {
            abort(403, 'You do not have access to this test.');
        }

        $attempts = $test->attempts()
            ->where('student_id', $user->id)
            ->with(['submissions.testItem.itemable'])
            ->orderBy('attempt_number', 'desc')
            ->get();

        $stats = $this->testService->getStudentTestStats($user, $test);

        return response()->json([
            'success' => true,
            'data'    => [
                'attempts' => $attempts,
                'stats'    => $stats,
            ],
        ]);
    }

    /**
     * Get specific attempt details
     */
    public function getAttempt(Test $test, TestAttempt $attempt)
    {
        $user = Auth::user();

        // Students can only view their own attempts
        if ($attempt->student_id !== $user->id && $test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        if ($attempt->test_id !== $test->id) {
            abort(404, 'Attempt not found for this test.');
        }

        $attempt->load(['test.items.itemable', 'submissions.testItem.itemable', 'student']);

        $attempt->test->items->each(function ($item) {
            if ($item->itemable instanceof \App\Models\CodingChallenge) {
                $item->itemable->load('programmingLanguages');
                $item->itemable->programmingLanguages->each(function ($lang) {
                    $lang->starter_code = $lang->pivot->starter_code;
                    $lang->makeHidden('pivot');
                });
            }
        });

        $attempt->submissions->each(function ($submission) {
            if ($submission->testItem && $submission->testItem->itemable instanceof \App\Models\CodingChallenge) {
                $submission->testItem->itemable->load('programmingLanguages');
                $submission->testItem->itemable->programmingLanguages->each(function ($lang) {
                    $lang->starter_code = $lang->pivot->starter_code;
                    $lang->makeHidden('pivot');
                });
            }
        });

        // Don't show correct answers if review is not allowed
        if (! $test->allow_review && $attempt->student_id === $user->id) {
            $attempt->makeHidden(['submissions']);
        }

        return response()->json([
            'success' => true,
            'data'    => $attempt,
        ]);
    }

    /**
     * Grade a submission (Teacher)
     */
    public function gradeSubmission(Request $request, Test $test, TestAttempt $attempt, TestItemSubmission $submission)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'score'    => 'required|integer|min:0',
            'feedback' => 'nullable|string',
        ]);

        try {
            $gradedSubmission = $this->testService->gradeSubmission(
                $submission,
                $validated['score'],
                $validated['feedback'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Submission graded successfully.',
                'data'    => $gradedSubmission->load(['attempt', 'testItem.itemable']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get pending submissions for grading (Teacher)
     */
    public function getPendingSubmissions(Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $pendingSubmissions = $this->testService->getPendingSubmissions($test);

        return response()->json([
            'success' => true,
            'data'    => $pendingSubmissions,
        ]);
    }

    /**
     * Close test (Teacher)
     */
    public function closeTest(Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $test->status   = 'closed';
        $test->end_time = now();
        $test->save();

        // Mark all in-progress attempts as abandoned
        $test->attempts()
            ->where('status', 'in_progress')
            ->update(['status' => 'abandoned']);

        return response()->json([
            'success' => true,
            'message' => 'Test closed successfully.',
            'data'    => $test,
        ]);
    }

    /**
     * Get tests for a specific course
     */
    public function getTestsByCourse(Course $course, Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'status'   => 'sometimes|in:draft,scheduled,active,closed,archived',
            'open_only' => 'sometimes|boolean',
            'closed_only' => 'sometimes|boolean',
        ]);

        $query = Test::where('course_id', $course->id);

        // Teachers see their own tests
        if ($user->tokenCan('tests:view') && ! $user->tokenCan('admin:*')) {
            $query->where('teacher_id', $user->id);
        }

        // Students see tests they're assigned to
        if (! $user->tokenCan('tests:view') && ! $user->tokenCan('admin:*')) {
            $query->whereHas('students', function ($q) use ($user) {
                $q->where('test_students.student_id', $user->id);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter to only open tests (active or upcoming)
        if ($request->boolean('open_only')) {
            $query->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'scheduled')
                            ->where('start_time', '>', now());
                    });
            });
        }

        // Filter to only closed tests (closed or archived)
        if ($request->boolean('closed_only')) {
            $query->where(function ($q) {
                $q->where('status', 'closed')
                    ->orWhere('status', 'archived');
            });
        }

        $tests = $query->with(['teacher', 'course'])
            ->withCount(['items', 'students', 'attempts'])
            ->get();

        return TestResource::collection($tests)->additional([
            'success' => true,
        ]);
    }

    /**
     * Get all tests assigned to the authenticated student
     */
    public function myTests(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'status'   => 'sometimes|in:upcoming,active,completed,all',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Test::whereHas('students', function ($q) use ($user) {
            // qualify pivot column to avoid ambiguous column errors
            $q->where('test_students.student_id', $user->id);
        });

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->status;

            switch ($status) {
                case 'upcoming':
                    $query->where('start_time', '>', now())
                        ->where('status', 'scheduled');
                    break;
                case 'active':
                    $query->where('status', 'active')
                        ->where(function ($q) {
                            $q->whereNull('end_time')
                                ->orWhere('end_time', '>', now());
                        });
                    break;
                case 'completed':
                    $query->where(function ($q) {
                        $q->where('status', 'closed')
                            ->orWhere('status', 'archived')
                            ->orWhere('end_time', '<', now());
                    });
                    break;
            }
        }

        $query->with(['teacher', 'course'])
            ->withCount(['items', 'students', 'attempts']);

        $perPage = $request->input('per_page', 15);
        $tests   = $query->paginate($perPage);

        // Add student stats for each test
        $tests->getCollection()->transform(function ($test) use ($user) {
            $test->student_stats     = $this->testService->getStudentTestStats($user, $test);
            $test->can_start_attempt = $test->canStartAttempt($user);
            return $test;
        });

        return TestResource::collection($tests)->additional([
            'success' => true,
        ]);
    }

    /**
     * Generate unique slug for test
     */
    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug     = $baseSlug;
        $counter  = 1;

        $query = Test::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            $query = Test::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    /**
     * Get test results for all students (paginated)
     */
    public function getResults(Request $request, Test $test)
    {
        $user = Auth::user();

        if ($test->teacher_id !== $user->id && ! $user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized.');
        }

        $perPage = $request->input('per_page', 15);
        $search  = $request->input('search');

        $query = $test->students();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $students = $query->paginate($perPage);

        $students->load(['testAttempts' => function ($q) use ($test) {
            $q->where('test_id', $test->id)
                ->orderBy('total_score', 'desc');
        }]);

        $results = $students->through(function ($student) use ($test) {
            $attempts      = $student->testAttempts;
            $bestAttempt   = $attempts->first();
            $latestAttempt = $attempts->sortByDesc('created_at')->first();

            return [
                'student' => [
                    'id'         => $student->id,
                    'student_id' => $student->student_id,
                    'first_name' => $student->first_name,
                    'last_name'  => $student->last_name,
                    'email'      => $student->email,
                    'username'   => $student->username,
                ],
                'stats'   => [
                    'attempts_count'  => $attempts->count(),
                    'best_score'      => $bestAttempt ? $bestAttempt->total_score : null,
                    'max_score'       => $test->total_points,
                    'percentage'      => $bestAttempt ? ($test->total_points > 0 ? round(($bestAttempt->total_score / $test->total_points) * 100, 1) : 0) : null,
                    'status'          => $latestAttempt ? $latestAttempt->status : 'not_started',
                    'last_attempt_at' => $latestAttempt ? $latestAttempt->created_at : null,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $results,
        ]);
    }
}
