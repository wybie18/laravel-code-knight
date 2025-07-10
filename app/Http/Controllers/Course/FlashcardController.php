<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use Illuminate\Http\Request;

class FlashcardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $flashcards = Flashcard::with(['course', 'userProgress'])->get();

        return response()->json([
            'success' => true,
            'data' => $flashcards,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'front_content' => 'required|string',
            'back_content' => 'required|string',
            'order' => 'integer',
            'exp_reward' => 'integer',
        ]);

        $flashcard = Flashcard::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Flashcard created successfully.',
            'data' => $flashcard->load(['course', 'userProgress']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Flashcard $flashcard)
    {
        return response()->json([
            'success' => true,
            'data' => $flashcard->load(['course', 'userProgress']),
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Flashcard $flashcard)
    {
        $validated = $request->validate([
            'course_id' => 'exists:courses,id',
            'title' => 'string|max:255',
            'front_content' => 'string',
            'back_content' => 'string',
            'order' => 'integer',
            'exp_reward' => 'integer',
        ]);

        $flashcard->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Flashcard updated successfully.',
            'data' => $flashcard->fresh()->load(['course', 'userProgress']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Flashcard $flashcard)
    {
        $flashcard->delete();

        return response()->json(null, 204);
    }
}
