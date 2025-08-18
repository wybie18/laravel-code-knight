<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\CodingActivityProblem;
use App\Models\Course;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search'        => 'sometimes|string|max:255',
            'category_id'   => 'sometimes|exists:course_categories,id',
            'difficulty_id' => 'sometimes|exists:difficulties,id',
            'is_published'  => 'sometimes|boolean',
            'per_page'      => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Course::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhere('short_description', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('difficulty_id')) {
            $query->where('difficulty_id', $request->input('difficulty_id'));
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        $query->with(['difficulty', 'category', 'skillTags', 'programmingLanguage'])
            ->withCount(['modules', 'enrollments']);

        if (Auth::check()) {
            $userId = Auth::id();
            $query->with([
                'userEnrollment' => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                },
                'userProgress'   => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                },
            ]);
        }

        $courses = $query->paginate(15);

        return CourseResource::collection($courses)->additional([
            'success' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'                   => 'required|string|max:255|unique:courses,title',
            'description'             => 'required|string',
            'short_description'       => 'required|string|max:500',
            'objectives'              => 'required|string',
            'difficulty_id'           => 'required|exists:difficulties,id',
            'category_id'             => 'required|exists:course_categories,id',
            'exp_reward'              => 'nullable|integer|min:0',
            'estimated_duration'      => 'nullable|integer|min:0',
            'is_published'            => 'sometimes|boolean',
            'skill_tag_ids'           => 'sometimes|array',
            'skill_tag_ids.*'         => 'exists:skill_tags,id',
            'programming_language_id' => 'required|exists:programming_languages,id',
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['title']);

        $course = Course::create($validated);

        if ($request->has('skill_tag_ids')) {
            $course->skillTags()->attach($request->input('skill_tag_ids'));
        }

        $course->load(['difficulty', 'category', 'skillTags', 'programmingLanguage']);

        return (new CourseResource($course))
            ->additional([
                'success' => true,
                'message' => 'Course created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        $course->load(['difficulty', 'category', 'modules.lessons', 'skillTags', 'programmingLanguage']);

        if (Auth::check()) {
            $userId = Auth::id();
            $course->load([
                'userEnrollment' => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                },
                'userProgress'   => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                },
            ]);
        }

        return (new CourseResource($course))
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title'                   => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('courses')->ignore($course->id),
            ],
            'description'             => 'required|string',
            'short_description'       => 'required|string|max:500',
            'objectives'              => 'required|string',
            'difficulty_id'           => 'sometimes|exists:difficulties,id',
            'category_id'             => 'sometimes|exists:course_categories,id',
            'exp_reward'              => 'required|integer|min:0',
            'estimated_duration'      => 'required|integer|min:0',
            'is_published'            => 'sometimes|boolean',
            'skill_tag_ids'           => 'sometimes|array',
            'skill_tag_ids.*'         => 'exists:skill_tags,id',
            'programming_language_id' => 'required|exists:programming_languages,id',
        ]);

        if ($request->has('title')) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $course->id);
        }

        $course->update($validated);

        if ($request->has('skill_tag_ids')) {
            $course->skillTags()->sync($request->input('skill_tag_ids'));
        }

        $course->load(['difficulty', 'category', 'skillTags', 'programmingLanguage']);

        return (new CourseResource($course))
            ->additional([
                'success' => true,
                'message' => 'Course updated successfully.',
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        $course->delete();

        return response()->json(null, 204);
    }

    /**
     * Store a newly created course with all nested content
     */
    public function storeWithContent(Request $request)
    {
        $validated = $request->validate([
            'title'                                                                 => 'required|string|max:255|unique:courses,title',
            'description'                                                           => 'required|string',
            'short_description'                                                     => 'required|string|max:500',
            'objectives'                                                            => 'required|string',
            'difficulty_id'                                                         => 'required|exists:difficulties,id',
            'category_id'                                                           => 'required|exists:course_categories,id',
            'programming_language_id'                                               => 'required|exists:programming_languages,id',
            'exp_reward'                                                            => 'nullable|integer|min:0',
            'estimated_duration'                                                    => 'nullable|integer|min:0',
            'is_published'                                                          => 'sometimes|boolean',
            'skill_tag_ids'                                                         => 'sometimes|array',
            'skill_tag_ids.*'                                                       => 'exists:skill_tags,id',
            'thumbnail'                                                             => 'sometimes|image|max:2048', // 2MB max

            // Modules validation
            'modules'                                                               => 'sometimes|array',
            'modules.*.title'                                                       => 'required|string|max:255',
            'modules.*.description'                                                 => 'nullable|string',
            'modules.*.order'                                                       => 'required|integer|min:1',

            // Lessons validation
            'modules.*.lessons'                                                     => 'sometimes|array',
            'modules.*.lessons.*.title'                                             => 'required|string|max:255',
            'modules.*.lessons.*.content'                                           => 'nullable|string',
            'modules.*.lessons.*.exp_reward'                                        => 'nullable|integer|min:0',
            'modules.*.lessons.*.estimated_duration'                                => 'nullable|integer|min:0',
            'modules.*.lessons.*.order'                                             => 'required|integer|min:1',

            // Activities validation
            'modules.*.lessons.*.activities'                                        => 'sometimes|array',
            'modules.*.lessons.*.activities.*.title'                                => 'required|string|max:255',
            'modules.*.lessons.*.activities.*.description'                          => 'nullable|string',
            'modules.*.lessons.*.activities.*.type'                                 => 'required|in:code,quiz',
            'modules.*.lessons.*.activities.*.exp_reward'                           => 'nullable|integer|min:0',
            'modules.*.lessons.*.activities.*.order'                                => 'required|integer|min:1',
            'modules.*.lessons.*.activities.*.is_required'                          => 'sometimes|boolean',

            // Coding problem validation
            'modules.*.lessons.*.activities.*.problem.problem_statement'            => 'required_if:modules.*.lessons.*.activities.*.type,code|nullable|string',
            'modules.*.lessons.*.activities.*.problem.starter_code'                 => 'nullable|string',
            'modules.*.lessons.*.activities.*.problem.test_cases'                   => 'required_if:modules.*.lessons.*.activities.*.type,code|nullable|array',
            'modules.*.lessons.*.activities.*.problem.test_cases.*.input'           => 'required|string',
            'modules.*.lessons.*.activities.*.problem.test_cases.*.expected_output' => 'required|string',

            // Quiz questions validation
            'modules.*.lessons.*.activities.*.questions'                            => 'required_if:modules.*.lessons.*.activities.*.type,quiz|nullable|array',
            'modules.*.lessons.*.activities.*.questions.*.question'                 => 'required|string',
            'modules.*.lessons.*.activities.*.questions.*.type'                     => 'required|in:multiple_choice,single_choice,true_false,short_answer',
            'modules.*.lessons.*.activities.*.questions.*.options'                  => 'nullable|array',
            'modules.*.lessons.*.activities.*.questions.*.correct_answers'          => 'required|array',
            'modules.*.lessons.*.activities.*.questions.*.explanation'              => 'nullable|string',
            'modules.*.lessons.*.activities.*.questions.*.points'                   => 'nullable|integer|min:0',
            'modules.*.lessons.*.activities.*.questions.*.order'                    => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Handle thumbnail upload
            $thumbnailPath = null;
            if ($request->hasFile('thumbnail')) {
                $thumbnailPath = $request->file('thumbnail')->store('course-thumbnails', 'public');
            }

            // Create the course
            $courseData = collect($validated)->only([
                'title', 'description', 'short_description', 'objectives',
                'difficulty_id', 'category_id', 'programming_language_id',
                'exp_reward', 'estimated_duration', 'is_published',
            ])->toArray();

            $courseData['slug'] = $this->generateTypeSlug($validated['title']);
            if ($thumbnailPath) {
                $courseData['thumbnail'] = $thumbnailPath;
            }

            $course = Course::create($courseData);

            // Attach skill tags
            if (isset($validated['skill_tag_ids'])) {
                $course->skillTags()->attach($validated['skill_tag_ids']);
            }

            // Create modules, lessons, and activities
            if (isset($validated['modules'])) {
                foreach ($validated['modules'] as $moduleData) {
                    $module = $course->modules()->create([
                        'title'       => $moduleData['title'],
                        'description' => $moduleData['description'] ?? null,
                        'order'       => $moduleData['order'],
                        'slug'        => $this->generateTypeSlug($moduleData['title'], $course, 'modules'),
                    ]);

                    if (isset($moduleData['lessons'])) {
                        foreach ($moduleData['lessons'] as $lessonData) {
                            $lesson = $module->lessons()->create([
                                'title'              => $lessonData['title'],
                                'content'            => $lessonData['content'] ?? null,
                                'exp_reward'         => $lessonData['exp_reward'] ?? 0,
                                'estimated_duration' => $lessonData['estimated_duration'] ?? 0,
                                'order'              => $lessonData['order'],
                                'slug'               => $this->generateTypeSlug($lessonData['title'], $module, 'lessons'),
                            ]);

                            if (isset($lessonData['activities'])) {
                                foreach ($lessonData['activities'] as $activityData) {
                                    $activity = $lesson->activities()->create([
                                        'title'       => $activityData['title'],
                                        'description' => $activityData['description'] ?? null,
                                        'type'        => $activityData['type'],
                                        'exp_reward'  => $activityData['exp_reward'] ?? 0,
                                        'order'       => $activityData['order'],
                                        'is_required' => $activityData['is_required'] ?? true,
                                    ]);

                                    // Handle coding activities
                                    if ($activityData['type'] === 'code' && isset($activityData['problem'])) {
                                        $testCasesJson = json_encode($activityData['problem']['test_cases']);

                                        $codingProblem = CodingActivityProblem::create([
                                            'problem_statement' => $activityData['problem']['problem_statement'],
                                            'starter_code'      => $activityData['problem']['starter_code'] ?? '',
                                            'test_cases'        => $testCasesJson,
                                        ]);

                                        $activity->update(['coding_activity_problem_id' => $codingProblem->id]);
                                    }

                                    // Handle quiz activities
                                    if ($activityData['type'] === 'quiz' && isset($activityData['questions'])) {
                                        foreach ($activityData['questions'] as $questionData) {
                                            QuizQuestion::create([
                                                'activity_id'    => $activity->id,
                                                'question'       => $questionData['question'],
                                                'type'           => $questionData['type'],
                                                'options'        => json_encode($questionData['options'] ?? []),
                                                'correct_answer' => json_encode($questionData['correct_answers']),
                                                'explanation'    => $questionData['explanation'] ?? null,
                                                'points'         => $questionData['points'] ?? 1,
                                                'order'          => $questionData['order'],
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            DB::commit();

            $course->load([
                'difficulty', 'category', 'skillTags', 'programmingLanguage',
                'modules.lessons.activities.codingActivityProblem',
                'modules.lessons.activities.quizQuestions',
            ]);

            return (new CourseResource($course))->additional([
                'success' => true,
                'message' => 'Course created successfully with all content.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded thumbnail if course creation failed
            if ($thumbnailPath) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create course.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function generateTypeSlug(string $title, $parent = null, string $type = 'courses'): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $query = null;
            
            switch ($type) {
                case 'courses':
                    $query = Course::where('slug', $slug);
                    break;
                case 'modules':
                    $query = $parent->modules()->where('slug', $slug);
                    break;
                case 'lessons':
                    $query = $parent->lessons()->where('slug', $slug);
                    break;
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate unique slug for course
     */
    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug     = $baseSlug;
        $counter  = 1;

        $query = Course::where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            $query = Course::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
