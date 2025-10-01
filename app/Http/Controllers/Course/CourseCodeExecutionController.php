<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Models\ProgrammingLanguage;
use App\Services\Judge0TestRunner;
use Illuminate\Http\Request;

class CourseCodeExecutionController extends Controller
{
    private $testRunner;

    public function __construct(Judge0TestRunner $testRunner)
    {
        $this->testRunner = $testRunner;
    }

    public function executeCode(Request $request)
    {
         $validatedData = $request->validate([
            'language_id' => 'required|integer|exists:programming_languages,id',
            'user_code'   => 'required|string',
            'user_input'  => 'nullable|string',
        ]);

        $programmingLang = ProgrammingLanguage::findOrFail($validatedData['language_id']);

        try {
            $result = $this->testRunner->runPlaygroundCode($programmingLang, $validatedData['user_code'], $validatedData['user_input']);

            return response()->json([
                'success' => true,
                'data'    => $result,
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
