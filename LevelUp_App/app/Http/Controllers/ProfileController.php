<?php

namespace App\Http\Controllers;

use App\Services\Wifi2BleSimulatorClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(private readonly Wifi2BleSimulatorClient $client)
    {
    }

    /**
     * Display the user's profile.
     */
    public function show()
    {
        $user = Auth::user();
        $minHeight = 60;
        $maxHeight = 130;

        if ($user->desk_id) {
            try {
                $desk = $this->client->getDesk($user->desk_id);
                if (isset($desk['config']['min_position_mm'])) {
                    $minHeight = ceil($desk['config']['min_position_mm'] / 10);
                }
                if (isset($desk['config']['max_position_mm'])) {
                    $maxHeight = floor($desk['config']['max_position_mm'] / 10);
                }
            } catch (\Throwable $e) {
                // Fallback to defaults if simulator is unreachable
            }
        }
        
        return view('profile', compact('user', 'minHeight', 'maxHeight'));
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $minHeight = 60;
        $maxHeight = 130;

        if ($user->desk_id) {
            try {
                $desk = $this->client->getDesk($user->desk_id);
                if (isset($desk['config']['min_position_mm'])) {
                    $minHeight = ceil($desk['config']['min_position_mm'] / 10);
                }
                if (isset($desk['config']['max_position_mm'])) {
                    $maxHeight = floor($desk['config']['max_position_mm'] / 10);
                }
            } catch (\Throwable $e) {
                // Fallback
            }
        }
        
        $request->validate([
            'name' => 'required|string|max:100',
            'surname' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'sitting_position' => "nullable|integer|min:$minHeight|max:$maxHeight",
            'standing_position' => "nullable|integer|min:$minHeight|max:$maxHeight",
        ]);

        $user->update([
            'name' => $request->name,
            'surname' => $request->surname,
            'date_of_birth' => $request->date_of_birth,
            'sitting_position' => $request->sitting_position,
            'standing_position' => $request->standing_position,
        ]);

        return redirect()->route('profile')->with('success', 'Profile updated successfully!');
    }
}