<?php
namespace App\Http\Controllers;

use App\Http\Resources\CourseCategoryResource;
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

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");

        $query->orderBy($sortField, $sortDirection);

        $courseCategory = $query->withCount('courses')->paginate(15);

        return CourseCategoryResource::collection($courseCategory)->additional([
            'success' => true]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255|unique:course_categories,name',
            'color' => 'nullable|string|max:7',
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $courseCategory = CourseCategory::create($validated);
        $courseCategory->loadCount('courses');
        return (new CourseCategoryResource($courseCategory))
            ->additional([
                'success' => true,
                'message' => 'Course category created successfully.',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $courseCategory = CourseCategory::findOrFail($id)->loadCount('courses');
        return (new CourseCategoryResource($courseCategory))
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $courseCategory = CourseCategory::findOrFail($id);
        $validated      = $request->validate([
            'name'  => 'required|string|max:255|unique:course_categories,name,' . $courseCategory->id,
            'color' => 'nullable|string|max:7',
        ]);

        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $courseCategory->update($validated);
        $courseCategory->loadCount('courses');
        return (new CourseCategoryResource($courseCategory))
            ->additional([
                'success' => true,
                'message' => 'Course category updated successfully.',
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $courseCategory = CourseCategory::findOrFail($id);
        $courseCategory->delete();

        return response()->json(null, 204);
    }
}
