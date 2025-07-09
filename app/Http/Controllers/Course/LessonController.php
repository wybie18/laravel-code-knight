<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Course $course)
    {
        return response()->json([
            'success' => true,
            'data'    => $course->lessons()->paginate(15)
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Course $course)
    {
        $validated = $request->validate([
            'title'              => [
                'required',
                'string',
                'max:255',
                Rule::unique('lessons')->where('course_id', $course->id),
            ],
            'content'            => 'nullable|string',
            'video_url'          => 'nullable|url',
            'exp_reward'         => 'nullable|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        $count = Lesson::where('course_id', $course->id)
            ->where('slug', 'like', $validated['slug'] . '%')->count();
        if ($count > 0) {
            $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
        }

        $validated['order'] = ($course->lessons()->max('order') ?? 0) + 1;

        $lesson = $course->lessons()->create($validated);

        return response()->json([
            'success' => true,
            'data'    => $lesson,
            'message' => 'Lesson created successfully!',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lesson $lesson)
    {
        $lesson->load(['course', 'activities']);
        return response()->json($lesson);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Lesson $lesson)
    {
        $validated = $request->validate([
            'title'              => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('lessons')
                    ->where('course_id', $lesson->course_id)
                    ->ignore($lesson->id),
            ],
            'content'            => 'nullable|string',
            'video_url'          => 'nullable|url',
            'exp_reward'         => 'nullable|integer|min:0',
            'estimated_duration' => 'nullable|integer|min:0',
            'order'              => 'sometimes|integer|min:1',
        ]);

        if ($request->has('title')) {
            $validated['slug'] = Str::slug($validated['title']);

            $count = Lesson::where('course_id', $lesson->course_id)
                ->where('slug', 'like', $validated['slug'] . '%')
                ->whereNot('id', $lesson->id)->count();
            if ($count > 0) {
                $validated['slug'] = $validated['slug'] . '-' . ($count + 1);
            }
        }

        $lesson->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $lesson,
            'message' => 'Lesson updated successfully!',
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson)
    {
        $lesson->delete();
        return response()->json(null, 204);
    }
}

