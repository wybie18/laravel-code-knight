<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\CodingActivityProblem;
use App\Models\Course;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseWithContentController extends Controller
{
    /**
     * Store a newly created course with all nested content
     */
    public function storeWithContent(Request $request)
    {
        $validated = $request->validate([
            'title'                                                       => 'required|string|max:255|unique:courses,title',
            'description'                                                 => 'required|string',
            'short_description'                                           => 'required|string|max:500',
            'objectives'                                                  => 'required|string',
            'requirements'                                                => 'nullable|string',
            'difficulty_id'                                               => 'required|exists:difficulties,id',
            'category_id'                                                 => 'required|exists:course_categories,id',
            'programming_language_id'                                     => 'required|exists:programming_languages,id',
            'exp_reward'                                                  => 'nullable|integer|min:0',
            'estimated_duration'                                          => 'nullable|integer|min:0',
            'is_published'                                                => 'sometimes|boolean',
            'skill_tag_ids'                                               => 'sometimes|array',
            'skill_tag_ids.*'                                             => 'exists:skill_tags,id',
            'thumbnail'                                                   => 'sometimes|image|max:2048', // 2MB max

            // Modules validation
            'modules'                                                     => 'sometimes|array',
            'modules.*.title'                                             => 'required|string|max:255',
            'modules.*.description'                                       => 'nullable|string',
            'modules.*.order'                                             => 'required|integer|min:1',

            // Lessons validation
            'modules.*.lessons'                                           => 'sometimes|array',
            'modules.*.lessons.*.title'                                   => 'required|string|max:255',
            'modules.*.lessons.*.content'                                 => 'nullable|string',
            'modules.*.lessons.*.exp_reward'                              => 'nullable|integer|min:0',
            'modules.*.lessons.*.estimated_duration'                      => 'nullable|integer|min:0',
            'modules.*.lessons.*.order'                                   => 'required|integer|min:1',

            // Activities validation
            'modules.*.activities'                                        => 'sometimes|array',
            'modules.*.activities.*.title'                                => 'required|string|max:255',
            'modules.*.activities.*.description'                          => 'nullable|string',
            'modules.*.activities.*.type'                                 => 'required|in:content,code,quiz',
            'modules.*.activities.*.exp_reward'                           => 'nullable|integer|min:0',
            'modules.*.activities.*.order'                                => 'required|integer|min:1',
            'modules.*.activities.*.is_required'                          => 'sometimes|boolean',

            // Coding problem validation
            'modules.*.activities.*.problem.problem_statement'            => 'required_if:modules.*.activities.*.type,code|nullable|string',
            'modules.*.activities.*.problem.starter_code'                 => 'nullable|string',
            'modules.*.activities.*.problem.test_cases'                   => 'required_if:modules.*.activities.*.type,code|nullable|array',
            'modules.*.activities.*.problem.test_cases.*.input'           => 'nullable|string',
            'modules.*.activities.*.problem.test_cases.*.expected_output' => 'required|string',

            // Quiz questions validation
            'modules.*.activities.*.questions'                            => 'required_if:modules.*.activities.*.type,quiz|nullable|array',
            'modules.*.activities.*.questions.*.question'                 => 'required|string',
            'modules.*.activities.*.questions.*.type'                     => 'required|in:multiple_choice,fill_blank,boolean',
            'modules.*.activities.*.questions.*.options'                  => 'nullable|array',
            'modules.*.activities.*.questions.*.correct_answer'           => 'required|string',
            'modules.*.activities.*.questions.*.explanation'              => 'nullable|string',
            'modules.*.activities.*.questions.*.points'                   => 'nullable|integer|min:0',
            'modules.*.activities.*.questions.*.order'                    => 'required|integer|min:1',
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
                'title', 'description', 'short_description', 'objectives', 'requirements',
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
                            $module->lessons()->create([
                                'title'              => $lessonData['title'],
                                'content'            => $lessonData['content'] ?? null,
                                'exp_reward'         => $lessonData['exp_reward'] ?? 0,
                                'estimated_duration' => $lessonData['estimated_duration'] ?? 0,
                                'order'              => $lessonData['order'],
                                'slug'               => $this->generateTypeSlug($lessonData['title'], $module, 'lessons'),
                            ]);
                        }
                    }

                    if (isset($moduleData['activities'])) {
                        foreach ($moduleData['activities'] as $activityData) {
                            $activity = $module->activities()->create([
                                'title'       => $activityData['title'],
                                'description' => $activityData['description'] ?? null,
                                'type'        => $activityData['type'],
                                'exp_reward'  => $activityData['exp_reward'] ?? 0,
                                'order'       => $activityData['order'],
                                'is_required' => $activityData['is_required'] ?? true,
                            ]);

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
                                        'correct_answer' => json_encode($questionData['correct_answer']),
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

            DB::commit();

            $course->load([
                'difficulty', 'category', 'skillTags', 'programmingLanguage',
                'modules.lessons', 'modules.activities.codingActivityProblem',
                'modules.activities.quizQuestions',
            ]);

            return (new CourseResource($course))->additional([
                'success' => true,
                'message' => 'Course created successfully with all content.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

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

    /**
     * Update an existing course with all nested content
     */
    public function updateWithContent(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title'                                                       => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses')->ignore($course->id),
            ],
            'description'                                                 => 'required|string',
            'short_description'                                           => 'required|string|max:500',
            'objectives'                                                  => 'required|string',
            'requirements'                                                => 'nullable|string',
            'difficulty_id'                                               => 'required|exists:difficulties,id',
            'category_id'                                                 => 'required|exists:course_categories,id',
            'programming_language_id'                                     => 'required|exists:programming_languages,id',
            'exp_reward'                                                  => 'nullable|integer|min:0',
            'estimated_duration'                                          => 'nullable|integer|min:0',
            'is_published'                                                => 'sometimes|boolean',
            'skill_tag_ids'                                               => 'sometimes|array',
            'skill_tag_ids.*'                                             => 'exists:skill_tags,id',
            'thumbnail'                                                   => 'sometimes|image|max:2048',
            'remove_thumbnail'                                            => 'sometimes|boolean',

            // Modules validation
            'modules'                                                     => 'sometimes|array',
            'modules.*.id'                                                => 'sometimes|exists:course_modules,id',
            'modules.*.title'                                             => 'required|string|max:255',
            'modules.*.description'                                       => 'nullable|string',
            'modules.*.order'                                             => 'required|integer|min:1',

            // Lessons validation - now directly under modules
            'modules.*.lessons'                                           => 'sometimes|array',
            'modules.*.lessons.*.id'                                      => 'sometimes|exists:lessons,id',
            'modules.*.lessons.*.title'                                   => 'required|string|max:255',
            'modules.*.lessons.*.content'                                 => 'nullable|string',
            'modules.*.lessons.*.exp_reward'                              => 'nullable|integer|min:0',
            'modules.*.lessons.*.estimated_duration'                      => 'nullable|integer|min:0',
            'modules.*.lessons.*.order'                                   => 'required|integer|min:1',

            // Activities validation - now directly under modules
            'modules.*.activities'                                        => 'sometimes|array',
            'modules.*.activities.*.id'                                   => 'sometimes|exists:activities,id',
            'modules.*.activities.*.title'                                => 'required|string|max:255',
            'modules.*.activities.*.description'                          => 'nullable|string',
            'modules.*.activities.*.type'                                 => 'required|in:content,code,quiz',
            'modules.*.activities.*.exp_reward'                           => 'nullable|integer|min:0',
            'modules.*.activities.*.order'                                => 'required|integer|min:1',
            'modules.*.activities.*.is_required'                          => 'sometimes|boolean',

            // Coding problem validation
            'modules.*.activities.*.problem.problem_statement'            => 'required_if:modules.*.activities.*.type,code|nullable|string',
            'modules.*.activities.*.problem.starter_code'                 => 'nullable|string',
            'modules.*.activities.*.problem.test_cases'                   => 'required_if:modules.*.activities.*.type,code|nullable|array',
            'modules.*.activities.*.problem.test_cases.*.input'           => 'nullable|string',
            'modules.*.activities.*.problem.test_cases.*.expected_output' => 'required|string',

            // Quiz questions validation
            'modules.*.activities.*.questions'                            => 'required_if:modules.*.activities.*.type,quiz|nullable|array',
            'modules.*.activities.*.questions.*.id'                       => 'sometimes|exists:quiz_questions,id',
            'modules.*.activities.*.questions.*.question'                 => 'required|string',
            'modules.*.activities.*.questions.*.type'                     => 'required|in:multiple_choice,fill_blank,boolean',
            'modules.*.activities.*.questions.*.options'                  => 'nullable|array',
            'modules.*.activities.*.questions.*.correct_answer'           => 'required|string',
            'modules.*.activities.*.questions.*.explanation'              => 'nullable|string',
            'modules.*.activities.*.questions.*.points'                   => 'nullable|integer|min:0',
            'modules.*.activities.*.questions.*.order'                    => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            $oldThumbnailPath = $course->thumbnail;
            $thumbnailPath    = $oldThumbnailPath;

            // Handle thumbnail removal
            if ($request->boolean('remove_thumbnail')) {
                if ($oldThumbnailPath && Storage::disk('public')->exists($oldThumbnailPath)) {
                    Storage::disk('public')->delete($oldThumbnailPath);
                }
                $thumbnailPath = null;
            }

            // Handle new thumbnail upload
            if ($request->hasFile('thumbnail')) {
                if ($oldThumbnailPath && Storage::disk('public')->exists($oldThumbnailPath)) {
                    Storage::disk('public')->delete($oldThumbnailPath);
                }
                $thumbnailPath = $request->file('thumbnail')->store('course-thumbnails', 'public');
            }

            // Update the course
            $courseData = collect($validated)->only([
                'title', 'description', 'short_description', 'objectives', 'requirements',
                'difficulty_id', 'category_id', 'programming_language_id',
                'exp_reward', 'estimated_duration', 'is_published',
            ])->toArray();

            if ($course->title !== $validated['title']) {
                $courseData['slug'] = $this->generateTypeSlug($validated['title'], $course->id);
            }

            $courseData['thumbnail'] = $thumbnailPath;
            $course->update($courseData);

            // Sync skill tags
            if (isset($validated['skill_tag_ids'])) {
                $course->skillTags()->sync($validated['skill_tag_ids']);
            }

            // Track existing IDs
            $existingModuleIds   = [];
            $existingLessonIds   = [];
            $existingActivityIds = [];
            $existingQuestionIds = [];

            // Handle modules
            if (isset($validated['modules'])) {
                $modulesToCreate = [];

                // Update existing modules
                foreach ($validated['modules'] as $index => $moduleData) {
                    if (isset($moduleData['id'])) {
                        $module = $course->modules()->findOrFail($moduleData['id']);

                        // Temporarily set high order to avoid conflicts
                        $tempOrder = 9999 + $index;
                        $module->update(['order' => $tempOrder]);

                        $module->update([
                            'title'       => $moduleData['title'],
                            'description' => $moduleData['description'] ?? null,
                            'slug'        => $module->title !== $moduleData['title']
                            ? $this->generateTypeSlug($moduleData['title'], $course, 'modules')
                            : $module->slug,
                        ]);
                        $existingModuleIds[] = $module->id;
                    } else {
                        $modulesToCreate[] = $moduleData;
                    }
                }

                // Delete modules not in update
                if (! empty($existingModuleIds)) {
                    $deletedModules = $course->modules()->whereNotIn('id', $existingModuleIds)->get();

                    foreach ($deletedModules as $deletedModule) {
                        // Delete lessons and activities
                        foreach ($deletedModule->lessons as $lesson) {
                            $lesson->delete();
                        }
                        foreach ($deletedModule->activities as $activity) {
                            if ($activity->codingActivityProblem) {
                                $activity->codingActivityProblem->delete();
                            }
                            $activity->quizQuestions()->delete();
                            $activity->delete();
                        }
                        $deletedModule->delete();
                    }
                } else {
                    // Delete all modules
                    foreach ($course->modules as $module) {
                        foreach ($module->lessons as $lesson) {
                            $lesson->delete();
                        }
                        foreach ($module->activities as $activity) {
                            if ($activity->codingActivityProblem) {
                                $activity->codingActivityProblem->delete();
                            }
                            $activity->quizQuestions()->delete();
                            $activity->delete();
                        }
                        $module->delete();
                    }
                }

                // Set correct orders for existing modules
                foreach ($validated['modules'] as $moduleData) {
                    if (isset($moduleData['id'])) {
                        $module = $course->modules()->findOrFail($moduleData['id']);
                        $module->update(['order' => $moduleData['order']]);
                    }
                }

                // Create new modules
                foreach ($modulesToCreate as $moduleData) {
                    $module = $course->modules()->create([
                        'title'       => $moduleData['title'],
                        'description' => $moduleData['description'] ?? null,
                        'order'       => $moduleData['order'],
                        'slug'        => $this->generateTypeSlug($moduleData['title'], $course, 'modules'),
                    ]);
                    $existingModuleIds[] = $module->id;

                    // Mark for lesson/activity processing
                    foreach ($validated['modules'] as &$validatedModule) {
                        if (! isset($validatedModule['id']) &&
                            $validatedModule['title'] === $moduleData['title'] &&
                            $validatedModule['order'] === $moduleData['order']) {
                            $validatedModule['created_module'] = $module;
                            break;
                        }
                    }
                }

                // Handle lessons and activities for each module
                foreach ($validated['modules'] as $moduleData) {
                    // Get module instance
                    if (isset($moduleData['id'])) {
                        $module = $course->modules()->findOrFail($moduleData['id']);
                    } elseif (isset($moduleData['created_module'])) {
                        $module = $moduleData['created_module'];
                    } else {
                        continue;
                    }

                    $this->handleModuleLessons($module, $moduleData['lessons'] ?? [], $existingLessonIds);
                    $this->handleModuleActivities($module, $moduleData['activities'] ?? [], $existingActivityIds, $existingQuestionIds);
                }
            }

            DB::commit();

            $course->load([
                'difficulty', 'category', 'skillTags', 'programmingLanguage',
                'modules.lessons', 'modules.activities.codingActivityProblem',
                'modules.activities.quizQuestions',
            ]);

            return (new CourseResource($course))->additional([
                'success' => true,
                'message' => 'Course updated successfully with all content.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->hasFile('thumbnail') && $thumbnailPath && $thumbnailPath !== $oldThumbnailPath) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update course.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function handleModuleLessons($module, $lessonsData, &$existingLessonIds)
    {
        $existingLessonIdsForModule = [];
        $lessonsToCreate            = [];

        foreach ($lessonsData as $index => $lessonData) {
            if (isset($lessonData['id'])) {
                $lesson = $module->lessons()->findOrFail($lessonData['id']);

                $lesson->update([
                    'title'              => $lessonData['title'],
                    'content'            => $lessonData['content'] ?? null,
                    'exp_reward'         => $lessonData['exp_reward'] ?? 0,
                    'estimated_duration' => $lessonData['estimated_duration'] ?? 0,
                    'order'              => $lessonData['order'],
                    'slug'               => $lesson->title !== $lessonData['title']
                    ? $this->generateTypeSlug($lessonData['title'], $module, 'lessons')
                    : $lesson->slug,
                ]);
                $existingLessonIds[]          = $lesson->id;
                $existingLessonIdsForModule[] = $lesson->id;
            } else {
                $lessonsToCreate[] = $lessonData;
            }
        }

        if (! empty($existingLessonIdsForModule)) {
            $module->lessons()->whereNotIn('id', $existingLessonIdsForModule)->delete();
        } else {
            $module->lessons()->delete();
        }

        // Create new lessons
        foreach ($lessonsToCreate as $lessonData) {
            $lesson = $module->lessons()->create([
                'title'              => $lessonData['title'],
                'content'            => $lessonData['content'] ?? null,
                'exp_reward'         => $lessonData['exp_reward'] ?? 0,
                'estimated_duration' => $lessonData['estimated_duration'] ?? 0,
                'order'              => $lessonData['order'],
                'slug'               => $this->generateTypeSlug($lessonData['title'], $module, 'lessons'),
            ]);
            $existingLessonIds[] = $lesson->id;
        }
    }

    private function handleModuleActivities($module, $activitiesData, &$existingActivityIds, &$existingQuestionIds)
    {
        $existingActivityIdsForModule = [];
        $activitiesToCreate           = [];

        // Update existing activities with temporary orders
        foreach ($activitiesData as $index => $activityData) {
            if (isset($activityData['id'])) {
                $activity = $module->activities()->findOrFail($activityData['id']);

                $activity->update([
                    'title'       => $activityData['title'],
                    'description' => $activityData['description'] ?? null,
                    'type'        => $activityData['type'],
                    'exp_reward'  => $activityData['exp_reward'] ?? 0,
                    'is_required' => $activityData['is_required'] ?? true,
                    'order'       => $activityData['order'],
                ]);
                $existingActivityIds[]          = $activity->id;
                $existingActivityIdsForModule[] = $activity->id;
            } else {
                $activitiesToCreate[] = $activityData;
            }
        }

        // Delete activities not in update
        if (! empty($existingActivityIdsForModule)) {
            $deletedActivities = $module->activities()->whereNotIn('id', $existingActivityIdsForModule)->get();
            foreach ($deletedActivities as $activity) {
                if ($activity->codingActivityProblem) {
                    $activity->codingActivityProblem->delete();
                }
                $activity->quizQuestions()->delete();
                $activity->delete();
            }
        } else {
            foreach ($module->activities as $activity) {
                if ($activity->codingActivityProblem) {
                    $activity->codingActivityProblem->delete();
                }
                $activity->quizQuestions()->delete();
                $activity->delete();
            }
        }

        // Create new activities
        foreach ($activitiesToCreate as $activityData) {
            $activity = $module->activities()->create([
                'title'       => $activityData['title'],
                'description' => $activityData['description'] ?? null,
                'type'        => $activityData['type'],
                'exp_reward'  => $activityData['exp_reward'] ?? 0,
                'order'       => $activityData['order'],
                'is_required' => $activityData['is_required'] ?? true,
            ]);
            $existingActivityIds[]          = $activity->id;
            $existingActivityIdsForModule[] = $activity->id;

            // Add back to activities data for content processing
            foreach ($activitiesData as &$validatedActivity) {
                if (! isset($validatedActivity['id']) &&
                    $validatedActivity['title'] === $activityData['title'] &&
                    $validatedActivity['order'] === $activityData['order']) {
                    $validatedActivity['created_activity'] = $activity;
                    break;
                }
            }
        }

        foreach ($activitiesData as $activityData) {
            if (isset($activityData['id'])) {
                $activity = $module->activities()->findOrFail($activityData['id']);
            } elseif (isset($activityData['created_activity'])) {
                $activity = $activityData['created_activity'];
            } else {
                continue;
            }

            if ($activityData['type'] === 'code' && isset($activityData['problem'])) {
                $testCasesJson = json_encode($activityData['problem']['test_cases']);

                if ($activity->codingActivityProblem) {
                    $activity->codingActivityProblem->update([
                        'problem_statement' => $activityData['problem']['problem_statement'],
                        'starter_code'      => $activityData['problem']['starter_code'] ?? '',
                        'test_cases'        => $testCasesJson,
                    ]);
                } else {
                    $codingProblem = CodingActivityProblem::create([
                        'problem_statement' => $activityData['problem']['problem_statement'],
                        'starter_code'      => $activityData['problem']['starter_code'] ?? '',
                        'test_cases'        => $testCasesJson,
                    ]);
                    $activity->update(['coding_activity_problem_id' => $codingProblem->id]);
                }
            }

            if ($activityData['type'] === 'quiz' && isset($activityData['questions'])) {
                $existingQuestionIdsForActivity = [];

                foreach ($activityData['questions'] as $questionData) {
                    if (isset($questionData['id'])) {
                        $question = QuizQuestion::findOrFail($questionData['id']);
                        $question->update([
                            'question'       => $questionData['question'],
                            'type'           => $questionData['type'],
                            'options'        => json_encode($questionData['options'] ?? []),
                            'correct_answer' => json_encode($questionData['correct_answer']),
                            'explanation'    => $questionData['explanation'] ?? null,
                            'points'         => $questionData['points'] ?? 1,
                            'order'          => $questionData['order'],
                        ]);
                        $existingQuestionIds[]            = $question->id;
                        $existingQuestionIdsForActivity[] = $question->id;
                    } else {
                        $question = QuizQuestion::create([
                            'activity_id'    => $activity->id,
                            'question'       => $questionData['question'],
                            'type'           => $questionData['type'],
                            'options'        => json_encode($questionData['options'] ?? []),
                            'correct_answer' => json_encode($questionData['correct_answer']),
                            'explanation'    => $questionData['explanation'] ?? null,
                            'points'         => $questionData['points'] ?? 1,
                            'order'          => $questionData['order'],
                        ]);
                        $existingQuestionIds[]            = $question->id;
                        $existingQuestionIdsForActivity[] = $question->id;
                    }
                }

                if (! empty($existingQuestionIdsForActivity)) {
                    $activity->quizQuestions()->whereNotIn('id', $existingQuestionIdsForActivity)->delete();
                } else {
                    $activity->quizQuestions()->delete();
                }
            }
        }
    }

    private function generateTypeSlug(string $title, $parent = null, string $type = 'courses'): string
    {
        $baseSlug = Str::slug($title);
        $slug     = $baseSlug;
        $counter  = 1;

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

            if (! $query->exists()) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
