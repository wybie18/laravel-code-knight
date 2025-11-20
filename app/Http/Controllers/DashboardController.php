<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\CodingChallenge;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CtfChallenge;
use App\Models\TypingChallenge;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->tokenCan('admin:*')) {
            return $this->getAdminStats();
        }
        
        // Check if user is a teacher (has permission to create courses or tests)
        if ($user->role->name === 'teacher') {
             return $this->getTeacherStats($user);
        }

        return response()->json(['message' => 'Dashboard not available for this user role'], 403);
    }

    private function getAdminStats()
    {
        // 1. Stats Data (Last 6 months)
        $statsData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $usersCount = User::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            $enrollmentsCount = CourseEnrollment::whereBetween('created_at', [$monthStart, $monthEnd])->count();

            $statsData[] = [
                'name' => $monthName,
                'users' => $usersCount,
                'enrollments' => $enrollmentsCount,
            ];
        }

        // Calculate Changes for Cards (Admin)
        $sixMonthsAgo = Carbon::now()->subMonths(6);
        $twelveMonthsAgo = Carbon::now()->subMonths(12);
        
        $currentUsers6mo = User::where('created_at', '>=', $sixMonthsAgo)->count();
        $prevUsers6mo = User::whereBetween('created_at', [$twelveMonthsAgo, $sixMonthsAgo])->count();
        $usersChange = $this->calculateChange($currentUsers6mo, $prevUsers6mo);

        $currentEnrollments6mo = CourseEnrollment::where('created_at', '>=', $sixMonthsAgo)->count();
        $prevEnrollments6mo = CourseEnrollment::whereBetween('created_at', [$twelveMonthsAgo, $sixMonthsAgo])->count();
        $enrollmentsChange = $this->calculateChange($currentEnrollments6mo, $prevEnrollments6mo);

        $totalChallengesCount = Challenge::count();
        $prevTotalChallenges = Challenge::where('created_at', '<', Carbon::now()->subMonth())->count();
        $challengesChange = $this->calculateChange($totalChallengesCount, $prevTotalChallenges);

        $activeCourses = Course::where('is_published', true)->count();
        $prevActiveCourses = Course::where('is_published', true)->where('created_at', '<', Carbon::now()->subMonth())->count();
        $activeCoursesChange = $this->calculateChange($activeCourses, $prevActiveCourses);

        // 2. Challenge Types Data
        $codingCount = Challenge::where('challengeable_type', CodingChallenge::class)->count();
        $typingCount = Challenge::where('challengeable_type', TypingChallenge::class)->count();
        $ctfCount = Challenge::where('challengeable_type', CtfChallenge::class)->count();
        
        $totalChallenges = $codingCount + $typingCount + $ctfCount;

        $challengeTypesData = [
            ['name' => 'Coding', 'count' => $codingCount],
            ['name' => 'Typing Test', 'count' => $typingCount],
            ['name' => 'CTF', 'count' => $ctfCount],
        ];

        // 3. Challenge Data (Percentages)
        $challengeData = [
            ['name' => 'Coding', 'value' => $totalChallenges > 0 ? round(($codingCount / $totalChallenges) * 100, 1) : 0],
            ['name' => 'Typing Test', 'value' => $totalChallenges > 0 ? round(($typingCount / $totalChallenges) * 100, 1) : 0],
            ['name' => 'CTF', 'value' => $totalChallenges > 0 ? round(($ctfCount / $totalChallenges) * 100, 1) : 0],
        ];

        // 4. Recent Users
        $recentUsers = User::select('id', 'first_name', 'last_name', 'email', 'avatar', 'created_at')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'created_at' => $user->created_at->format('Y-m-d'),
                ];
            });

        return response()->json([
            'statsData' => $statsData,
            'challengeTypesData' => $challengeTypesData,
            'challengeData' => $challengeData,
            'recentUsers' => $recentUsers,
            'statCards' => [
                'usersChange' => $usersChange,
                'enrollmentsChange' => $enrollmentsChange,
                'challengesChange' => $challengesChange,
                'activeCourses' => $activeCourses,
                'activeCoursesChange' => $activeCoursesChange,
            ]
        ]);
    }

    private function getTeacherStats($user)
    {
        $courseIds = Course::where('created_by', $user->id)->pluck('id');

        // 1. Stats Data (Last 6 months)
        $statsData = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $enrollmentsQuery = CourseEnrollment::whereIn('course_id', $courseIds)
                ->whereBetween('created_at', [$monthStart, $monthEnd]);
            
            $enrollmentsCount = $enrollmentsQuery->count();
            // Users here represents unique students enrolled in this month
            $usersCount = $enrollmentsQuery->distinct('user_id')->count('user_id');

            $statsData[] = [
                'name' => $monthName,
                'users' => $usersCount,
                'enrollments' => $enrollmentsCount,
            ];
        }

        // Calculate Changes for Cards (Teacher)
        $sixMonthsAgo = Carbon::now()->subMonths(6);
        $twelveMonthsAgo = Carbon::now()->subMonths(12);

        // Users Change (Unique students in my courses)
        $currentUsers6mo = CourseEnrollment::whereIn('course_id', $courseIds)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->distinct('user_id')
            ->count('user_id');
        $prevUsers6mo = CourseEnrollment::whereIn('course_id', $courseIds)
            ->whereBetween('created_at', [$twelveMonthsAgo, $sixMonthsAgo])
            ->distinct('user_id')
            ->count('user_id');
        $usersChange = $this->calculateChange($currentUsers6mo, $prevUsers6mo);

        // Enrollments Change
        $currentEnrollments6mo = CourseEnrollment::whereIn('course_id', $courseIds)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->count();
        $prevEnrollments6mo = CourseEnrollment::whereIn('course_id', $courseIds)
            ->whereBetween('created_at', [$twelveMonthsAgo, $sixMonthsAgo])
            ->count();
        $enrollmentsChange = $this->calculateChange($currentEnrollments6mo, $prevEnrollments6mo);

        // Challenges Change
        $totalChallengesCount = Challenge::where('created_by', $user->id)->count();
        $prevTotalChallenges = Challenge::where('created_by', $user->id)
            ->where('created_at', '<', Carbon::now()->subMonth())
            ->count();
        $challengesChange = $this->calculateChange($totalChallengesCount, $prevTotalChallenges);

        // Active Courses Change
        $activeCourses = Course::where('created_by', $user->id)->where('is_published', true)->count();
        $prevActiveCourses = Course::where('created_by', $user->id)
            ->where('is_published', true)
            ->where('created_at', '<', Carbon::now()->subMonth())
            ->count();
        $activeCoursesChange = $this->calculateChange($activeCourses, $prevActiveCourses);

        // 2. Challenge Types Data (Challenges created by teacher)
        $codingCount = Challenge::where('created_by', $user->id)->where('challengeable_type', CodingChallenge::class)->count();
        $typingCount = Challenge::where('created_by', $user->id)->where('challengeable_type', TypingChallenge::class)->count();
        $ctfCount = Challenge::where('created_by', $user->id)->where('challengeable_type', CtfChallenge::class)->count();
        
        $totalChallenges = $codingCount + $typingCount + $ctfCount;

        $challengeTypesData = [
            ['name' => 'Coding', 'count' => $codingCount],
            ['name' => 'Typing Test', 'count' => $typingCount],
            ['name' => 'CTF', 'count' => $ctfCount],
        ];

        // 3. Challenge Data (Percentages)
        $challengeData = [
            ['name' => 'Coding', 'value' => $totalChallenges > 0 ? round(($codingCount / $totalChallenges) * 100, 1) : 0],
            ['name' => 'Typing Test', 'value' => $totalChallenges > 0 ? round(($typingCount / $totalChallenges) * 100, 1) : 0],
            ['name' => 'CTF', 'value' => $totalChallenges > 0 ? round(($ctfCount / $totalChallenges) * 100, 1) : 0],
        ];

        // 4. Recent Users (Students recently enrolled in my courses)
        $recentEnrollments = CourseEnrollment::whereIn('course_id', $courseIds)
            ->with('user')
            ->latest()
            ->take(5)
            ->get();
            
        $recentUsers = $recentEnrollments->map(function ($enrollment) {
            $student = $enrollment->user;
            return [
                'id' => $student->id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'avatar' => $student->avatar,
                'email' => $student->email,
                'created_at' => $enrollment->created_at->format('Y-m-d'), 
            ];
        });

        return response()->json([
            'statsData' => $statsData,
            'challengeTypesData' => $challengeTypesData,
            'challengeData' => $challengeData,
            'recentUsers' => $recentUsers,
            'statCards' => [
                'usersChange' => $usersChange,
                'enrollmentsChange' => $enrollmentsChange,
                'challengesChange' => $challengesChange,
                'activeCourses' => $activeCourses,
                'activeCoursesChange' => $activeCoursesChange,
            ]
        ]);
    }

    private function calculateChange($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
