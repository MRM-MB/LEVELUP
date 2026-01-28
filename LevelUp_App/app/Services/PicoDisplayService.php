<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PicoDisplayService
{
    private const CACHE_KEY = 'pico.display.state';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULT_STATE = [
        'message' => 'Welcome to LevelUp!',
        'username' => null,
        'logged_in' => false,
        'points' => null,
        'daily_points' => null,
        'updated_at' => null,
        'timer_phase' => null, // 'sitting' or 'standing'
        'time_remaining' => null, // seconds left
        'warning_message' => null, // 'Get ready to stand up!' or 'Get ready to sit down!'
        'is_paused' => false, // timer pause state
    ];

    /**
     * Persist a welcome message tailored to the authenticated user.
     */
    public function setMessageForUser(User $user): void
    {
        $username = $user->username ?? $user->name ?? 'User';
        $name = $user->name ?? $user->username ?? 'User';
        $message = 'Hello ' . $name . '!';

        $this->storeState([
            'message' => $message,
            'username' => $username,
            'name' => $name,
            'logged_in' => true,
            'points' => $user->total_points ?? 0,
            'daily_points' => $user->daily_points ?? 0,
            'timer_phase' => null,
            'time_remaining' => null,
            'warning_message' => null,
        ]);
    }

    /**
     * Reset the display message back to the default guest greeting.
     */
    public function setDefaultMessage(): void
    {
        $this->storeState(self::DEFAULT_STATE);
    }

    /**
     * Set the current timer phase (sitting or standing) and time remaining.
     */
    public function setTimerPhase(?string $phase, ?int $timeRemaining = null): void
    {
        $currentState = $this->getState();
        $currentState['timer_phase'] = $phase;
        $currentState['time_remaining'] = $timeRemaining;

        Log::info('PicoDisplay: timer phase updated', [
            'phase' => $phase,
            'time_remaining' => $timeRemaining,
        ]);
        
        // Generate warning message if within 30 seconds of end
        $warningMessage = null;
        if ($phase && $timeRemaining !== null && $timeRemaining <= 30 && $timeRemaining > 0) {
            if ($phase === 'sitting') {
                $warningMessage = 'Get ready to stand up!';
            } else {
                $warningMessage = 'Get ready to sit down!';
            }
        }
        $currentState['warning_message'] = $warningMessage;
        
        $this->storeState($currentState);
    }

    /**
     * Set the timer pause state.
     */
    public function setTimerPaused(bool $paused): void
    {
        $currentState = $this->getState();
        $currentState['is_paused'] = $paused;

        Log::info('PicoDisplay: timer pause toggled', [
            'paused' => $paused,
        ]);
        $this->storeState($currentState);
    }

    /**
     * Fetch the current display state for the Pico W.
     *
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        $state = Cache::get(self::CACHE_KEY);

        if (!is_array($state)) {
            $this->setDefaultMessage();
            $state = Cache::get(self::CACHE_KEY, self::DEFAULT_STATE);
        }

        return $state;
    }

    /**
     * Merge provided values and persist them to cache.
     *
     * @param array<string, mixed> $values
     */
    private function storeState(array $values): void
    {
        // Get current state first, then merge with defaults, then apply new values
        // This preserves existing state values that aren't being updated
        $currentState = Cache::get(self::CACHE_KEY, self::DEFAULT_STATE);
        $state = array_merge(self::DEFAULT_STATE, $currentState, $values);
        $state['updated_at'] = Carbon::now()->toIso8601String();

        Cache::forever(self::CACHE_KEY, $state);
    }
}
