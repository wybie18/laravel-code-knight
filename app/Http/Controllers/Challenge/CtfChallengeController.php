<?php
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\CtfChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CtfChallengeController extends Controller
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

        $query = Challenge::query()
            ->where('challengeable_type', CtfChallenge::class);

        if ($request->has('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                    ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->has('category_ids')) {
            $categoryIds = explode(',', $request->input('category_ids'));

            $categoryIds = array_filter(array_map('intval', $categoryIds));

            if (! empty($categoryIds)) {
                $query->whereExists(function ($q) use ($categoryIds) {
                    $q->select(DB::raw(1))
                        ->from('ctf_challenges')
                        ->whereRaw('ctf_challenges.id = challenges.challengeable_id')
                        ->whereIn('ctf_challenges.category_id', $categoryIds);
                });
            }
        }

        if ($request->has('difficulty_ids')) {
            $difficultyIds = explode(',', $request->input('difficulty_ids'));
            $difficultyIds = array_filter(array_map('intval', $difficultyIds));

            if (! empty($difficultyIds)) {
                $query->whereIn('difficulty_id', $difficultyIds);
            }
        }

        if ($request->has('hide_solved') && $request->boolean('hide_solved') && $request->user()) {
            $userId = $request->user()->id;

            $query->whereDoesntHave('submissions', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->where('is_correct', true);
            });
        }

        $sortField     = request("sort_field", "created_at");
        $sortDirection = request("sort_direction", "desc");
        $query->orderBy($sortField, $sortDirection);

        $challenges = $query->paginate(15);

        $challenges->load(['challengeable', 'difficulty', 'challengeable.category']);

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
            'title'             => 'required|string|max:255|unique:challenges,title',
            'description'       => 'nullable|string',
            'difficulty_id'     => 'required|exists:difficulties,id',
            'points'            => 'required|integer|min:0',
            'hints'             => 'nullable|string',

            // CtfChallenge specific fields
            'flag'              => 'required|string|max:255',
            'category_id'       => 'required|exists:ctf_categories,id',
            'challenge_files.*' => 'nullable|file|max:2048',
        ]);

        DB::beginTransaction();

        try {
            $filePaths = [];
            if ($request->hasFile('challenge_files')) {
                foreach ($request->file('challenge_files') as $file) {
                    $path        = $file->store('ctf_challenges/files', 'public');
                    $filePaths[] = $path;
                }
            }

            $ctfChallenge = CtfChallenge::create([
                'flag'        => $validated['flag'],
                'category_id' => $validated['category_id'],
                'file_paths'  => $filePaths,
            ]);

            $challenge = new Challenge([
                'title'         => $validated['title'],
                'slug'          => Str::slug($validated['title']),
                'description'   => $validated['description'],
                'difficulty_id' => $validated['difficulty_id'],
                'points'        => $validated['points'],
                'hints'         => $validated['hints'] ?? null,
            ]);

            $ctfChallenge->challenge()->save($challenge);

            DB::commit();

            $challenge->load(['challengeable', 'difficulty', 'challengeable.category']);

            return (new ChallengeResource($challenge))
                ->additional([
                    'success' => true,
                    'message' => 'CTF Challenge created successfully.',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($filePaths as $path) {
                Storage::disk('public')->delete($path);
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to create CTF Challenge.',
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
        // Authenticated users need either admin or user permissions
        if (request()->user() && !request()->user()->tokenCan('admin:*') && !request()->user()->tokenCan('user:*')) {
            abort(403, 'Unauthorized. You do not have permission.');
        }

        $challenge = Challenge::where('slug', $slug)
            ->where('challengeable_type', CtfChallenge::class)
            ->with(['challengeable', 'difficulty', 'challengeable.category'])
            ->firstOrFail();
            
        // Hide flag from non-admin users (including guests)
        if (!request()->user() || !request()->user()->tokenCan('admin:*')) {
            unset($challenge->challengeable->flag);
        }

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
            ->where('challengeable_type', CtfChallenge::class)
            ->with('challengeable')
            ->firstOrFail();

        $validated = $request->validate([
            'title'               => 'required|string|max:255|unique:challenges,title,' . $challenge->id,
            'description'         => 'nullable|string',
            'difficulty_id'       => 'required|exists:difficulties,id',
            'points'              => 'required|integer|min:0',
            'hints'               => 'nullable|string',

            'flag'                => 'required|string|max:255',
            'category_id'         => 'required|exists:ctf_categories,id',
            'challenge_files.*'   => 'nullable|file|max:2048',
            'existing_file_paths' => 'nullable|array', // Array of file paths to keep
        ]);

        DB::beginTransaction();

        try {
            $ctfChallenge = $challenge->challengeable;
            $oldFilePaths = $ctfChallenge->file_paths ?? [];
            $newFilePaths = $request->input('existing_file_paths', []);

            if ($request->hasFile('challenge_files')) {
                foreach ($request->file('challenge_files') as $file) {
                    $path           = $file->store('ctf_challenges/files', 'public');
                    $newFilePaths[] = $path;
                }
            }

            $filesToDelete = array_diff($oldFilePaths, $newFilePaths);
            foreach ($filesToDelete as $filePath) {
                Storage::disk('public')->delete($filePath);
            }

            $ctfChallenge->update([
                'flag'        => $validated['flag'],
                'category_id' => $validated['category_id'],
                'file_paths'  => array_values($newFilePaths),
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

            $challenge->load(['challengeable', 'difficulty', 'challengeable.category']);

            return (new ChallengeResource($challenge))
                ->additional([
                    'success' => true,
                    'message' => 'CTF Challenge updated successfully.',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->hasFile('challenge_files')) {
                foreach ($request->file('challenge_files') as $file) {
                    Storage::disk('public')->delete($file->hashName('ctf_challenges/files'));
                }
            }
            return response()->json([
                'success' => false,
                'message' => 'Failed to update CTF Challenge.',
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
            ->where('challengeable_type', CtfChallenge::class)
            ->with('challengeable')
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $filePaths = $challenge->challengeable->file_paths ?? [];
            $challenge->challengeable->delete();

            $challenge->delete();

            foreach ($filePaths as $filePath) {
                Storage::disk('public')->delete($filePath);
            }

            DB::commit();

            return response()->json(null, 204);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete CTF Challenge.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
