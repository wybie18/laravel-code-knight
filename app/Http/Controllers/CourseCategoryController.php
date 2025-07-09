<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Http\Request;

class CourseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => CourseCategory::withCount('courses')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:course_categories,name',
            'color' => 'nullable|string|max:7',
        ]);

        $courseCategory = CourseCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course category created successfully.',
            'data' => $courseCategory,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CourseCategory $courseCategory)
    {
        return response()->json([
            'success' => true,
            'data' => $courseCategory->loadCount('courses'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CourseCategory $courseCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:course_categories,name,' . $courseCategory->id,
            'color' => 'nullable|string|max:7',
        ]);

        $courseCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Course category updated successfully.',
            'data' => $courseCategory,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseCategory $courseCategory)
    {
        $courseCategory->delete();

        return response()->json(null, 204);
    }
}
