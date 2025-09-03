<?php
namespace App\Services;

use App\Models\CodingChallenge;
use App\Models\ProgrammingLanguage;
use Illuminate\Support\Facades\Http;

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
     * Run all test cases for a coding challenge
     */
    public function runTestCases(
        CodingChallenge $codingChallenge,
        ProgrammingLanguage $language,
        string $userCode,
        ?int $numTestsToRun = null
    ): array {
        $testCases = json_decode($codingChallenge->test_cases, true);
        $results   = [];

        if ($numTestsToRun !== null) {
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
        $publicResults = array_slice($results, 0, $public_cases_to_show);
        $allPassed = collect($results)->every('passed');

        if (!$allPassed && count($results) > $public_cases_to_show) {
            $firstFailedResult = collect($results)->firstWhere('passed', false);
            $firstFailedIndex = array_search($firstFailedResult, $results);

            if ($firstFailedIndex >= $public_cases_to_show) {
                $publicResults = $publicResults;
                $publicResults[] = [
                    'test_case' => 'Hidden Test',
                    'passed' => false,
                    'error' => 'One or more hidden test cases failed.',
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
        int $index
    ): array {
        try {
            $cleanUserCode = $this->cleanCode($userCode);
            
            $completeCode = $this->prepareCodeWithTestCase($cleanUserCode, $language, $testCase);

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
    private function cleanCode(string $code): string
    {
        // Remove BOM if present
        $code = str_replace("\xEF\xBB\xBF", '', $code);
        
        // Normalize line endings to \n
        $code = str_replace(["\r\n", "\r"], "\n", $code);
        
        // Remove any null bytes or other problematic characters
        $code = str_replace("\0", '', $code);
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($code, 'UTF-8')) {
            $code = mb_convert_encoding($code, 'UTF-8', mb_detect_encoding($code));
        }
        
        $code = trim($code);
        
        return $code;
    }

    /**
     * Prepare code with test case input based on language
     */
    private function prepareCodeWithTestCase(
        string $userCode,
        ProgrammingLanguage $language,
        array $testCase
    ): string {
        $input        = $testCase['input'];
        $languageName = strtolower($language->name);

        switch ($languageName) {
            case 'python':
                return $this->preparePythonCode($userCode, $input);

            case 'javascript':
            case 'node.js':
                return $this->prepareJavaScriptCode($userCode, $input);

            case 'java':
                return $this->prepareJavaCode($userCode, $input);

            default:
                throw new \Exception("Unsupported language: {$language->name}");
        }
    }

    /**
     * Prepare Python code with test input
     */
    private function preparePythonCode(string $userCode, array $input): string
    {
        $inputValues = $this->formatInputForPython($input);

        $testCode = <<<PYTHON
{$userCode}

# Test execution
{$inputValues}
try:
    solution = Solution()
    result = solution.main({$this->getPythonArguments($input)})
    print(repr(result))
except Exception as e:
    print(f"Runtime Error: {e}")
    import sys
    sys.exit(1)
PYTHON;

        return $testCode;
    }

    /**
     * Prepare JavaScript code with test input
     */
    private function prepareJavaScriptCode(string $userCode, array $input): string
    {
        $inputValues = $this->formatInputForJavaScript($input);

        $testCode = <<<JAVASCRIPT
{$userCode}

// Test execution
{$inputValues}
try {
    const solution = new Solution();
    const result = solution.main({$this->getJavaScriptArguments($input)});
    console.log(JSON.stringify(result));
} catch (error) {
    console.error('Runtime Error:', error.message);
    process.exit(1);
}
JAVASCRIPT;

        return $testCode;
    }

    /**
     * Prepare Java code with test input
     */
    private function prepareJavaCode(string $userCode, array $input): string
    {
        $inputValues = $this->formatInputForJava($input);

        $mainMethod = <<<JAVA

    public static void main(String[] args) {
        try {
            {$inputValues}
            Solution solution = new Solution();
            Object result = solution.main({$this->getJavaArguments($input)});
            System.out.println(java.util.Arrays.deepToString(new Object[]{result}));
        } catch (Exception e) {
            System.err.println("Runtime Error: " + e.getMessage());
            System.exit(1);
        }
    }
JAVA;

        // Insert main method into the class
        $lastBrace = strrpos($userCode, '}');
        if ($lastBrace !== false) {
            return substr($userCode, 0, $lastBrace) . $mainMethod . "\n}";
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
    private function formatInputForPython(array $input): string
    {
        $assignments = [];
        foreach ($input as $key => $value) {
            $assignments[] = "{$key} = " . $this->pythonValue($value);
        }
        return implode("\n", $assignments);
    }

    private function formatInputForJavaScript(array $input): string
    {
        $assignments = [];
        foreach ($input as $key => $value) {
            $assignments[] = "const {$key} = " . json_encode($value, JSON_UNESCAPED_UNICODE) . ";";
        }
        return implode("\n", $assignments);
    }

    private function formatInputForJava(array $input): string
    {
        $assignments = [];
        foreach ($input as $key => $value) {
            $type          = $this->getJavaType($value);
            $javaValue     = $this->javaValue($value);
            $assignments[] = "            {$type} {$key} = {$javaValue};";
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
            'Accept' => 'application/json'
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
     * Get submission result from Judge0
     */
    private function getSubmissionResult(string $token): ?array
    {
        $maxAttempts = 15; // Increased attempts
        $attempt     = 0;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
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
        if (!mb_check_encoding($decoded, 'UTF-8')) {
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
        $actualParsed = $this->tryParseOutput($actual);
        $expectedParsed = $this->tryParseOutput($expected);

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

        if (is_bool($output)) {
            return $output;
        }

        if (is_numeric($output) && !is_string($output)) {
            return $output;
        }

        if (is_array($output)) {
            return $output;
        }

        $cleaned = trim($output);
        
        if ($cleaned === 'True') {
            return true;
        }
        if ($cleaned === 'False') {
            return false;
        }
        
        if ($cleaned === 'None') {
            return null;
        }
        
        if ($cleaned === 'null') {
            return null;
        }

        $cleaned = str_replace("'", '"', $cleaned);
        $decoded = json_decode($cleaned, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (is_numeric($cleaned)) {
            return strpos($cleaned, '.') !== false ? (float) $cleaned : (int) $cleaned;
        }

        if (preg_match('/^["\'](.*)["\']\s*$/', $cleaned, $matches)) {
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
                if (!array_key_exists($key, $b) || !$this->deepEquals($value, $b[$key])) {
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
}