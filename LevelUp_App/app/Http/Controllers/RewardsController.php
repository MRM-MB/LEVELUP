<?php

namespace App\Http\Controllers;

use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class RewardsController extends Controller
{
    /**
     * Display the rewards page
     */
    public function index()
    {
        $user = Auth::user();

        if (!$user) {
            $user = \App\Models\User::where('email', 'test@example.com')->first();
        }

        $rewards = Reward::where('archived', false)->get();
        $savedRewardIds = $user ? $user->favoriteRewards()->pluck('card_id')->toArray() : [];
         $redeemedRewards = $user ? $user->redeemedRewards()->orderBy('pivot_redeemed_at', 'desc')->get() : [];

        return view('rewards.rewards', [
            'rewards' => $rewards,
            'savedRewardIds' => $savedRewardIds,
            'redeemedRewards' => $redeemedRewards,
        ]);
    }

    // Redeem rewards
    public function redeem(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rewardId = $request->input('reward_id');
        $reward = Reward::find($rewardId);

        if (!$reward) {
            return response()->json(['error' => 'Reward not found'], 404);
        }

        // Check if user has enough points
        if ($user->total_points < $reward->points_amount) {
            return response()->json([
                'error' => 'Insufficient points',
                'required' => $reward->points_amount,
                'available' => $user->total_points
            ], 400);
        }

        // Deduct points
        $user->total_points -= $reward->points_amount;
        $user->save();

        // Create redemption record
        $user->redeemedRewards()->attach($rewardId, [
            'redeemed_at' => now(),
            'card_name_snapshot' => $reward->card_name,
      'points_amount_snapshot' => $reward->points_amount,
      'card_description_snapshot' => $reward->card_description,
        ]);

        return response()->json([
            'success' => true,
            'new_points' => $user->total_points,
            'reward_name' => $reward->card_name
        ]);
    }

    // Toggle save reward
    public function toggleSave(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rewardId = $request->input('reward_id');

        if ($user->favoriteRewards()->where('card_id', $rewardId)->exists()) {
            $user->favoriteRewards()->detach($rewardId);
            return response()->json(['saved' => false]);
        } else {
            $user->favoriteRewards()->attach($rewardId);
            return response()->json(['saved' => true]);
        }
    }

    // Get saved rewards
    public function getSavedRewards()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'savedRewardIds' => $user->favoriteRewards()->pluck('card_id')->toArray()
        ]);
    }
}