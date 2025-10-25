<?php
namespace App\Http\Controllers;

use App\Services\UserActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserActivityController extends Controller
{
    public function getHeatmap(Request $request)
    {
        $activityService = app(UserActivityService::class);
        $user            = Auth::user();

        $activities = $activityService->getHeatmapData($user, 365);
        $stats      = $activityService->getOverallStats($user);

        return response()->json([
            'activities' => $activities,
            'stats'      => $stats,
        ]);
    }
}
