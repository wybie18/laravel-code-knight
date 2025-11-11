<?php
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\TypingChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TypingChallengeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Allow both guests and authenticated users to view challenges
        // Authenticated users need either admin or challenge:view permission
        if ($request->user() && !$request->user()->tokenCan('admin:*') && !$request->user()->tokenCan('challenge:view')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $query = Challenge::with([
            'challengeable',
            'difficulty',
            'challengeable.programmingLanguage',
        ])->where('challengeable_type', TypingChallenge::class);

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->has('difficulty_ids')) {
            $difficultyIds = explode(',', $request->input('difficulty_ids'));
            $difficultyIds = array_filter(array_map('intval', $difficultyIds));

            if (! empty($difficultyIds)) {
                $query->whereIn('difficulty_id', $difficultyIds);
            }
        }

        if ($request->has('programming_language_ids')) {
            $languageIds = explode(',', $request->input('programming_language_ids'));

            $languageIds = array_filter(array_map('intval', $languageIds));

            if (! empty($languageIds)) {
                $query->whereHasMorph('challengeable', [TypingChallenge::class], function ($q) use ($languageIds) {
                    $q->whereIn('programming_language_id', $languageIds);
                });
            }
        }

        if ($request->has('hide_solved') && $request->boolean('hide_solved') && $request->user()) {
            $userId = $request->user()->id;

            $query->whereDoesntHave('submissions', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->where('is_correct', true);
            });
        }

        $sortField     = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $challenges = $query->paginate(15);

        $challenges->getCollection()->transform(function ($challenge) use ($request) {
            // Check if user is authenticated before checking solved status
            if ($request->user()) {
                $challenge->is_solved = ChallengeSubmission::where('challenge_id', $challenge->id)
                    ->where('user_id', $request->user()->id)
                    ->where('is_correct', true)
                    ->exists();
            } else {
                $challenge->is_solved = false;
            }
            return $challenge;
        });

        return ChallengeResource::collection($challenges)->additional([
            'success' => true,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $validated = $request->validate([
            // Challenge fields
            'title'                   => 'required|string|max:255|unique:challenges,title',
            'description'             => 'nullable|string',
            'difficulty_id'           => 'required|exists:difficulties,id',
            'points'                  => 'required|integer|min:0',
            'hints'                   => 'nullable|string',

            // TypingChallenge specific fields
            'text_content'            => 'required|string',
            'programming_language_id' => 'required|exists:programming_languages,id',
            'target_wpm'              => 'nullable|integer|min:0',
            'target_accuracy'         => 'nullable|numeric|between:0,100',
        ]);

        DB::beginTransaction();

        try {
            $typingChallenge = TypingChallenge::create([
                'text_content'            => $validated['text_content'],
                'programming_language_id' => $validated['programming_language_id'],
                'target_wpm'              => $validated['target_wpm'],
                'target_accuracy'         => $validated['target_accuracy'],
            ]);

            $challenge = new Challenge([
                'title'         => $validated['title'],
                'slug'          => Str::slug($validated['title']),
                'description'   => $validated['description'],
                'difficulty_id' => $validated['difficulty_id'],
                'points'        => $validated['points'],
                'hints'         => $validated['hints'] ?? null,
            ]);

            $typingChallenge->challenge()->save($challenge);

            DB::commit();

            $challenge->load(['challengeable', 'difficulty', 'challengeable.programmingLanguage']);

            return (new ChallengeResource($challenge))
                ->additional([
                    'success' => true,
                    'message' => 'Typing Challenge created successfully.',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Typing Challenge.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        // Allow both guests and authenticated users to view challenges
        // Authenticated users need either admin or challenge:view permission
        if (request()->user() && !request()->user()->tokenCan('admin:*') && !request()->user()->tokenCan('challenge:view')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', TypingChallenge::class)
            ->with(['challengeable', 'difficulty', 'challengeable.programmingLanguage'])
            ->firstOrFail();

        return (new ChallengeResource($challenge))
            ->additional([
                'success' => true,
            ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $slug)
    {
        if (! $request->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', TypingChallenge::class)
            ->with('challengeable')
            ->firstOrFail();

        $validated = $request->validate([
            'title'                   => 'required|string|max:255|unique:challenges,title,' . $challenge->id,
            'description'             => 'nullable|string',
            'difficulty_id'           => 'required|exists:difficulties,id',
            'points'                  => 'required|integer|min:0',
            'hints'                   => 'nullable|string',

            'text_content'            => 'required|string',
            'programming_language_id' => 'required|exists:programming_languages,id',
            'target_wpm'              => 'nullable|integer|min:0',
            'target_accuracy'         => 'nullable|numeric|between:0,100',
        ]);

        DB::beginTransaction();

        try {
            $typingChallenge = $challenge->challengeable;

            $typingChallenge->update([
                'text_content'            => $validated['text_content'],
                'programming_language_id' => $validated['programming_language_id'],
                'target_wpm'              => $validated['target_wpm'],
                'target_accuracy'         => $validated['target_accuracy'],
            ]);

            $challenge->update([
                'title'         => $validated['title'],
                'slug'          => Str::slug($validated['title']),
                'description'   => $validated['description'],
                'difficulty_id' => $validated['difficulty_id'],
                'points'        => $validated['points'],
                'hints'         => $validated['hints'] ?? null,
            ]);

            DB::commit();

            $challenge->load(['challengeable', 'difficulty', 'challengeable.programmingLanguage']);

            return (new ChallengeResource($challenge))
                ->additional([
                    'success' => true,
                    'message' => 'Typing Challenge updated successfully.',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Typing Challenge.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $slug)
    {
        if (! request()->user()->tokenCan('admin:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', TypingChallenge::class)
            ->with('challengeable')
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $challenge->challengeable->delete();
            $challenge->delete();

            DB::commit();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete Typing Challenge.',
                'error'   => $e->getMessage(),
            ], 500);
        }

    }
}
