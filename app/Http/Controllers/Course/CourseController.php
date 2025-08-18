<?php
namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'title'              => 'required|string|max:255|unique:courses,title',
            'description'        => 'required|string',
            'short_description'  => 'required|string|max:500',
            'objectives'         => 'required|string',
            'difficulty_id'      => 'required|exists:difficulties,id',
            'category_id'        => 'required|exists:course_categories,id',
            'exp_reward'         => 'nullable|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:0',
            'is_published'       => 'sometimes|boolean',
            'skill_tag_ids'      => 'sometimes|array',
            'skill_tag_ids.*'    => 'exists:skill_tags,id',
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
            'title'              => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('courses')->ignore($course->id),
            ],
            'description'        => 'required|string',
            'short_description'  => 'required|string|max:500',
            'objectives'         => 'required|string',
            'difficulty_id'      => 'sometimes|exists:difficulties,id',
            'category_id'        => 'sometimes|exists:course_categories,id',
            'exp_reward'         => 'required|integer|min:0',
            'estimated_duration' => 'required|integer|min:0',
            'is_published'       => 'sometimes|boolean',
            'skill_tag_ids'      => 'sometimes|array',
            'skill_tag_ids.*'    => 'exists:skill_tags,id',
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
