<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Services\ContentOrderingService;
use App\Services\CourseEnrollmentService;
use App\Services\CourseProgressService;
use App\Services\CourseReportService;
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
    private CourseEnrollmentService $enrollmentService;
    private CourseReportService $reportService;

    public function __construct(
        CourseProgressService $progressService,
        ContentOrderingService $contentOrderingService,
        CourseEnrollmentService $enrollmentService,
        CourseReportService $reportService
    ) {
        $this->progressService        = $progressService;
        $this->contentOrderingService = $contentOrderingService;
        $this->enrollmentService      = $enrollmentService;
        $this->reportService          = $reportService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->tokenCan('admin:*') && !$user->tokenCan('challenge:view')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }
        $request->validate([
            'search'        => 'sometimes|string|max:255',
            'category_id'   => 'sometimes|exists:course_categories,id',
            'difficulty_id' => 'sometimes|exists:difficulties,id',
            'is_published'  => 'sometimes|boolean',
            'per_page'      => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Course::query();

        $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public');

            if ($user) {
                $q->orWhereHas('enrollments', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
                $q->orWhere('created_by', $user->id);
            }
        });
        
        if ($user->role->name == 'teacher') {
            $query->where('created_by', $user->id);
        }

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

        $query->with(['difficulty', 'category', 'skillTags', 'programmingLanguage', 'creator'])
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
            'visibility'              => 'sometimes|in:public,private',
            'skill_tag_ids'           => 'sometimes|array',
            'skill_tag_ids.*'         => 'exists:skill_tags,id',
            'programming_language_id' => 'required|exists:programming_languages,id',
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['title']);
        $validated['created_by'] = Auth::id();
        
        // Auto-generate course code
        $validated['course_code'] = $this->enrollmentService->generateCourseCode();

        $course = Course::create($validated);

        if ($request->has('skill_tag_ids')) {
            $course->skillTags()->attach($request->input('skill_tag_ids'));
        }

        $course->load(['difficulty', 'category', 'skillTags', 'programmingLanguage', 'creator']);

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
        $course->load(['difficulty', 'category', 'skillTags', 'programmingLanguage', 'creator']);

        $additionalData = ['success' => true];

        if (Auth::check()) {
            $user = request()->user();

            if ($user->tokenCan('admin:*') || $user->tokenCan('courses:update')) {
                $course->load(['modules.lessons', 'modules.activities']);
            }else {
                if ($course->visibility === 'private' && $course->created_by !== $user->id && !$this->enrollmentService->isUserEnrolled($user, $course)) {
                    abort(403, 'Unauthorized. You do not have permission to view this private course.');
                }
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
     * Get user's enrolled courses with progress
     */
    public function myCoursesWithProgress(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'limit'    => 'sometimes|integer|min:1|max:100',
            'status'   => 'sometimes|in:all,completed,in_progress,not_started',
        ]);

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $query = Course::whereHas('enrollments', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        if ($request->filled('status')) {
            $status = $request->input('status');
            
            switch ($status) {
                case 'completed':
                    $query->whereHas('userProgress', function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->whereNotNull('completed_at');
                    });
                    break;
                    
                case 'in_progress':
                    $query->whereHas('userProgress', function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->whereNull('completed_at')
                            ->where('progress_percentage', '>', 0);
                    });
                    break;
                    
                case 'not_started':
                    $query->whereHas('userProgress', function ($q) use ($user) {
                        $q->where('user_id', $user->id)
                            ->where('progress_percentage', 0);
                    })->orWhereDoesntHave('userProgress');
                    break;
            }
        }

        $query->with([
            'difficulty',
            'category',
            'skillTags',
            'programmingLanguage',
            'userEnrollment',
            'currentUserProgress'
        ])->withCount(['modules', 'enrollments']);

        if ($request->filled('limit')) {
            $courses = $query->limit($request->input('limit'))->get();
            
            return CourseResource::collection($courses)->additional([
                'success' => true,
                'total' => $courses->count(),
            ]);
        }

        $perPage = $request->input('per_page', 15);
        $courses = $query->paginate($perPage);

        return CourseResource::collection($courses)->additional([
            'success' => true,
        ]);
    }

    /**
     * Enroll in course using course code
     */
    public function enrollWithCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:8',
        ]);

        $user = Auth::user();

        try {
            $enrollment = $this->enrollmentService->enrollByCourseCode($user, $request->code);

            return response()->json([
                'success' => true,
                'message' => 'Successfully enrolled in the course.',
                'data' => [
                    'enrollment_id' => $enrollment->id,
                    'course' => new CourseResource($enrollment->course),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Enroll students by course creator
     */
    public function enrollStudents(Request $request, Course $course)
    {
        $user = Auth::user();

        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:users,id',
        ]);

        try {
            $result = $this->enrollmentService->enrollStudentsByCourseCreator(
                $course,
                $request->student_ids,
                $user
            );

            return response()->json([
                'success' => true,
                'message' => "Enrolled {$result['total_enrolled']} student(s) successfully.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Remove student from course (by creator)
     */
    public function removeStudent(Request $request, Course $course)
    {
        $user = Auth::user();

        $request->validate([
            'student_id' => 'required|exists:users,id',
        ]);

        try {
            $this->enrollmentService->removeStudent($course, $request->student_id, $user);

            return response()->json([
                'success' => true,
                'message' => 'Student removed from course successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get enrolled students for a course
     */
    public function getEnrolledStudents(Course $course)
    {
        $user = Auth::user();

        try {
            $students = $this->enrollmentService->getEnrolledStudents($course, $user);

            return response()->json([
                'success' => true,
                'data' => $students,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Regenerate course code
     */
    public function regenerateCourseCode(Course $course)
    {
        $user = Auth::user();

        try {
            $newCode = $this->enrollmentService->regenerateCourseCode($course, $user);

            return response()->json([
                'success' => true,
                'message' => 'Course code regenerated successfully.',
                'data' => [
                    'course_code' => $newCode,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Check if user can enroll
     */
    public function checkEnrollment(Course $course)
    {
        $user = Auth::user();

        $result = $this->enrollmentService->canEnroll($user, $course);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Unenroll from course (self)
     */
    public function unenroll(Course $course)
    {
        $user = Auth::user();

        try {
            $this->enrollmentService->unenrollUser($user, $course);

            return response()->json([
                'success' => true,
                'message' => 'Successfully unenrolled from the course.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
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

    /**
     * Generate overall course report
     */
    public function report(Course $course)
    {
        $user = Auth::user();

        // Only course creator or admin can view overall report
        if ($course->created_by !== $user->id && !$user->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission to view this report.');
        }

        try {
            $report = $this->reportService->generateCourseReport($course);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate individual student report
     */
    public function studentReport(Course $course, \App\Models\User $student)
    {
        $user = Auth::user();

        // Course creator, admin, or the student themselves can view the report
        $canView = $course->created_by === $user->id || 
                   $user->tokenCan('admin:*') || 
                   $user->id === $student->id;

        if (!$canView) {
            abort(403, 'Unauthorized. You do not have permission to view this report.');
        }

        try {
            $report = $this->reportService->generateStudentReport($student, $course);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
