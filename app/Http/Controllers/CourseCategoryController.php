<?php

namespace App\Http\Controllers;

use App\Models\CourseCategory;
use Illuminate\Http\Request;

class CourseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CourseCategory::query();

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            });
        }

        $sortField = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $courseCategory = $query->withCount('courses')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $courseCategory,
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

        if(!$request->user()->tokenCan('admin:*')){
            abort(403, 'Unauthorized. You do not have permission.');
        }

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
    public function show(string $id)
    {
        $courseCategory = CourseCategory::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $courseCategory->loadCount('courses'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $courseCategory = CourseCategory::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:course_categories,name,' . $courseCategory->id,
            'color' => 'nullable|string|max:7',
        ]);

        if(!$request->user()->tokenCan('admin:*')){
            abort(403, 'Unauthorized. You do not have permission.');
        }

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
    public function destroy(string $id)
    {
        if(!request()->user()->tokenCan('admin:*')){
            abort(403, 'Unauthorized. You do not have permission.');
        }
        
        $courseCategory = CourseCategory::findOrFail($id);
        $courseCategory->delete();

        return response()->json(null, 204);
    }
}
