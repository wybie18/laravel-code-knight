<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Test;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CourseReportService
{
    protected $progressService;
    protected $enrollmentService;

    public function __construct(
        CourseProgressService $progressService,
        CourseEnrollmentService $enrollmentService
    ) {
        $this->progressService = $progressService;
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Generate a performance report for a specific student in a course.
     */
    public function generateStudentReport(User $student, Course $course): array
    {
        $stats = $this->progressService->getCourseStatistics($student, $course);
        $activityPerformance = $this->getActivityPerformance($student, $course);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'email' => $student->email,
                'student_id' => $student->student_id,
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'code' => $course->course_code,
            ],
            'progress_summary' => $stats,
            'performance_metrics' => [
                'activities_completed' => $stats['completed_content'] ?? 0,
                'total_activities' => $stats['total_activities'] ?? 0,
                'completion_rate' => ($stats['progress_percentage'] ?? 0) . '%',
                'test_performance' => $activityPerformance['tests'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate an overall performance report for all students in a course.
     */
    public function generateCourseReport(Course $course): array
    {
        $students = $this->enrollmentService->getEnrolledStudents($course, Auth::user());
        
        $totalStudents = count($students);
        $activeStudents = 0;
        $completedStudents = 0;
        $totalProgress = 0;
        
        // Test aggregation variables
        $totalTestsTaken = 0;
        $sumAverageTestScores = 0;
        $studentsWithTests = 0;

        $studentReports = [];

        foreach ($students as $studentData) {
            $studentId = $studentData['student']['id'] ?? null;
            if (!$studentId) continue;

            $student = User::find($studentId);
            if (!$student) continue;

            $report = $this->generateStudentReport($student, $course);
            $studentReports[] = $report;

            $progress = $report['progress_summary']['progress_percentage'] ?? 0;
            $totalProgress += $progress;

            if ($progress > 0) {
                $activeStudents++;
            }

            if (!empty($report['progress_summary']['is_completed'])) {
                $completedStudents++;
            }

            // Aggregate test data
            $testPerf = $report['performance_metrics']['test_performance'];
            if ($testPerf['taken'] > 0) {
                $totalTestsTaken += $testPerf['taken'];
                // Parse percentage string to float
                $avgScore = floatval(str_replace('%', '', $testPerf['average_score_percentage']));
                $sumAverageTestScores += $avgScore;
                $studentsWithTests++;
            }
        }

        $averageProgress = $totalStudents > 0 ? round($totalProgress / $totalStudents, 2) : 0;
        $averageTestScore = $studentsWithTests > 0 ? round($sumAverageTestScores / $studentsWithTests, 2) : 0;

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'code' => $course->course_code,
                'total_enrolled' => $totalStudents,
            ],
            'overall_statistics' => [
                'average_progress' => $averageProgress . '%',
                'completion_rate' => $totalStudents > 0 ? round(($completedStudents / $totalStudents) * 100, 2) . '%' : '0%',
                'active_students_count' => $activeStudents,
                'completed_students_count' => $completedStudents,
                'average_test_score' => $averageTestScore . '%',
            ],
            'student_performance' => $studentReports,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function getActivityPerformance(User $user, Course $course): array
    {
        // 1. Test Performance
        $tests = Test::where('course_id', $course->id)->get();
        $totalTests = $tests->count();
        $testsTaken = 0;
        $totalScoreObtained = 0;
        $totalMaxScorePossible = 0;
        $testDetails = [];

        foreach ($tests as $test) {
            // Get attempts for this test
            $attempts = TestAttempt::where('test_id', $test->id)
                ->where('student_id', $user->id)
                ->whereIn('status', ['submitted', 'graded'])
                ->orderBy('total_score', 'desc') // Get best score first
                ->get();

            if ($attempts->isNotEmpty()) {
                $testsTaken++;
                $bestAttempt = $attempts->first();
                
                $score = $bestAttempt->total_score;
                $maxScore = $test->total_points > 0 ? $test->total_points : 100; // Default to 100 if 0 to avoid division by zero

                $totalScoreObtained += $score;
                $totalMaxScorePossible += $maxScore;

                $testDetails[] = [
                    'test_id' => $test->id,
                    'title' => $test->title,
                    'attempts_count' => $attempts->count(),
                    'best_score' => $score,
                    'total_points' => $maxScore,
                    'score_percentage' => round(($score / $maxScore) * 100, 1) . '%',
                    'status' => 'Completed',
                    'last_attempt_at' => $bestAttempt->submitted_at ? $bestAttempt->submitted_at->toIso8601String() : null,
                ];
            } else {
                $testDetails[] = [
                    'test_id' => $test->id,
                    'title' => $test->title,
                    'attempts_count' => 0,
                    'best_score' => 0,
                    'total_points' => $test->total_points,
                    'score_percentage' => '0%',
                    'status' => 'Not Attempted',
                    'last_attempt_at' => null,
                ];
            }
        }

        // Calculate overall average score percentage for tests taken
        // If we want "how good the student is", we might want average of percentages
        $averageScorePercentage = 0;
        if ($totalMaxScorePossible > 0) {
            $averageScorePercentage = ($totalScoreObtained / $totalMaxScorePossible) * 100;
        }

        return [
            'tests' => [
                'total' => $totalTests,
                'taken' => $testsTaken,
                'completion_rate' => $totalTests > 0 ? round(($testsTaken / $totalTests) * 100, 2) . '%' : '0%',
                'average_score_percentage' => round($averageScorePercentage, 2) . '%',
                'details' => $testDetails
            ]
        ];
    }
}
