<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use App\Models\UserActivityProgress;
use App\Models\UserCourseProgress;
use Illuminate\Support\Collection;
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
        // 1. Basic Progress Statistics
        $stats = $this->progressService->getCourseStatistics($student, $course);

        // 2. Detailed Activity Performance
        $activityPerformance = $this->getActivityPerformance($student, $course);

        // 3. Time Analysis
        $timeAnalysis = $this->getTimeAnalysis($student, $course);

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
                'activities_completed' => $stats['completed_content'] - $stats['total_lessons'], // Approximation if lessons are tracked separately
                'total_activities' => $stats['total_activities'],
                'completion_rate' => $stats['progress_percentage'] . '%',
            ],
            'time_analysis' => $timeAnalysis,
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
        
        $studentReports = [];

        foreach ($students as $studentData) {
            // getEnrolledStudents returns an array of data, we need the User model
            // But wait, getEnrolledStudents returns a collection of arrays.
            // I should probably fetch the User models directly or adjust how I use the data.
            // Let's fetch the user model for accurate service usage.
            $student = User::find($studentData['student']['id']);
            
            if (!$student) continue;

            $report = $this->generateStudentReport($student, $course);
            $studentReports[] = $report;

            $progress = $report['progress_summary']['progress_percentage'];
            $totalProgress += $progress;

            if ($progress > 0) {
                $activeStudents++;
            }

            if ($report['progress_summary']['is_completed']) {
                $completedStudents++;
            }
        }

        $averageProgress = $totalStudents > 0 ? round($totalProgress / $totalStudents, 2) : 0;

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
            ],
            'student_performance' => $studentReports,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function getActivityPerformance(User $user, Course $course): array
    {
        // This could be expanded to include quiz scores, coding challenge results, etc.
        // For now, we'll rely on completion status from the progress service.
        // If we had a Score model, we would query it here.
        return [];
    }

    private function getTimeAnalysis(User $user, Course $course): array
    {
        // Calculate time spent based on UserActivityProgress
        // This assumes we track time spent. If not, we can calculate duration between started_at and completed_at
        
        $activities = UserActivityProgress::where('user_id', $user->id)
            ->whereHas('activity', function($q) use ($course) {
                $q->whereHas('module', function($q) use ($course) {
                    $q->where('course_id', $course->id);
                });
            })
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        $totalSeconds = 0;
        foreach ($activities as $activity) {
            $start = \Carbon\Carbon::parse($activity->started_at);
            $end = \Carbon\Carbon::parse($activity->completed_at);
            $totalSeconds += $end->diffInSeconds($start);
        }

        // Format duration
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds / 60) % 60);

        return [
            'total_time_spent' => "{$hours}h {$minutes}m",
        ];
    }
}
