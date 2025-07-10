<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activities = Activity::with(['lesson', 'codingActivityProblem', 'activitySubmissions', 'quizQuestions'])->get();

        return response()->json([
            'success' => true,
            'data' => $activities,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lesson_id' => 'required|exists:lessons,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:content,code,quiz',
            'content' => 'nullable|string',
            'coding_activity_problem_id' => 'nullable|exists:coding_activity_problems,id',
            'exp_reward' => 'integer',
            'order' => 'integer',
            'is_required' => 'boolean',
        ]);

        $activity = Activity::create($validated);

        return response()->json([
            'success' => true,
            'data' => $activity->load(['lesson', 'codingActivityProblem', 'activitySubmissions', 'quizQuestions']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        return response()->json([
            'success' => true,
            'data' => $activity->load(['lesson', 'codingActivityProblem', 'activitySubmissions', 'quizQuestions']),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'lesson_id' => 'exists:lessons,id',
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'type' => 'in:content,code,quiz',
            'content' => 'nullable|string',
            'coding_activity_problem_id' => 'nullable|exists:coding_activity_problems,id',
            'exp_reward' => 'integer',
            'order' => 'integer',
            'is_required' => 'boolean',
        ]);

        $activity->update($validated);

        return response()->json([
            'success' => true,
            'data' => $activity->fresh()->load(['lesson', 'codingActivityProblem', 'activitySubmissions', 'quizQuestions']),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        $activity->delete();

        return response()->json(null, 204);
    }
}
