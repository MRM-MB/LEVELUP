<?php

namespace App\Http\Controllers;

use App\Models\HealthCycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HealthCycleController extends Controller
{
    /**
     * Calculate health score based on LINAK 20:10 algorithm
     * 
     * @param int $sit - sitting time in minutes
     * @param int $stand - standing time in minutes
     * @return int - score from 0 to 100
     */
    /**
     * Reset daily points if user's date changed (using timezone-aware date)
     * IMPORTANT: This must match the User model's resetDailyPointsIfNeeded() to avoid double resets
     */
    private function resetDailyPointsForUserDate($user, $userDate)
    {
        // Check if this is a new day for the user using the SAME field as User model
        $lastResetDate = $user->last_points_date ? 
            \Carbon\Carbon::parse($user->last_points_date)->toDateString() : null;
            
        if ($lastResetDate !== $userDate) {
            // Reset daily points for new day
            $user->daily_points = 0;
            $user->last_points_date = $userDate;
            $user->last_daily_reset = $userDate; // Keep both in sync for backward compatibility
            $user->save();
        }
    }

    private function calculateHealthScore($sittingMinutes, $standingMinutes)
    {
        // Safety: avoid division errors
        if ($standingMinutes <= 0 || $sittingMinutes <= 0) {
            return 0;
        }

        // Minimum cycle time check (15 minutes total)
        // Prevents gaming the system with tiny cycles
        $minCycleTime = 15; // minutes
        $total = $sittingMinutes + $standingMinutes;
        
        if ($total < $minCycleTime) {
            return 0; // Cycle too short, no points
        }

        // Step 1: Calculate ratio accuracy
        // Ideal ratio = 2 (20 min sitting / 10 min standing)
        $idealRatio = 2.0;
        $userRatio = $sittingMinutes / $standingMinutes;

        // The closer the ratio is to 2, the higher the score (0–1)
        $ratioScore = max(0, 1 - abs($userRatio - $idealRatio) / $idealRatio);

        // Step 2: Check total duration balance
        // Ideal total duration ~30 minutes (20 + 10)
        $durationScore = max(0, 1 - abs($total - 30) / 20);
        // → If user works 25–35 min total, full points.
        // → Drops slowly if cycle is much shorter or longer.

        // Step 3: Weighted final score
        // Ratio accuracy is more important (70%), total time (30%)
        $score = ($ratioScore * 0.7 + $durationScore * 0.3) * 100;

        return round($score);
    }

    /**
     * Convert health score to points
     * 
     * @param int $healthScore
     * @return array - ['points' => int, 'feedback' => string, 'color' => string]
     */
    private function scoreToPoints($healthScore)
    {
        if ($healthScore >= 90) {
            return [
                'points' => 10,
                'feedback' => '🟢 Perfect! Excellent sit-stand balance.',
                'color' => 'green'
            ];
        } elseif ($healthScore >= 70) {
            return [
                'points' => 7,
                'feedback' => '🟡 Good, keep this rhythm going.',
                'color' => 'yellow'
            ];
        } elseif ($healthScore >= 50) {
            return [
                'points' => 4,
                'feedback' => '🟠 Fair, try adjusting your times a bit.',
                'color' => 'orange'
            ];
        } else {
            return [
                'points' => 0,
                'feedback' => '🔴 Too much sitting or too short, no points this cycle.',
                'color' => 'red'
            ];
        }
    }

    /**
     * Complete a health cycle and award points
     */
    public function completeHealthCycle(Request $request)
    {
        $request->validate([
            'sitting_minutes' => 'required|integer|min:1',
            'standing_minutes' => 'required|integer|min:1',
            'cycle_number' => 'required|integer|min:1',
            'user_date' => 'nullable|string|date_format:Y-m-d',
        ]);

        $user = Auth::user();
        
        // If no user is logged in, use the test user for development
        if (!$user) {
            $user = \App\Models\User::where('email', 'test@example.com')->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'requires_auth' => true
                ], 401);
            }
        }
        
        // Get user's timezone date (from frontend) or fallback to server date
        $userDate = $request->get('user_date', now()->toDateString());
        
        // Trigger daily reset using user's timezone date
        $this->resetDailyPointsForUserDate($user, $userDate);
        
        // Calculate health score
        $healthScore = $this->calculateHealthScore(
            $request->sitting_minutes,
            $request->standing_minutes
        );

        // Convert to points and get feedback
        $result = $this->scoreToPoints($healthScore);
        $pointsEarned = $result['points'];

        // Check if user can earn points today (use cached daily_points for consistency)
        $actualPointsEarned = 0;
        $dailyLimitReached = !$user->canEarnPoints($userDate);
        
        if (!$dailyLimitReached) {
            // Add points to user (respecting daily limit), passing userDate for consistency
            $actualPointsEarned = $user->addPoints($pointsEarned, $userDate);
        }

        // Save the health cycle REGARDLESS of whether points are awarded
        // Use user's timezone date for the completed_at timestamp
        $userDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $userDate . ' ' . now()->format('H:i:s'));
        
        $healthCycle = HealthCycle::create([
            'user_id' => $user->getKey(),
            'sitting_minutes' => $request->sitting_minutes,
            'standing_minutes' => $request->standing_minutes,
            'cycle_number' => $request->cycle_number,
            'health_score' => $healthScore,
            'points_earned' => $actualPointsEarned, // 0 if daily limit reached
            'completed_at' => $userDateTime,
        ]);

        // Get today's cycle count using user's timezone date
        $todaysCycles = $user->healthCycles()
            ->whereDate('completed_at', $userDate)
            ->count();

        if ($dailyLimitReached) {
            return response()->json([
                'success' => false,
                'message' => 'Daily limit reached! You\'ve earned 160 points today. Come back tomorrow!',
                'health_score' => $healthScore,
                'points_earned' => 0,
                'daily_points' => 160, // Use the limit, not database sum
                'total_points' => $user->total_points,
                'todays_cycles' => $todaysCycles, // This will now be incremented!
                'feedback' => 'Daily limit reached (160 points)',
                'color' => 'blue',
                'user_date' => $userDate, // Include for debugging
            ]);
        }

        // Check if user hit the daily limit with this cycle
        $message = $actualPointsEarned < $pointsEarned 
            ? "You earned {$actualPointsEarned} points (daily limit reached!)" 
            : "You earned {$actualPointsEarned} points!";

        // Use the user's cached daily_points (which respects the 100 limit)
        // This ensures consistency and prevents showing >100 points
        $dailyPoints = $user->daily_points;

        return response()->json([
            'success' => true,
            'message' => $message,
            'health_score' => $healthScore,
            'points_earned' => $actualPointsEarned,
            'daily_points' => $dailyPoints,
            'total_points' => $user->total_points,
            'todays_cycles' => $todaysCycles,
            'feedback' => $result['feedback'],
            'color' => $result['color'],
            'daily_limit_reached' => $dailyPoints >= 160,
            'user_date' => $userDate, // Include for debugging
        ]);
    }

    /**
     * Get user's points and daily status
     */
    public function getPointsStatus(Request $request)
    {
        $user = Auth::user();
        
        // If no user logged in, use test user for development
        if (!$user) {
            $user = \App\Models\User::where('email', 'test@example.com')->first();
            
            if (!$user) {
                return response()->json([
                    'total_points' => 0,
                    'daily_points' => 0,
                    'can_earn_more' => false,
                    'points_remaining_today' => 0,
                    'message' => 'Test user not found',
                ]);
            }
        }
        
        // Get user's timezone date (from frontend) or fallback to server date
        $userDate = $request->get('user_date', now()->toDateString());
        
        // Trigger daily reset using user's timezone date
        $this->resetDailyPointsForUserDate($user, $userDate);
        
        // Use cached daily_points for consistency (respects 100 limit)
        $dailyPoints = $user->daily_points;

        // Get today's cycle count from database using user's date
        $todaysCycles = $user->healthCycles()
            ->whereDate('completed_at', $userDate)
            ->count();

        return response()->json([
            'total_points' => $user->total_points,
            'daily_points' => $dailyPoints,
            'todays_cycles' => $todaysCycles,
            'can_earn_more' => $dailyPoints < 100,
            'points_remaining_today' => max(0, 100 - $dailyPoints),
            'user_date' => $userDate, // Include for debugging
        ]);
    }

    /**
     * Get user's health cycle history
     */
    public function getHistory(Request $request)
    {
        $user = Auth::user();
        
        // If no user logged in, return empty
        if (!$user) {
            return response()->json([
                'cycles' => [],
            ]);
        }
        
        $limit = $request->input('limit', 10);

        $cycles = $user->healthCycles()
            ->orderBy('completed_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'cycles' => $cycles,
        ]);
    }
}
