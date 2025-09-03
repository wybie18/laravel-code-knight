<?php
namespace App\Http\Controllers\Challenge;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ChallengeCodeExecutionService;

class ChallengeCodeExecutionController extends Controller
{
    private $codeExecutionService;

    public function __construct(ChallengeCodeExecutionService $codeExecutionService)
    {
        $this->codeExecutionService = $codeExecutionService;
    }

    public function executeCode(Request $request, string $slug): JsonResponse
    {
        $validatedData = $request->validate([
            'language_id' => 'required|integer|exists:programming_languages,id',
            'user_code'   => 'required|string',
        ]);

        try {
            $result = $this->codeExecutionService->executeCode(
                $slug,
                $validatedData['language_id'],
                $validatedData['user_code']
            );

            return response()->json([
                'success' => true,
                'data'    => $result['results'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Code execution failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function submitCode(Request $request, string $slug): JsonResponse
    {
        $validatedData = $request->validate([
            'language_id' => 'required|integer|exists:programming_languages,id',
            'user_code'   => 'required|string',
        ]);

        try {
            $result = $this->codeExecutionService->submitCode(
                $slug,
                $validatedData['language_id'],
                $validatedData['user_code'],
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data'    => $result['results'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Code execution failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
