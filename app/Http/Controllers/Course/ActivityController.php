<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Lesson $lesson)
    {
        $activities = $lesson->activities()
            ->with(['codingActivityProblem', 'quizQuestions'])
            ->orderBy('order')
            ->get();

        return ActivityResource::collection($activities)->additional([
            'success' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Lesson $lesson)
    {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('activities')->where('lesson_id', $lesson->id),
            ],
            'description' => 'nullable|string',
            'type' => 'required|in:code,quiz',
            'coding_activity_problem_id' => 'required_if:type,code|nullable|exists:coding_activity_problems,id',
            'exp_reward' => 'nullable|integer|min:0',
            'order' => 'sometimes|integer|min:1',
            'is_required' => 'sometimes|boolean',
        ]);

        if (!isset($validated['order'])) {
            $validated['order'] = ($lesson->activities()->max('order') ?? 0) + 1;
        } else {
            $this->reorderActivities($lesson, $validated['order']);
        }

        $activity = $lesson->activities()->create($validated);

        if ($activity->type === 'code') {
            $activity->load('codingActivityProblem');
        } elseif ($activity->type === 'quiz') {
            $activity->load('quizQuestions');
        }

        return (new ActivityResource($activity))->additional([
            'success' => true,
            'message' => 'Activity created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lesson $lesson, Activity $activity)
    {
        if ($activity->lesson_id !== $lesson->id) {
            return response()->json(['success' => false, 'message' => 'Activity not found in this lesson.'], 404);
        }

        if ($activity->type === 'code') {
            $activity->load('codingActivityProblem');
        } elseif ($activity->type === 'quiz') {
            $activity->load('quizQuestions');
        }
        
        // You might also want to load user-specific submissions here
        // $activity->load('activitySubmissions' => fn($q) => $q->where('user_id', auth()->id()));

        return (new ActivityResource($activity))->additional(['success' => true]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Lesson $lesson, Activity $activity)
    {
        if ($activity->lesson_id !== $lesson->id) {
            return response()->json(['success' => false, 'message' => 'Activity not found in this lesson.'], 404);
        }

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('activities')->where('lesson_id', $lesson->id)->ignore($activity->id),
            ],
            'description' => 'nullable|string',
            'type' => 'sometimes|in:code,quiz',
            'coding_activity_problem_id' => 'required_if:type,code|nullable|exists:coding_activity_problems,id',
            'exp_reward' => 'nullable|integer|min:0',
            'order' => 'sometimes|integer|min:1',
            'is_required' => 'sometimes|boolean',
        ]);

        if ($request->has('order') && $validated['order'] !== $activity->order) {
            $this->reorderActivities($lesson, $validated['order'], $activity->id);
        }

        $activity->update($validated);

        if ($activity->type === 'code') {
            $activity->load('codingActivityProblem');
        } elseif ($activity->type === 'quiz') {
            $activity->load('quizQuestions');
        }

        return (new ActivityResource($activity))->additional([
            'success' => true,
            'message' => 'Activity updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson, Activity $activity)
    {
        if ($activity->lesson_id !== $lesson->id) {
            return response()->json(['success' => false, 'message' => 'Activity not found in this lesson.'], 404);
        }

        $activity->delete();

        $this->reorderActivitiesAfterDeletion($lesson);

        return response()->json(null, 204);
    }

    /**
     * Reorder activities to accommodate a new or updated item.
     */
    private function reorderActivities(Lesson $lesson, int $newOrder, ?int $excludeId = null): void
    {
        $query = $lesson->activities()->where('order', '>=', $newOrder);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->increment('order');
    }

    /**
     * Reorder activities after one has been deleted.
     */
    private function reorderActivitiesAfterDeletion(Lesson $lesson): void
    {
        $activities = $lesson->activities()->orderBy('order')->get();

        foreach ($activities as $index => $activity) {
            // Update the order to be sequential (1, 2, 3, ...)
            $activity->update(['order' => $index + 1]);
        }
    }
}
