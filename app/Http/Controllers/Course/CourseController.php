<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Services\ContentOrderingService;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    private CourseProgressService $progressService;
    private ContentOrderingService $contentOrderingService;

    public function __construct(
        CourseProgressService $progressService,
        ContentOrderingService $contentOrderingService
    ) {
        $this->progressService        = $progressService;
        $this->contentOrderingService = $contentOrderingService;
    }
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

        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%")
                    ->orWhere('short_description', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->filled('duration_min') && $request->filled('duration_max')) {
            $query->whereBetween('estimated_duration', [
                $request->input('duration_min'),
                $request->input('duration_max'),
            ]);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('difficulty_id')) {
            $query->where('difficulty_id', $request->input('difficulty_id'));
        }

        if ($request->filled('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        $query->with(['difficulty', 'category', 'skillTags', 'programmingLanguage'])
            ->withCount(['modules', 'enrollments']);

        if (Auth::check()) {
            $userId = Auth::id();
            $query->with(['userEnrollment', 'currentUserProgress']);
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
            'requirements'            => 'nullable|string',
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
        $course->load(['difficulty', 'category', 'skillTags', 'programmingLanguage']);

        $additionalData = ['success' => true];

        if (Auth::check()) {
            $user = request()->user();

            if ($user->tokenCan('admin:*')) {
                $course->load(['modules.lessons', 'modules.activities']);
            }

            if ($user->tokenCan('courses:view')) {
                $course->load(['userEnrollment', 'currentUserProgress'])->loadCount('enrollments');
                $statistics           = $this->progressService->getCourseStatistics($user, $course);
                $currentActiveContent = $this->progressService->getCurrentActiveContent($user, $course);

                $contentByModules = $this->contentOrderingService->getContentByModules($course);

                $additionalData = array_merge($additionalData, [
                    'statistics'             => $statistics,
                    'current_active_content' => $currentActiveContent,
                    'content_by_modules'     => $contentByModules,
                ]);
            }
        }

        return (new CourseResource($course))->additional($additionalData);
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
            'requirements'            => 'nullable|string',
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
    public function destroy(Course $course, Request $request)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        DB::beginTransaction();

        try {
            if ($course->thumbnail && Storage::disk('public')->exists($course->thumbnail)) {
                Storage::disk('public')->delete($course->thumbnail);
            }

            $course->load('modules.lessons');

            foreach ($course->modules as $module) {
                foreach ($module->lessons as $lesson) {
                    if (empty($lesson->content)) {
                        continue;
                    }

                    $pattern = '/!\[.*?\]\((.*?)\)/';
                    preg_match_all($pattern, $lesson->content, $matches);

                    if (! empty($matches[1])) {
                        foreach ($matches[1] as $imageUrl) {
                            if (Str::contains($imageUrl, '/storage/')) {
                                $filePath = Str::after($imageUrl, '/storage/');

                                if (Storage::disk('public')->exists($filePath)) {
                                    Storage::disk('public')->delete($filePath);
                                }
                            }
                        }
                    }
                }
            }

            $course->delete();

            DB::commit();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete course.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function completion(Course $course)
    {
        $user = request()->user();

        if (!$user->tokenCan('courses:view')) {
            abort(403, 'Unauthorized. You do not have permission to complete courses.');
        }

        if (!$this->progressService->isUserEnrolled($user, $course)) {
            return response()->json([
                'success' => false,
                'message' => 'You must be enrolled in this course to complete it.',
            ], 403);
        }

        $allContent = $this->progressService->getAllCourseContentOrdered($course);
        
        if (empty($allContent)) {
            return response()->json([
                'success' => false,
                'message' => 'This course has no content to complete.',
            ], 422);
        }

        $statistics = $this->progressService->getCourseStatistics($user, $course);
        
        if ($statistics['progress_percentage'] !== 100) {
            return response()->json([
                'success' => false,
                'message' => 'You must complete all course content first.',
                'statistics' => $statistics,
            ], 422);
        }

        $this->progressService->updateCourseProgress($user, $course);
        $this->progressService->markCourseCompleted($user, $course);

        $completionStats = $this->progressService->getCourseCompletionStats($user, $course);

        $course->load([
            'difficulty',
            'category',
            'skillTags',
            'programmingLanguage',
            'userEnrollment',
            'currentUserProgress'
        ]);

        return (new CourseResource($course))
            ->additional([
                'success' => true,
                'message' => 'Congratulations! You have successfully completed the course.',
                'completion_stats' => $completionStats,
            ]);
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
