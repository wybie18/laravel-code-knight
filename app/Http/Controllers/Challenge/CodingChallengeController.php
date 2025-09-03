<?php
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CodingChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CodingChallengeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (! $request->user()->tokenCan('admin:*') && ! $request->user()->tokenCan('challenge:view')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $query = Challenge::query()
            ->where('challengeable_type', CodingChallenge::class);

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
                $query->whereExists(function ($q) use ($languageIds) {
                    $q->select(DB::raw(1))
                        ->from('coding_challenges')
                        ->whereRaw('coding_challenges.id = challenges.challengeable_id')
                        ->whereExists(function ($q2) use ($languageIds) {
                            $q2->select(DB::raw(1))
                                ->from('challenge_language')
                                ->whereRaw('challenge_language.coding_challenge_id = coding_challenges.id')
                                ->whereIn('challenge_language.programming_language_id', $languageIds);
                        });
                });
            }
        }

        if ($request->has('hide_solved') && $request->boolean('hide_solved')) {
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

        $challenges->load(['challengeable', 'difficulty', 'challengeable.programmingLanguages']);

        $challenges->getCollection()->transform(function ($challenge) use ($request) {
            $challenge->is_solved = ChallengeSubmission::where('challenge_id', $challenge->id)
                ->where('user_id', $request->user()->id)
                ->where('is_correct', true)
                ->exists();
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
            'title'                                => 'required|string|max:255|unique:challenges,title',
            'description'                          => 'nullable|string',
            'difficulty_id'                        => 'required|exists:difficulties,id',
            'points'                               => 'required|integer|min:0',
            'hints'                                => 'nullable|string',

            // CodingChallenge specific fields
            'problem_statement'                    => 'required|string',
            'test_cases'                           => 'required|json',

            // Programming languages and pivot data
            'programming_languages'                => 'required|array|min:1',
            'programming_languages.*.language_id'  => 'required|exists:programming_languages,id',
            'programming_languages.*.starter_code' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $codingChallenge = CodingChallenge::create([
                'problem_statement' => $validated['problem_statement'],
                'test_cases'        => $validated['test_cases'],
            ]);

            $challenge = new Challenge([
                'title'         => $validated['title'],
                'slug'          => Str::slug($validated['title']),
                'description'   => $validated['description'],
                'difficulty_id' => $validated['difficulty_id'],
                'points'        => $validated['points'],
                'hints'         => $validated['hints'] ?? null,
            ]);

            $codingChallenge->challenge()->save($challenge);

            $languagesToAttach = [];
            foreach ($validated['programming_languages'] as $langData) {
                $languagesToAttach[$langData['language_id']] = [
                    'starter_code' => $langData['starter_code'] ?? null,
                ];
            }
            $codingChallenge->programmingLanguages()->attach($languagesToAttach);

            DB::commit();

            $challenge->load(['challengeable', 'difficulty', 'challengeable.programmingLanguages']);

            return (new ChallengeResource($challenge))
                ->additional([
                    'success' => true,
                    'message' => 'Coding Challenge created successfully.',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Coding Challenge.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        if (! request()->user()->tokenCan('admin:*') && ! request()->user()->tokenCan('challenge:view')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', CodingChallenge::class)
            ->with(['challengeable', 'difficulty', 'challengeable.programmingLanguages'])
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
            ->where('challengeable_type', CodingChallenge::class)
            ->with('challengeable')
            ->firstOrFail();

        $validated = $request->validate([
            'title'                                => 'required|string|max:255|unique:challenges,title,' . $challenge->id,
            'description'                          => 'nullable|string',
            'difficulty_id'                        => 'required|exists:difficulties,id',
            'points'                               => 'required|integer|min:0',
            'hints'                                => 'nullable|string',

            'problem_statement'                    => 'required|string',
            'test_cases'                           => 'required|json',

            'programming_languages'                => 'required|array|min:1',
            'programming_languages.*.id'           => 'required|exists:programming_languages,id',
            'programming_languages.*.starter_code' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $codingChallenge = $challenge->challengeable;

            $codingChallenge->update([
                'problem_statement' => $validated['problem_statement'],
                'test_cases'        => $validated['test_cases'],
            ]);

            $challenge->update([
                'title'         => $validated['title'],
                'slug'          => Str::slug($validated['title']),
                'description'   => $validated['description'],
                'difficulty_id' => $validated['difficulty_id'],
                'points'        => $validated['points'],
                'hints'         => $validated['hints'] ?? null,
            ]);

            $languagesToSync = [];
            foreach ($validated['programming_languages'] as $langData) {
                $languagesToSync[$langData['id']] = [
                    'starter_code' => $langData['starter_code'] ?? null,
                ];
            }
            $codingChallenge->programmingLanguages()->sync($languagesToSync);

            DB::commit();

            $challenge->load(['challengeable', 'difficulty', 'challengeable.programmingLanguages']);

            return (new ChallengeResource($challenge))
                ->additional([
                    'success' => true,
                    'message' => 'Coding Challenge updated successfully.',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Coding Challenge.',
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
            ->where('challengeable_type', CodingChallenge::class)
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
                'message' => 'Failed to delete Coding Challenge.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
