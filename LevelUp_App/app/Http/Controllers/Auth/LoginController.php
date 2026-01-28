<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Services\PicoDisplayService;
use App\Services\Wifi2BleSimulatorClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(
        private PicoDisplayService $picoDisplayService,
        private Wifi2BleSimulatorClient $simClient,
    ) {
    }

    public function show()
    {
        return view('login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (Auth::attempt(
            ['username' => $credentials['username'], 'password' => $credentials['password']],
            $remember
        )) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Reset daily points if needed
            $user->resetDailyPointsIfNeeded();

            $this->picoDisplayService->setMessageForUser($user);

            // Auto-adjust desk to user's preferred sitting height on login
            if ($user->desk_id && $user->sitting_position) {
                $desk = Desk::find($user->desk_id);

                if ($desk && $desk->serial_number) {
                    $positionMm = $user->sitting_position * 10; // cm -> mm

                    try {
                        $this->simClient->updateDeskState($desk->serial_number, [
                            'position_mm' => $positionMm,
                        ]);

                        Log::info('Desk auto-adjusted on login', [
                            'user_id'     => $user->getKey(),
                            'desk_id'     => $desk->id,
                            'serial'      => $desk->serial_number,
                            'position_mm' => $positionMm,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to auto-adjust desk on login', [
                            'user_id' => $user->getKey(),
                            'desk_id' => $desk->id ?? null,
                            'error'   => $e->getMessage(),
                        ]);
                    }
                }
            }

            return redirect()->intended(route('home'));
        }

        throw ValidationException::withMessages([
            'username' => __('auth.failed'),
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->picoDisplayService->setDefaultMessage();

        return redirect()->route('login');
    }
}