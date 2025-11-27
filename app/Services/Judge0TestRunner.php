<?php
namespace App\Services;

use App\Models\CodingActivityProblem;
use App\Models\CodingChallenge;
use App\Models\ProgrammingLanguage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Judge0TestRunner
{
    private $judge0Url;
    private $judge0ApiKey;

    public function __construct()
    {
        $this->judge0Url    = config('services.judge0.url');
        $this->judge0ApiKey = config('services.judge0.api_key');
    }

    /**
     * Run code in playground mode (without test cases)
     * Returns the raw output of the code execution
     */
    public function runPlaygroundCode(
        ProgrammingLanguage $language,
        string $userCode,
        ?string $input = null
    ): array {
        try {
            $submissionToken = $this->submitPlaygroundCode($userCode, $language->language_id, $input);

            if (! $submissionToken) {
                return [
                    'success'        => false,
                    'error'          => 'Failed to submit code for execution',
                    'output'         => null,
                    'stderr'         => null,
                    'execution_time' => null,
                    'memory_usage'   => null,
                ];
            }

            $judgeResult = $this->getSubmissionResult($submissionToken);

            if (! $judgeResult) {
                return [
                    'success'        => false,
                    'error'          => 'Failed to get execution result',
                    'output'         => null,
                    'stderr'         => null,
                    'execution_time' => null,
                    'memory_usage'   => null,
                ];
            }

            return $this->processPlaygroundResult($judgeResult);

        } catch (\Exception $e) {
            return [
                'success'        => false,
                'error'          => $e->getMessage(),
                'output'         => null,
                'stderr'         => null,
                'execution_time' => null,
                'memory_usage'   => null,
            ];
        }
    }

    /**
     * Process playground execution result
     */
    private function processPlaygroundResult(array $judgeResult): array
    {
        $statusId = $judgeResult['status']['id'];
        $stdout   = null;
        $stderr   = null;

        if (isset($judgeResult['stdout']) && $judgeResult['stdout']) {
            $stdout = $this->safeDecode($judgeResult['stdout']);
        }

        if (isset($judgeResult['stderr']) && $judgeResult['stderr']) {
            $stderr = $this->safeDecode($judgeResult['stderr']);
        }

        if ($statusId === 3) {
            return [
                'success'        => true,
                'error'          => null,
                'output'         => $stdout,
                'stderr'         => $stderr,
                'execution_time' => $judgeResult['time'] ?? null,
                'memory_usage'   => $judgeResult['memory'] ?? null,
                'status'         => $judgeResult['status']['description'] ?? 'Completed',
            ];
        }

        return [
            'success'        => false,
            'error'          => $judgeResult['status']['description'] ?? 'Unknown execution error',
            'output'         => $stdout,
            'stderr'         => $stderr,
            'execution_time' => $judgeResult['time'] ?? null,
            'memory_usage'   => $judgeResult['memory'] ?? null,
            'status'         => $judgeResult['status']['description'] ?? 'Failed',
        ];
    }

    public function runActivityTestCases(
        CodingActivityProblem $problem,
        ProgrammingLanguage $language,
        string $userCode
    ): array {
        $testCases = $problem->test_cases;
        $results   = [];

        foreach ($testCases as $index => $testCase) {
            $result = $this->runSingleTestCase(
                $userCode,
                $language,
                $testCase,
                $index,
                true
            );

            $results[] = $result;

            if (! $result['passed']) {
                break;
            }
        }

        $public_cases_to_show = 3;
        $publicResults        = array_slice($results, 0, $public_cases_to_show);
        $allPassed            = collect($results)->every('passed');

        if (! $allPassed && count($results) > $public_cases_to_show) {
            $firstFailedResult = collect($results)->firstWhere('passed', false);
            $firstFailedIndex  = array_search($firstFailedResult, $results);

            if ($firstFailedIndex >= $public_cases_to_show) {
                $publicResults   = $publicResults;
                $publicResults[] = [
                    'test_case' => 'Hidden Test',
                    'passed'    => false,
                    'error'     => 'One or more hidden test cases failed.',
                ];
            }
        }

        return [
            'passed'       => collect($results)->every('passed'),
            'total_cases'  => count($testCase),
            'passed_cases' => collect($results)->where('passed', true)->count(),
            'results'      => $publicResults,
        ];
    }

    /**
     * Run all test cases for a coding challenge
     */
    public function runTestCases(
        CodingChallenge $codingChallenge,
        ProgrammingLanguage $language,
        string $userCode,
        ?int $numTestsToRun = null
    ): array {
        $testCases = $codingChallenge->test_cases;
        $results   = [];

        if ($numTestsToRun !== null) {
            if ($numTestsToRun > count($testCases)) {
                $numTestsToRun = count($testCases);
            }
            $testCasesToRun = array_slice($testCases, 0, $numTestsToRun);
        } else {
            $testCasesToRun = $testCases;
        }

        foreach ($testCasesToRun as $index => $testCase) {
            $result = $this->runSingleTestCase(
                $userCode,
                $language,
                $testCase,
                $index
            );

            $results[] = $result;

            if (! $result['passed']) {
                break;
            }
        }

        $public_cases_to_show = 3;
        $publicResults        = array_slice($results, 0, $public_cases_to_show);
        $allPassed            = collect($results)->every('passed');

        if (! $allPassed && count($results) > $public_cases_to_show) {
            $firstFailedResult = collect($results)->firstWhere('passed', false);
            $firstFailedIndex  = array_search($firstFailedResult, $results);

            if ($firstFailedIndex >= $public_cases_to_show) {
                $publicResults   = $publicResults;
                $publicResults[] = [
                    'test_case' => 'Hidden Test',
                    'passed'    => false,
                    'error'     => 'One or more hidden test cases failed.',
                ];
            }
        }

        return [
            'passed'       => collect($results)->every('passed'),
            'total_cases'  => count($testCasesToRun),
            'passed_cases' => collect($results)->where('passed', true)->count(),
            'results'      => $publicResults,
        ];
    }

    /**
     * Run a single test case
     */
    private function runSingleTestCase(
        string $userCode,
        ProgrammingLanguage $language,
        array $testCase,
        int $index,
        bool $isActivity = false
    ): array {
        try {
            $cleanUserCode = $this->cleanText($userCode);

            $completeCode = $this->prepareCodeWithTestCase($cleanUserCode, $language, $testCase, $isActivity);

            $submissionToken = $this->submitCode($completeCode, $language->language_id);

            if (! $submissionToken) {
                return $this->createErrorResult($index, 'Failed to submit code');
            }

            $judgeResult = $this->getSubmissionResult($submissionToken);

            if (! $judgeResult) {
                return $this->createErrorResult($index, 'Failed to get submission result');
            }

            return $this->processTestResult($judgeResult, $testCase, $index);

        } catch (\Exception $e) {
            return $this->createErrorResult($index, $e->getMessage());
        }
    }

    /**
     * Clean and normalize code to prevent encoding issues
     */
    private function cleanText(string $text): string
    {
        // Remove BOM if present
        $text = str_replace("\xEF\xBB\xBF", '', $text);

        // Normalize line endings to \n
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove any null bytes or other problematic characters
        $text = str_replace("\0", '', $text);

        // Ensure UTF-8 encoding
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));
        }

        $text = trim($text);

        return $text;
    }

    /**
     * Prepare code with test case input based on language
     */
    private function prepareCodeWithTestCase(
        string $userCode,
        ProgrammingLanguage $language,
        array $testCase,
        bool $isActivity = false
    ): string {
        $input        = $testCase['input'];
        $languageName = strtolower($language->name);

        switch ($languageName) {
            case 'python':
                return $this->preparePythonCode($userCode, $input, $isActivity);

            case 'javascript':
            case 'node.js':
                return $this->prepareJavaScriptCode($userCode, $input, $isActivity);

            case 'java':
                return $this->prepareJavaCode($userCode, $input, $isActivity);

            default:
                throw new \Exception("Unsupported language: {$language->name}");
        }
    }

    /**
     * Check if input is empty or contains only empty values
     */
    private function hasInput($input): bool
    {
        if (empty($input) || $input === '' || $input === '""') {
            return false;
        }

        if (is_array($input) && empty($input)) {
            return false;
        }

        return true;
    }

    /**
     * Check if user code has a Solution class
     */
    private function hasSolutionClass(string $userCode, string $languageName): bool
    {
        switch ($languageName) {
            case 'python':
                return preg_match('/class\s+Solution\s*[\(:]/i', $userCode) &&
                preg_match('/def\s+main\s*\(/i', $userCode);

            case 'javascript':
            case 'node.js':
                return preg_match('/class\s+Solution\s*\{/i', $userCode) &&
                preg_match('/main\s*\(/i', $userCode);

            case 'java':
                return preg_match('/class\s+Solution\s*\{/i', $userCode) &&
                preg_match('/public\s+\w+\s+main\s*\(/i', $userCode);

            default:
                return false;
        }
    }

    /**
     * Prepare Python code with test input
     */
    private function preparePythonCode(string $userCode, $input, bool $isActivity = false): string
    {
        $definitions      = $this->getHiddenDefinitions('python');
        $hasSolutionClass = $this->hasSolutionClass($userCode, 'python');

        if ($isActivity && ! $hasSolutionClass) {
            if (! $this->hasInput($input)) {
                return <<<PYTHON
                {$definitions}
                {$userCode}
                PYTHON;
            } else {
                $inputArray  = is_string($input) ? json_decode($input, true) : $input;
                $inputValues = $this->formatInputForPython($inputArray, $userCode);

                return <<<PYTHON
                from typing import *
                {$definitions}
                {$inputValues}
                {$userCode}
                PYTHON;
            }
        }

        if (! $this->hasInput($input)) {
            $testCode = <<<PYTHON
            from typing import *
            {$definitions}
            {$userCode}

            # Test execution
            try:
                solution = Solution()
                result = solution.main()
                print(repr(result))
            except Exception as e:
                print(f"Runtime Error: {e}")
                import sys
                sys.exit(1)
            PYTHON;
        } else {
            $inputArray  = is_string($input) ? json_decode($input, true) : $input;
            $inputValues = $this->formatInputForPython($inputArray, $userCode);

            $testCode = <<<PYTHON
            from typing import *
            {$definitions}
            {$userCode}

            # Test execution
            {$inputValues}
            try:
                solution = Solution()
                result = solution.main({$this->getPythonArguments($inputArray)})
                print(repr(result))
            except Exception as e:
                print(f"Runtime Error: {e}")
                import sys
                sys.exit(1)
            PYTHON;
        }

        return $testCode;
    }

    /**
     * Prepare JavaScript code with test input
     */
    private function prepareJavaScriptCode(string $userCode, $input, bool $isActivity = false): string
    {
        $definitions      = $this->getHiddenDefinitions('javascript');
        $hasSolutionClass = $this->hasSolutionClass($userCode, 'javascript');

        if ($isActivity && ! $hasSolutionClass) {
            if (! $this->hasInput($input)) {
                return <<<JAVASCRIPT
                {$definitions}
                {$userCode}
                JAVASCRIPT;
            } else {
                $inputArray  = is_string($input) ? json_decode($input, true) : $input;
                $inputValues = $this->formatInputForJavaScript($inputArray, $userCode);

                return <<<JAVASCRIPT
                {$definitions}
                {$inputValues}
                {$userCode}
                JAVASCRIPT;
            }
        }

        if (! $this->hasInput($input)) {
            $testCode = <<<JAVASCRIPT
            {$definitions}
            {$userCode}

            try {
                const solution = new Solution();
                const result = solution.main();
                console.log(JSON.stringify(result));
            } catch (error) {
                console.error('Runtime Error:', error.message);
                process.exit(1);
            }
            JAVASCRIPT;
        } else {
            $inputArray  = is_string($input) ? json_decode($input, true) : $input;
            $inputValues = $this->formatInputForJavaScript($inputArray, $userCode);

            $testCode = <<<JAVASCRIPT
            {$definitions}
            {$userCode}

            {$inputValues}
            try {
                const solution = new Solution();
                const result = solution.main({$this->getJavaScriptArguments($inputArray)});
                console.log(JSON.stringify(result));
            } catch (error) {
                console.error('Runtime Error:', error.message);
                process.exit(1);
            }
            JAVASCRIPT;
        }

        return $testCode;
    }

    /**
     * Prepare Java code with test input
     */
    private function prepareJavaCode(string $userCode, $input, bool $isActivity = false): string
    {
        $definitions = $this->getHiddenDefinitions('java');

        // Check if user is using Main class with main method (Standard Java entry point)
        // If so, we return the code as is, assuming they handle execution themselves.
        // Note: This currently doesn't support injecting inputs via stdin for Main class,
        // so it works best for no-input problems or if we update submitCode to handle stdin.
        if (strpos($userCode, 'class Main') !== false && strpos($userCode, 'public static void main') !== false) {
            return $userCode . "\n\n" . $definitions;
        }

        $hasSolutionClass = $this->hasSolutionClass($userCode, 'java');

        if ($isActivity && ! $hasSolutionClass) {
            if (! $this->hasInput($input)) {
                return $userCode . "\n\n" . $definitions;
            } else {
                $inputArray  = is_string($input) ? json_decode($input, true) : $input;
                $inputValues = $this->formatInputForJava($inputArray, $userCode);
                Log::info("Input Values for Java Activity: " . $inputValues);
                if (preg_match('/(\s*public\s+static\s+void\s+main\s*\([^)]*\)\s*\{)/', $userCode, $matches, PREG_OFFSET_CAPTURE)) {
                    $mainMethodStart = $matches[1][1] + strlen($matches[1][0]);
                    $code            = substr($userCode, 0, $mainMethodStart) . "\n" . $inputValues . "\n" . substr($userCode, $mainMethodStart);
                    return $code . "\n\n" . $definitions;
                }

                return $inputValues . "\n" . $userCode . "\n\n" . $definitions;
            }
        }

        if (! $this->hasInput($input)) {
            $mainMethod = <<<JAVA

                public static void main(String[] args) {
                    try {
                        Solution solution = new Solution();
                        Object result = solution.main();
                        System.out.println(java.util.Arrays.deepToString(new Object[]{result}));
                    } catch (Exception e) {
                        System.err.println("Runtime Error: " + e.getMessage());
                        System.exit(1);
                    }
                }
            JAVA;
        } else {
            $inputArray  = is_string($input) ? json_decode($input, true) : $input;
            $inputValues = $this->formatInputForJava($inputArray, $userCode);

            $mainMethod = <<<JAVA

                public static void main(String[] args) {
                    try {
                        {$inputValues}
                        Solution solution = new Solution();
                        Object result = solution.main({$this->getJavaArguments($inputArray)});
                        System.out.println(java.util.Arrays.deepToString(new Object[]{result}));
                    } catch (Exception e) {
                        System.err.println("Runtime Error: " + e.getMessage());
                        System.exit(1);
                    }
                }
            JAVA;
        }

        $lastBrace = strrpos($userCode, '}');
        if ($lastBrace !== false) {
            return substr($userCode, 0, $lastBrace) . $mainMethod . "\n}" . "\n\n" . $definitions;
        }

        throw new \Exception("Invalid Java class structure");
    }

    /**
     * Get method arguments for different languages
     */
    private function getPythonArguments(array $input): string
    {
        return implode(', ', array_keys($input));
    }

    private function getJavaScriptArguments(array $input): string
    {
        return implode(', ', array_keys($input));
    }

    private function getJavaArguments(array $input): string
    {
        return implode(', ', array_keys($input));
    }

    /**
     * Format input values for different languages
     */
    private function formatInputForPython(array $input, string $userCode = ''): string
    {
        $assignments = [];
        foreach ($input as $key => $value) {
            // Check if user code expects ListNode for this variable
            if (preg_match("/{$key}\s*:\s*(?:Optional\[)?ListNode(?:\])?/", $userCode)) {
                $pythonValue   = $this->pythonValue($value);
                $assignments[] = "{$key} = list_to_ll({$pythonValue})";
            } else {
                $assignments[] = "{$key} = " . $this->pythonValue($value);
            }
        }
        return implode("\n", $assignments);
    }

    private function formatInputForJavaScript(array $input, string $userCode = ''): string
    {
        $assignments = [];
        foreach ($input as $key => $value) {
            // Check for JSDoc @param {ListNode} key
            if (preg_match("/@param\s+\{ListNode\}\s+{$key}/", $userCode)) {
                $jsValue       = json_encode($value, JSON_UNESCAPED_UNICODE);
                $assignments[] = "const {$key} = arrayToListNode({$jsValue});";
            } else {
                $assignments[] = "const {$key} = " . json_encode($value, JSON_UNESCAPED_UNICODE) . ";";
            }
        }
        return implode("\n", $assignments);
    }

    private function formatInputForJava(array $input, string $userCode = ''): string
    {
        $assignments = [];
        foreach ($input as $key => $value) {
            // Check if user code expects ListNode for this variable
            if (preg_match("/ListNode\s+{$key}/", $userCode)) {
                $javaValue     = $this->javaValue($value);
                $assignments[] = "            ListNode {$key} = ListNode.fromArray({$javaValue});";
            } else {
                $type          = $this->getJavaType($value);
                $javaValue     = $this->javaValue($value);
                $assignments[] = "            {$type} {$key} = {$javaValue};";
            }
        }
        return implode("\n", $assignments);
    }

    /**
     * Convert PHP value to Python representation
     */
    private function pythonValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'True' : 'False';
        } elseif (is_null($value)) {
            return 'None';
        } elseif (is_string($value)) {
            return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
        } elseif (is_array($value)) {
            if ($this->isAssociativeArray($value)) {
                // Dictionary
                $items = [];
                foreach ($value as $k => $v) {
                    $items[] = $this->pythonValue($k) . ': ' . $this->pythonValue($v);
                }
                return '{' . implode(', ', $items) . '}';
            } else {
                // List
                return '[' . implode(', ', array_map([$this, 'pythonValue'], $value)) . ']';
            }
        }
        return (string) $value;
    }

    /**
     * Convert PHP value to Java representation
     */
    private function javaValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } elseif (is_string($value)) {
            return '"' . str_replace(["\\", '"'], ["\\\\", '\\"'], $value) . '"';
        } elseif (is_array($value)) {
            if ($this->isAssociativeArray($value)) {
                // For simplicity, convert to JSON string for complex objects
                return '"' . str_replace('"', '\\"', json_encode($value, JSON_UNESCAPED_UNICODE)) . '"';
            } else {
                // Array
                $items = array_map([$this, 'javaValue'], $value);
                return 'new Object[]{' . implode(', ', $items) . '}';
            }
        }
        return (string) $value;
    }

    /**
     * Get Java type for a value
     */
    private function getJavaType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'int';
        } elseif (is_float($value)) {
            return 'double';
        } elseif (is_string($value)) {
            return 'String';
        } elseif (is_array($value)) {
            return 'Object[]';
        }
        return 'Object';
    }

    /**
     * Check if array is associative
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Submit code to Judge0
     */
    private function submitCode(string $code, int $languageId): ?string
    {
        // Ensure the code is properly encoded
        $encodedCode = mb_convert_encoding($code, 'UTF-8', 'UTF-8');

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($this->judge0ApiKey) {
            $headers['X-RapidAPI-Key'] = $this->judge0ApiKey;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post("{$this->judge0Url}/submissions", [
                'source_code'     => $encodedCode,
                'language_id'     => $languageId,
                'stdin'           => '',
                'expected_output' => null,
            ]);

        if ($response->successful()) {
            $responseData = $response->json();
            return $responseData['token'] ?? null;
        }

        return null;
    }

    /**
     * Submit code to Judge0 for playground execution
     */
    private function submitPlaygroundCode(string $code, int $languageId, ?string $input = null): ?string
    {
        // Ensure the code is properly encoded
        $encodedCode = mb_convert_encoding($code, 'UTF-8', 'UTF-8');

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($this->judge0ApiKey) {
            $headers['X-RapidAPI-Key'] = $this->judge0ApiKey;
        }

        $payload = [
            'source_code'     => $encodedCode,
            'language_id'     => $languageId,
            'stdin'           => $input ?? '',
            'expected_output' => null,
        ];

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post("{$this->judge0Url}/submissions", $payload);

        if ($response->successful()) {
            $responseData = $response->json();
            return $responseData['token'] ?? null;
        }

        return null;
    }

    /**
     * Get submission result from Judge0
     */
    private function getSubmissionResult(string $token): ?array
    {
        $maxAttempts = 15; // Increased attempts
        $attempt     = 0;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($this->judge0ApiKey) {
            $headers['X-RapidAPI-Key'] = $this->judge0ApiKey;
        }

        while ($attempt < $maxAttempts) {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get("{$this->judge0Url}/submissions/{$token}");

            if ($response->successful()) {
                $result = $response->json();

                // Status ID 1 = In Queue, 2 = Processing
                if (! in_array($result['status']['id'], [1, 2])) {
                    return $result;
                }
            }

            sleep(1);
            $attempt++;
        }

        return null;
    }

    /**
     * Process test result and compare with expected output
     */
    private function processTestResult(array $judgeResult, array $testCase, int $index): array
    {
        $statusId = $judgeResult['status']['id'];

        // Status ID 3 = Accepted
        if ($statusId !== 3) {
            $stderr = null;
            $stdout = null;

            if (isset($judgeResult['stderr']) && $judgeResult['stderr']) {
                $stderr = $this->safeDecode($judgeResult['stderr']);
            }

            if (isset($judgeResult['stdout']) && $judgeResult['stdout']) {
                $stdout = $this->safeDecode($judgeResult['stdout']);
            }

            return [
                'test_case' => $index,
                'passed'    => false,
                'error'     => $judgeResult['status']['description'] ?? 'Unknown error',
                'stderr'    => $stderr,
                'stdout'    => $stdout,
            ];
        }

        $actualOutput = '';

        if (isset($judgeResult['stdout']) && $judgeResult['stdout']) {
            $actualOutput = trim($this->safeDecode($judgeResult['stdout']));
        }

        if ($actualOutput === "True" || $actualOutput === "False") {
            $actualOutput = strtolower($actualOutput) === "true";
        }

        $expectedOutput = $testCase['expected_output'];

        // Parse and compare outputs
        $passed = $this->compareOutputs($actualOutput, $expectedOutput);

        return [
            'test_case'       => $index,
            'passed'          => $passed,
            'input'           => $testCase['input'],
            'actual_output'   => $actualOutput,
            'expected_output' => $expectedOutput,
            'execution_time'  => $judgeResult['time'] ?? null,
            'memory_usage'    => $judgeResult['memory'] ?? null,
        ];
    }

    /**
     * Safely decode base64 content
     */
    private function safeBase64Decode(?string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        $decoded = base64_decode($content, true);

        if ($decoded === false) {
            return $content; // Return original if not valid base64
        }

        // Ensure UTF-8 encoding
        if (! mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = mb_convert_encoding($decoded, 'UTF-8', mb_detect_encoding($decoded) ?: 'UTF-8');
        }

        return $decoded;
    }

    /**
     * Compare actual vs expected outputs
     */
    private function compareOutputs($actual, $expected): bool
    {
        // Parse both outputs
        $actualParsed   = $this->tryParseOutput($this->cleanText($actual));
        $expectedParsed = $this->tryParseOutput($this->cleanText($expected));

        return $this->deepEquals($actualParsed, $expectedParsed);
    }

    /**
     * Try to parse output as JSON, fallback to string
     */
    private function tryParseOutput($output)
    {
        if ($output === null || $output === '') {
            return null;
        }

        // Already proper types
        if (is_bool($output) || is_array($output) || is_int($output) || is_float($output)) {
            return $output;
        }

        // Normalize string
        $cleaned = trim((string) $output);
        $lower   = strtolower($cleaned);

        // Python / JSON style booleans and nulls
        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if ($lower === 'none' || $lower === 'null') {
            return null;
        }

        // Numeric?
        if (is_numeric($cleaned)) {
            return strpos($cleaned, '.') !== false ? (float) $cleaned : (int) $cleaned;
        }

        // Looks like JSON?
        if (
            ($cleaned[0] === '{' && substr($cleaned, -1) === '}') ||
            ($cleaned[0] === '[' && substr($cleaned, -1) === ']')
        ) {
            $decoded = json_decode($cleaned, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Remove wrapping quotes
        if (preg_match('/^["\'](.*)["\']$/', $cleaned, $matches)) {
            return $matches[1];
        }

        return $cleaned;
    }

    /**
     * Deep comparison of two values
     */
    private function deepEquals($a, $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }
        if ($a === null || $b === null) {
            return false;
        }

        $typeA = gettype($a);
        $typeB = gettype($b);

        if (($typeA === 'integer' || $typeA === 'double') && ($typeB === 'integer' || $typeB === 'double')) {
            return abs($a - $b) < 1e-9;
        }

        if ($typeA !== $typeB) {
            return false;
        }

        if (is_array($a)) {
            if (count($a) !== count($b)) {
                return false;
            }

            $keysA = array_keys($a);
            $keysB = array_keys($b);

            if ($keysA !== $keysB) {
                return false;
            }

            foreach ($a as $key => $value) {
                if (! array_key_exists($key, $b) || ! $this->deepEquals($value, $b[$key])) {
                    return false;
                }
            }

            return true;
        }

        return $a === $b;
    }

    /**
     * Create error result
     */
    private function createErrorResult(int $index, string $error): array
    {
        return [
            'test_case'       => $index,
            'passed'          => false,
            'error'           => $error,
            'actual_output'   => null,
            'expected_output' => null,
        ];
    }

    public function testConnection(): array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];

            if ($this->judge0ApiKey) {
                $headers['X-RapidAPI-Key'] = $this->judge0ApiKey;
            }

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get("{$this->judge0Url}/system_info");

            if ($response->successful()) {
                return [
                    'success'     => true,
                    'system_info' => $response->json(),
                    'url'         => $this->judge0Url,
                ];
            } else {
                return [
                    'success'  => false,
                    'error'    => 'System info endpoint failed',
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'url'     => $this->judge0Url,
            ];
        }
    }
    private function safeDecode(?string $output): ?string
    {
        if (empty($output)) {
            return null;
        }

        $decoded = base64_decode($output, true);

        if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
            return $decoded;
        }

        if (! mb_check_encoding($output, 'UTF-8')) {
            return mb_convert_encoding($output, 'UTF-8', mb_detect_encoding($output));
        }

        return $output;
    }

    /**
     * Get hidden data structure definitions (ListNode, TreeNode, etc.)
     */
    private function getHiddenDefinitions(string $language): string
    {
        $language = strtolower($language);

        switch ($language) {
            case 'python':
                return <<<PYTHON
class ListNode:
    def __init__(self, val=0, next=None):
        self.val = val
        self.next = next
    def __repr__(self):
        return f"ListNode({self.val})"

class TreeNode:
    def __init__(self, val=0, left=None, right=None):
        self.val = val
        self.left = left
        self.right = right

# Helper to convert List to LinkedList (for local testing if needed)
def list_to_ll(arr):
    dummy = ListNode(0)
    curr = dummy
    for val in arr:
        curr.next = ListNode(val)
        curr = curr.next
    return dummy.next
PYTHON;

            case 'javascript':
            case 'node.js':
                return <<<JAVASCRIPT
class ListNode {
    constructor(val = 0, next = null) {
        this.val = val;
        this.next = next;
    }
}

class TreeNode {
    constructor(val = 0, left = null, right = null) {
        this.val = val;
        this.left = left;
        this.right = right;
    }
}

function arrayToListNode(arr) {
    if (!arr || arr.length === 0) return null;
    let dummy = new ListNode(0);
    let curr = dummy;
    for (let val of arr) {
        curr.next = new ListNode(val);
        curr = curr.next;
    }
    return dummy.next;
}
JAVASCRIPT;

            case 'java':
                // For Java, these must be non-public classes or static inner classes.
                // We will append these OUTSIDE the Main/Solution class.
                return <<<JAVA
class ListNode {
    int val;
    ListNode next;
    ListNode() {}
    ListNode(int val) { this.val = val; }
    ListNode(int val, ListNode next) { this.val = val; this.next = next; }
    @Override
    public String toString() { return "ListNode(" + val + ")"; }

    public static ListNode fromArray(Object[] arr) {
        if (arr == null || arr.length == 0) return null;
        ListNode dummy = new ListNode(0);
        ListNode curr = dummy;
        for (Object val : arr) {
            if (val instanceof Integer) {
                curr.next = new ListNode((int)val);
                curr = curr.next;
            }
        }
        return dummy.next;
    }
}

class TreeNode {
    int val;
    TreeNode left;
    TreeNode right;
    TreeNode() {}
    TreeNode(int val) { this.val = val; }
    TreeNode(int val, TreeNode left, TreeNode right) {
        this.val = val;
        this.left = left;
        this.right = right;
    }
}
JAVA;

            default:
                return '';
        }
    }
}
