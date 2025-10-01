<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\CodingActivityProblem;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    private CourseProgressService $progressService;
    private ActivityService $activityService;

    public function __construct(CourseProgressService $progressService, ActivityService $activityService)
    {
        $this->progressService = $progressService;
        $this->activityService = $activityService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Course $course, CourseModule $module)
    {
        if ($module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found in this course.',
            ], 404);
        }

        $activities = $module->activities()
            ->with(['codingActivityProblem', 'quizQuestions'])
            ->orderBy('order')
            ->get();

        return ActivityResource::collection($activities)->additional([
            'success' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Course $course, CourseModule $module)
    {
        $validated = $request->validate([
            'title'                      => [
                'required',
                'string',
                'max:255',
                Rule::unique('activities')->where('course_module_id', $module->id),
            ],
            'description'                => 'nullable|string',
            'type'                       => 'required|in:code,quiz',
            'exp_reward'                 => 'nullable|integer|min:0',
            'order'                      => 'sometimes|integer|min:1',
            'is_required'                => 'sometimes|boolean',

            // Coding activity fields
            'problem_statement'          => 'required_if:type,code|nullable|string',
            'test_cases'                 => 'required_if:type,code|nullable|json',
            'starter_code'               => 'nullable|string',

            // Quiz activity fields
            'questions'                  => 'required_if:type,quiz|nullable|array|min:1',
            'questions.*.question'       => 'required_with:questions|string',
            'questions.*.type'           => 'required_with:questions|in:multiple_choice,single_choice,true_false,short_answer',
            'questions.*.options'        => 'nullable|array',
            'questions.*.correct_answer' => 'required_with:questions',
            'questions.*.explanation'    => 'nullable|string',
            'questions.*.points'         => 'nullable|integer|min:0',
            'questions.*.order'          => 'nullable|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Set default order if not provided
            if (! isset($validated['order'])) {
                $validated['order'] = ($module->activities()->max('order') ?? 0) + 1;
            } else {
                $this->reorderActivities($module, $validated['order']);
            }

            // Create the activity first
            $activityData = collect($validated)->only([
                'title', 'description', 'type', 'exp_reward', 'order', 'is_required',
            ])->toArray();

            $activity = $module->activities()->create($activityData);

            // Handle coding activity
            if ($validated['type'] === 'code') {
                $codingProblem = CodingActivityProblem::create([
                    'problem_statement' => $validated['problem_statement'],
                    'test_cases'        => $validated['test_cases'],
                    'starter_code'      => $validated['starter_code'] ?? null,
                ]);

                $activity->update(['coding_activity_problem_id' => $codingProblem->id]);
                $activity->load('codingActivityProblem');
            }

            // Handle quiz activity
            if ($validated['type'] === 'quiz') {
                foreach ($validated['questions'] as $index => $questionData) {
                    QuizQuestion::create([
                        'activity_id'    => $activity->id,
                        'question'       => $questionData['question'],
                        'type'           => $questionData['type'],
                        'options'        => $questionData['options'] ?? null,
                        'correct_answer' => $questionData['correct_answer'],
                        'explanation'    => $questionData['explanation'] ?? null,
                        'points'         => $questionData['points'] ?? 1,
                        'order'          => $questionData['order'] ?? $index + 1,
                    ]);
                }
                $activity->load('quizQuestions');
            }

            DB::commit();

            return (new ActivityResource($activity))->additional([
                'success' => true,
                'message' => 'Activity created successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create activity.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course, CourseModule $module, string $id)
    {
        if (! request()->user()->tokenCan('admin:*') && ! request()->user()->tokenCan('courses:view') && ! Auth::check()) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $activity = Activity::findOrFail($id);
        if ($activity->course_module_id !== $module->id || $module->course_id !== $course->id) {
            return response()->json(['success' => false, 'message' => 'Activity not found in this module.'], 404);
        }

        if ($activity->type === 'code') {
            $activity->load(['codingActivityProblem', 'course.programmingLanguage']);
        } elseif ($activity->type === 'quiz') {
            $activity->load('quizQuestions');
        }

        $activity->load(['activitySubmissions' => function ($query) {
            $query->where('user_id', Auth::id());
        }]);

        return (new ActivityResource($activity))->additional([
            'success'      => true,
            'prev_content' => $this->progressService->getPrevContentData($activity),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CourseModule $module, Activity $activity)
    {
        if ($activity->course_module_id !== $module->id) {
            return response()->json(['success' => false, 'message' => 'Activity not found in this module.'], 404);
        }

        $validated = $request->validate([
            'title'                      => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('activities')->where('course_module_id', $module->id)->ignore($activity->id),
            ],
            'description'                => 'nullable|string',
            'type'                       => 'sometimes|in:code,quiz',
            'exp_reward'                 => 'nullable|integer|min:0',
            'order'                      => 'sometimes|integer|min:1',
            'is_required'                => 'sometimes|boolean',

            // Coding activity fields
            'problem_statement'          => 'required_if:type,code|nullable|string',
            'test_cases'                 => 'required_if:type,code|nullable|json',
            'starter_code'               => 'nullable|string',

            // Quiz activity fields
            'questions'                  => 'required_if:type,quiz|nullable|array|min:1',
            'questions.*.id'             => 'nullable|exists:quiz_questions,id',
            'questions.*.question'       => 'required_with:questions|string',
            'questions.*.type'           => 'required_with:questions|in:multiple_choice,single_choice,true_false,short_answer',
            'questions.*.options'        => 'nullable|array',
            'questions.*.correct_answer' => 'required_with:questions',
            'questions.*.explanation'    => 'nullable|string',
            'questions.*.points'         => 'nullable|integer|min:0',
            'questions.*.order'          => 'nullable|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            if ($request->has('order') && $validated['order'] !== $activity->order) {
                $this->reorderActivities($module, $validated['order'], $activity->id);
            }

            // Update basic activity data
            $activityData = collect($validated)->only([
                'title', 'description', 'type', 'exp_reward', 'order', 'is_required',
            ])->filter()->toArray();

            $activity->update($activityData);

            // Handle coding activity updates
            if (isset($validated['type']) && $validated['type'] === 'code' || $activity->type === 'code') {
                if ($activity->codingActivityProblem) {
                    $activity->codingActivityProblem->update([
                        'problem_statement' => $validated['problem_statement'],
                        'test_cases'        => $validated['test_cases'],
                        'starter_code'      => $validated['starter_code'] ?? null,
                    ]);
                } else {
                    $codingProblem = CodingActivityProblem::create([
                        'problem_statement' => $validated['problem_statement'],
                        'test_cases'        => $validated['test_cases'],
                        'starter_code'      => $validated['starter_code'] ?? null,
                    ]);
                    $activity->update(['coding_activity_problem_id' => $codingProblem->id]);
                }
                $activity->load('codingActivityProblem');
            }

            // Handle quiz activity updates
            if (isset($validated['type']) && $validated['type'] === 'quiz' || $activity->type === 'quiz') {
                if (isset($validated['questions'])) {
                    $existingQuestionIds = $activity->quizQuestions->pluck('id')->toArray();
                    $updatedQuestionIds  = [];

                    foreach ($validated['questions'] as $index => $questionData) {
                        if (isset($questionData['id']) && in_array($questionData['id'], $existingQuestionIds)) {
                            $question = QuizQuestion::find($questionData['id']);
                            $question->update([
                                'question'       => $questionData['question'],
                                'type'           => $questionData['type'],
                                'options'        => $questionData['options'] ?? null,
                                'correct_answer' => $questionData['correct_answer'],
                                'explanation'    => $questionData['explanation'] ?? null,
                                'points'         => $questionData['points'] ?? 1,
                                'order'          => $questionData['order'] ?? $index + 1,
                            ]);
                            $updatedQuestionIds[] = $questionData['id'];
                        } else {
                            $newQuestion = QuizQuestion::create([
                                'activity_id'    => $activity->id,
                                'question'       => $questionData['question'],
                                'type'           => $questionData['type'],
                                'options'        => $questionData['options'] ?? null,
                                'correct_answer' => $questionData['correct_answer'],
                                'explanation'    => $questionData['explanation'] ?? null,
                                'points'         => $questionData['points'] ?? 1,
                                'order'          => $questionData['order'] ?? $index + 1,
                            ]);
                            $updatedQuestionIds[] = $newQuestion->id;
                        }
                    }

                    $questionsToDelete = array_diff($existingQuestionIds, $updatedQuestionIds);
                    QuizQuestion::whereIn('id', $questionsToDelete)->delete();
                }

                $activity->load('quizQuestions');
            }

            DB::commit();

            return (new ActivityResource($activity))->additional([
                'success' => true,
                'message' => 'Activity updated successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update activity.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseModule $module, Activity $activity)
    {
        if ($activity->course_module_id !== $module->id) {
            return response()->json(['success' => false, 'message' => 'Activity not found in this module.'], 404);
        }

        DB::beginTransaction();

        try {
            if ($activity->codingActivityProblem) {
                $activity->codingActivityProblem->delete();
            }

            if ($activity->quizQuestions) {
                $activity->quizQuestions()->delete();
            }

            $activity->delete();

            $this->reorderActivitiesAfterDeletion($module);

            DB::commit();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete activity.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder activities to accommodate a new or updated item.
     */
    private function reorderActivities(CourseModule $module, int $newOrder, ?int $excludeId = null): void
    {
        $query = $module->activities()->where('order', '>=', $newOrder);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->increment('order');
    }

    /**
     * Reorder activities after one has been deleted.
     */
    private function reorderActivitiesAfterDeletion(CourseModule $module): void
    {
        $activities = $module->activities()->orderBy('order')->get();

        foreach ($activities as $index => $activity) {
            $activity->update(['order' => $index + 1]);
        }
    }

    public function submitCode(Request $request, string $id)
    {
        $activity = Activity::findOrFail($id);
        if ($activity->type !== "code") {
            return response()->json([
                'success' => false,
                'message' => 'This activity does not support code submission',
            ], 400);
        }

        $validatedData = $request->validate([
            'language_id' => 'required|integer|exists:programming_languages,id',
            'user_code'   => 'required|string',
        ]);

        try {
            $result = $this->activityService->submitCode(
                $activity->id,
                $validatedData['language_id'],
                $validatedData['user_code'],
                $request->user()
            );

            return response()->json([
                'success'      => true,
                'data'         => $result,
                'next_content' => $this->progressService->getNextContentData($activity),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Code execution failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
