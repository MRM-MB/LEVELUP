/**
 * Pico W Timer Phase Synchronization
 * 
 * This script syncs the focus timer phase (sitting/standing) with the Pico W
 * so the RGB LED can show the correct color and displays warnings.
 */

// Send timer phase and time info to backend for Pico W
function updatePicoTimerPhase(phase, timeRemaining = null) {
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    
    if (!csrfToken) {
        console.warn('⚠️ No CSRF token found, cannot update Pico timer phase');
        return;
    }

    const payload = { phase: phase };
    if (timeRemaining !== null) {
        payload.time_remaining = timeRemaining;
    }

    console.log(`📡 Sending timer phase to backend:`, payload);

    fetch('/api/pico/timer-phase', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log(`✅ Pico timer phase updated:`, data);
    })
    .catch(error => {
        console.error('❌ Failed to update Pico timer phase:', error);
    });
}

// Hook into the focus timer callbacks
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Pico timer sync script loaded');
    
    // Wait for focusClockUI to be initialized
    const checkFocusClock = setInterval(() => {
        if (window.focusClockUI && window.focusClockUI.core) {
            console.log('🔗 Hooking into focus timer for Pico sync');
            clearInterval(checkFocusClock);
            
            // Store original callback
            const originalOnTick = window.focusClockUI.core.callbacks.onTick;
            const originalOnSessionChange = window.focusClockUI.core.callbacks.onSessionChange;
            
            let lastPhase = null;
            let lastTimeUpdate = 0;
            
            // Wrap onTick to detect phase changes and send time updates
            window.focusClockUI.core.callbacks.onTick = function(timeLeft, isSitting) {
                // Call original callback
                if (originalOnTick) {
                    originalOnTick(timeLeft, isSitting);
                }
                
                // Send phase update if it changed
                const currentPhase = isSitting ? 'sitting' : 'standing';
                if (currentPhase !== lastPhase) {
                    updatePicoTimerPhase(currentPhase, timeLeft);
                    console.log(`🪑 Session state changed to ${currentPhase}`);
                    lastPhase = currentPhase;
                    lastTimeUpdate = Date.now();
                }
                
                // Send time updates every 5 seconds
                const now = Date.now();
                if (now - lastTimeUpdate >= 5000) {
                    updatePicoTimerPhase(currentPhase, timeLeft);
                    console.log(`⏱️ Sent periodic time update for ${currentPhase} (${timeLeft}s left)`);
                    lastTimeUpdate = now;
                }
            };
            
            // Wrap onSessionChange
            window.focusClockUI.core.callbacks.onSessionChange = function(isSitting) {
                // Call original callback
                if (originalOnSessionChange) {
                    originalOnSessionChange(isSitting);
                }
                
                // Send phase update with initial time
                const phase = isSitting ? 'sitting' : 'standing';
                const timeLeft = window.focusClockUI.core.currentTime || 0;
                updatePicoTimerPhase(phase, timeLeft);
                lastPhase = phase;
                lastTimeUpdate = Date.now();
            };
            
            // Handle timer stop
            const originalStop = window.focusClockUI.core.stop.bind(window.focusClockUI.core);
            window.focusClockUI.core.stop = function() {
                originalStop();
                updatePicoTimerPhase(null, 0);
                lastPhase = null;
            };
            
            // Poll for pause state from Pico button
            checkPicoPauseState();
        }
    }, 100);
    
    // Give up after 10 seconds
    setTimeout(() => clearInterval(checkFocusClock), 10000);
});

// Check if Pico button triggered pause/resume
let lastPauseState = null;
let pauseCheckInterval = null;

function checkPicoPauseState() {
    // Don't create multiple intervals
    if (pauseCheckInterval) {
        console.log('⚠️ Pause monitoring already active');
        return;
    }
    
    console.log('🔄 Starting Pico pause state monitoring');
    
    pauseCheckInterval = setInterval(() => {
        if (!window.focusClockUI || !window.focusClockUI.core) {
            console.log('⚠️ Timer not initialized yet');
            return;
        }
        
        fetch('/api/pico/display')
            .then(response => response.json())
            .then(data => {
                const isPaused = data.is_paused;
                console.log(`📊 Pause state check: isPaused=${isPaused}, lastPauseState=${lastPauseState}, isRunning=${window.focusClockUI.core.isRunning}`);
                
                // Initialize lastPauseState on first check
                if (lastPauseState === null) {
                    lastPauseState = isPaused;
                    console.log(`🎬 Initial pause state set to: ${isPaused}`);
                    return;
                }
                
                // Only act if pause state changed
                if (isPaused !== lastPauseState) {
                    console.log(`🔄 Pause state changed: ${lastPauseState} -> ${isPaused}`);
                    
                    if (isPaused && window.focusClockUI.core.isRunning) {
                        console.log('⏸️ Pico button pressed - Pausing timer');
                        window.focusClockUI.core.pause();
                    } else if (!isPaused && !window.focusClockUI.core.isRunning) {
                        console.log('▶️ Pico button pressed - Resuming timer');
                        window.focusClockUI.core.start();
                    }
                    
                    lastPauseState = isPaused;
                }
            })
            .catch(error => {
                console.error('❌ Failed to check pause state:', error);
            });
    }, 1000); // Check every second
}
