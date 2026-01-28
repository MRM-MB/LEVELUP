<?php

namespace App\Http\Controllers;

use App\Models\HealthCycle;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class StatisticsController extends BaseController
{
    // Special method of PHP
    public function __construct()
    {
        $this->middleware("auth");
    }
    public function statistics()
    {

        // Get user Id
        $userId = auth()->id();

        // Last 7 days of data for Bar Chart
        $healthCycle = HealthCycle::where('user_id', $userId)
            ->whereDate('completed_at', now())
            ->first();

        // Totals for Pie Chart
        $totalSitting = HealthCycle::where('user_id', $userId)->sum('sitting_minutes');
        $totalStanding = HealthCycle::where('user_id', $userId)->sum('standing_minutes');
  
        
        return view('statistics', compact('healthCycle', 'totalSitting', 'totalStanding'));
    }

    public function getTodayStats(Request $request)
    {
        $user = auth()->user();
        
        // If no user logged in, return empty
        if (!$user) {
            return response()->json([
                'sitting_minutes' => 0,
                'standing_minutes' => 0,
                'total_minutes' => 0,
            ]);
        }
        
        // Get user's timezone date (from frontend) or fallback to server date
        $userDate = $request->get('user_date', now()->toDateString());
        
        // Sum all health cycles completed today
        $todayStats = $user->healthCycles()
            ->whereDate('completed_at', $userDate)
            ->selectRaw('SUM(sitting_minutes) as sitting_minutes, SUM(standing_minutes) as standing_minutes')
            ->first();

        return response()->json([
            'sitting_minutes' => $todayStats->sitting_minutes ?? 0,
            'standing_minutes' => $todayStats->standing_minutes ?? 0,
            'total_minutes' => ($todayStats->sitting_minutes ?? 0) + ($todayStats->standing_minutes ?? 0),
        ]);
    }

    public function getAllTimeStats(Request $request)
    {
        $user = auth()->user();
        
        // If no user logged in, return empty
        if (!$user) {
            return response()->json([
                'sitting_minutes' => 0,
                'standing_minutes' => 0,
                'total_minutes' => 0,
            ]);
        }
        
        // Sum all health cycles for this user
        $allTimeStats = $user->healthCycles()
            ->selectRaw('SUM(sitting_minutes) as sitting_minutes, SUM(standing_minutes) as standing_minutes')
            ->first();

        return response()->json([
            'sitting_minutes' => $allTimeStats->sitting_minutes ?? 0,
            'standing_minutes' => $allTimeStats->standing_minutes ?? 0,
            'total_minutes' => ($allTimeStats->sitting_minutes ?? 0) + ($allTimeStats->standing_minutes ?? 0),
        ]);
    }
}
