<?php

namespace App\Http\Controllers;

use App\Services\PicoDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PicoDisplayController extends Controller
{
    public function __construct(private PicoDisplayService $picoDisplayService)
    {
    }

    public function getState(): JsonResponse
    {
        return response()->json($this->picoDisplayService->getState());
    }

    public function updateTimerPhase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phase' => 'nullable|in:sitting,standing',
            'time_remaining' => 'nullable|integer|min:0',
        ]);

        $phase = $validated['phase'] ?? null;
        $timeRemaining = $validated['time_remaining'] ?? null;
        
        $this->picoDisplayService->setTimerPhase($phase, $timeRemaining);

        return response()->json([
            'success' => true, 
            'phase' => $phase,
            'time_remaining' => $timeRemaining
        ]);
    }

    public function toggleTimerPause(Request $request): JsonResponse
    {
        \Log::info('Pause request received', ['data' => $request->all()]);
        
        $validated = $request->validate([
            'paused' => 'required|boolean',
        ]);

        $paused = $validated['paused'];
        $this->picoDisplayService->setTimerPaused($paused);
        
        \Log::info('Pause state updated', ['paused' => $paused]);

        return response()->json([
            'success' => true,
            'paused' => $paused,
            'message' => $paused ? 'Timer paused' : 'Timer resumed'
        ]);
    }
}
