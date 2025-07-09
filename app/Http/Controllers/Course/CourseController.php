<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Course::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        $courses = $query->with(['difficulty', 'category', 'skillTags'])->paginate(15);

        return response()->json([
            "success" => true,
            "data"    => $courses,
        ], 200);
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
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        $count = Course::where('slug', 'like', $validated['slug'] . '%')->count();
        if ($count > 0) {
            $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
        }

        $course = Course::create($validated);

        return response()->json([
            "success" => true,
            "message" => "Course created successfully!",
            "data"    => $course,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        $course->load(['difficulty', 'category', 'lessons', 'flashcards', 'skillTags']);
        
        return response()->json($course);
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
        ]);

        if ($request->has('title')) {
            $validated['slug'] = Str::slug($validated['title']);

            $count = Course::where('slug', 'like', $validated['slug'] . '%')
                ->whereNot('id', $course->id)->count();
            if ($count > 0) {
                $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
            }
        }

        $course->update($validated);

        return response()->json([
            "success" => true,
            "message" => "Course updated successfully!",
            "data"    => $course,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        $course->delete();

        return response()->json(null, 204);
    }
}
