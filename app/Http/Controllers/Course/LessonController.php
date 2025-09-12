<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Services\CourseProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    private CourseProgressService $progressService;

    public function __construct(CourseProgressService $progressService)
    {
        $this->progressService = $progressService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Course $course, CourseModule $module, Request $request)
    {
        if ($module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found in this course.',
            ], 404);
        }

        $query = $module->lessons()->orderBy('order');

        if (Auth::check()) {
            $userId = Auth::id();
            $query->with(['currentUserProgress']);
        }

        $lessons = $query->paginate(10);

        return LessonResource::collection($lessons)->additional([
            'success' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Course $course, CourseModule $module)
    {
        if ($module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found in this course.',
            ], 404);
        }

        $validated = $request->validate([
            'title'                     => [
                'required',
                'string',
                'max:255',
                Rule::unique('lessons')->where('course_id', $course->id),
            ],
            'content'                   => 'nullable|string',
            'exp_reward'                => 'nullable|integer|min:0',
            'estimated_duration'        => 'nullable|integer|min:0',
            'order'                     => 'sometimes|integer|min:1',
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['title'], $module);

        if (! isset($validated['order'])) {
            $validated['order'] = ($module->lessons()->max('order') ?? 0) + 1;
        } else {
            $this->reorderLessons($module, $validated['order']);
        }

        $lesson = $module->lessons()->create($validated);

        $lesson->load(['module']);

        return (new LessonResource($lesson))->additional([
            'success' => true,
            'message' => 'Lesson created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course, CourseModule $module, Lesson $lesson)
    {
        if (! request()->user()->tokenCan('admin:*') && ! request()->user()->tokenCan('courses:view') && ! Auth::check()) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        if ($lesson->course_module_id !== $module->id || $module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found in this module.',
            ], 404);
        }

        if (Auth::check()) {
            $lesson->load(['currentUserProgress']);
        }

        return (new LessonResource($lesson))->additional([
            'success' => true,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Course $course, CourseModule $module, Lesson $lesson)
    {
        if ($lesson->module_id !== $module->id || $module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found in this module.',
            ], 404);
        }

        $validated = $request->validate([
            'title'                     => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('lessons')
                    ->where('course_id', $lesson->course_id)
                    ->ignore($lesson->id),
            ],
            'content'                   => 'nullable|string',
            'exp_reward'                => 'nullable|integer|min:0',
            'estimated_duration'        => 'nullable|integer|min:0',
            'order'                     => 'sometimes|integer|min:1',
        ]);

        if ($request->has('title')) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $module, $lesson->id);
        }

        if ($request->has('order') && $validated['order'] !== $lesson->order) {
            $this->reorderLessons($module, $validated['order'], $lesson->id);
        }

        $lesson->update($validated);

        $lesson->load(['module']);

        return (new LessonResource($lesson))->additional([
            'success' => true,
            'message' => 'Lesson updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course, CourseModule $module, Lesson $lesson)
    {
        if ($lesson->module_id !== $module->id || $module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found in this module.',
            ], 404);
        }

        $lesson->delete();

        $this->reorderLessonsAfterDeletion($module);

        return response()->json(null, 204);
    }

    /**
     * Reorder lessons to accommodate new order
     */
    private function reorderLessons(CourseModule $module, int $newOrder, ?int $excludeId = null): void
    {
        $query = $module->lessons()->where('order', '>=', $newOrder);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->increment('order');
    }

    /**
     * Reorder lessons after deletion
     */
    private function reorderLessonsAfterDeletion(CourseModule $module): void
    {
        $lessons = $module->lessons()->orderBy('order')->get();

        foreach ($lessons as $index => $lesson) {
            $lesson->update(['order' => $index + 1]);
        }
    }

    /**
     * Generate unique slug for lesson within module
     */
    private function generateUniqueSlug(string $title, CourseModule $module, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug     = $baseSlug;
        $counter  = 1;

        $query = $module->lessons()->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            $query = $module->lessons()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    public function markCompleted(Lesson $lesson)
    {
        $this->progressService->markLessonCompleted(Auth::user(), $lesson);
        
        return response()->json([
            'success' => true,
            'message' => 'Lesson marked as completed!',
            'redirect_url' => $this->progressService->getNextContentUrl(Auth::user(), $lesson)
        ]);
    }
}
