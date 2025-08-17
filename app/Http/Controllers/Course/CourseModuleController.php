<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseModuleResource;
use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Course $course)
    {
        $query = $course->modules()->orderBy('order');
        $query->with(['lessons' => function ($q) {
            $q->orderBy('order');
        }])->withCount(['lessons']);

        if (Auth::check()) {
            $userId = Auth::id();
            $query->with([
                'userProgress' => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                },
            ]);
        }
        $modules = $query->paginate(10);

        return CourseModuleResource::collection($modules)->additional([
            'success' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title'       => [
                'required',
                'string',
                'max:255',
                Rule::unique('course_modules')->where('course_id', $course->id),
            ],
            'description' => 'nullable|string',
            'order'       => 'sometimes|integer|min:1',
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['title'], $course);

        if (! isset($validated['order'])) {
            $validated['order'] = ($course->modules()->max('order') ?? 0) + 1;
        } else {
            $this->reorderModules($course, $validated['order']);
        }
        $module = $course->modules()->create($validated);
        $module->load(['lessons']);

        return (new CourseModuleResource($module))
            ->additional([
                'success' => true,
                'message' => 'Course Module created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course, CourseModule $module)
    {
        if ($module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found in this course.',
            ], 404);
        }

        $module->load([
            'course',
            'lessons' => function ($q) {
                $q->orderBy('order');
            },
        ]);

        if (Auth::check()) {
            $userId = Auth::id();
            $module->load([
                'userProgress'         => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                },
                'lessons.userProgress' => function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                },
            ]);
        }

        return (new CourseModuleResource($module))
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Course $course, CourseModule $module)
    {
        if ($module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found in this course.',
            ], 404);
        }

        $validated = $request->validate([
            'title'       => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('course_modules')
                    ->where('course_id', $course->id)
                    ->ignore($module->id),
            ],
            'description' => 'nullable|string',
            'order'       => 'sometimes|integer|min:1',
        ]);

        if ($request->has('title')) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $course, $module->id);
        }

        if ($request->has('order') && $validated['order'] !== $module->order) {
            $this->reorderModules($course, $validated['order'], $module->id);
        }

        $module->update($validated);
        $module->load(['lessons']);

        return (new CourseModuleResource($module))
            ->additional([
                'success' => true,
                'message' => 'Course Module updated successfully.',
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course, CourseModule $module)
    {
        if ($module->course_id !== $course->id) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found in this course.'
            ], 404);
        }

        if ($module->lessons()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete module that contains lessons. Please delete or move lessons first.'
            ], 422);
        }

        $module->delete();

        $this->reorderModulesAfterDeletion($course);
        return response()->json(null, 204);
    }

    /**
     * Reorder modules to accommodate new order
     */
    private function reorderModules(Course $course, int $newOrder, ?int $excludeId = null): void
    {
        $query = $course->modules()->where('order', '>=', $newOrder);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->increment('order');
    }

    /**
     * Reorder modules after deletion
     */
    private function reorderModulesAfterDeletion(Course $course): void
    {
        $modules = $course->modules()->orderBy('order')->get();
        
        foreach ($modules as $index => $module) {
            $module->update(['order' => $index + 1]);
        }
    }

    /**
     * Generate unique slug for module within course
     */
    private function generateUniqueSlug(string $title, Course $course, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug     = $baseSlug;
        $counter  = 1;

        $query = $course->modules()->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            $query = $course->modules()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
