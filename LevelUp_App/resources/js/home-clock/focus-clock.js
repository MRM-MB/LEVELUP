// ===========================================
// 🔔 Alarm Preset Definitions
// ===========================================

const ALARM_PRESET_LIST = [
    {
        id: 'option1',
        label: 'Dual Alarm Mix',
        description: 'Alarm 1 to stand up, Alarm 2 to sit back down (current setup).',
        quickSummary: 'Stand/sit dual alert',
        files: {
            standUp: '/alarm_files/alarm_1.mp3',
            backToWork: '/alarm_files/alarm_2.mp3',
            focused: '/alarm_files/alarm_1.mp3',
            gentle: '/alarm_files/alarm_2.mp3'
        },
        previewOrder: ['/alarm_files/alarm_1.mp3', '/alarm_files/alarm_2.mp3']
    },
    {
        id: 'option2',
        label: 'Alarm 1',
        description: 'Use Alarm 3 for every reminder.',
        quickSummary: 'Same alert sound',
        files: {
            standUp: '/alarm_files/alarm_3.mp3',
            backToWork: '/alarm_files/alarm_3.mp3',
            focused: '/alarm_files/alarm_3.mp3',
            gentle: '/alarm_files/alarm_3.mp3'
        },
        previewOrder: ['/alarm_files/alarm_3.mp3']
    },
    {
        id: 'option3',
        label: 'Alarm 2',
        description: 'Use Alarm 4 for every reminder.',
        quickSummary: 'Same alert sound',
        files: {
            standUp: '/alarm_files/alarm_4.mp3',
            backToWork: '/alarm_files/alarm_4.mp3',
            focused: '/alarm_files/alarm_4.mp3',
            gentle: '/alarm_files/alarm_4.mp3'
        },
        previewOrder: ['/alarm_files/alarm_4.mp3']
    },
    {
        id: 'option4',
        label: 'Alarm 3',
        description: 'Use Alarm 5 for every reminder.',
        quickSummary: 'Same alert sound',
        files: {
            standUp: '/alarm_files/alarm_5.mp3',
            backToWork: '/alarm_files/alarm_5.mp3',
            focused: '/alarm_files/alarm_5.mp3',
            gentle: '/alarm_files/alarm_5.mp3'
        },
        previewOrder: ['/alarm_files/alarm_5.mp3']
    }
];

const ALARM_PRESETS = ALARM_PRESET_LIST.reduce((map, preset) => {
    map[preset.id] = preset;
    return map;
}, {});

const DEFAULT_ALARM_PRESET_ID = ALARM_PRESET_LIST[0].id;

function renderAlarmPresetSelectOptions(selectedId = DEFAULT_ALARM_PRESET_ID) {
    return ALARM_PRESET_LIST.map((preset, index) => {
        const isSelected = (selectedId && preset.id === selectedId) || (!selectedId && index === 0);
        return `<option value="${preset.id}" ${isSelected ? 'selected' : ''}>${preset.label}</option>`;
    }).join('');
}

function renderAlarmPresetSummary(presetId = DEFAULT_ALARM_PRESET_ID) {
    const preset = ALARM_PRESETS[presetId] || ALARM_PRESETS[DEFAULT_ALARM_PRESET_ID];
    if (!preset) {
        return '';
    }

    const summary = `
        <div style="display: inline-flex; align-items: center; gap: 0.45rem; background: rgba(238, 242, 255, 0.95); padding: 0.35rem 0.9rem; border-radius: 999px; color: #1E293B; font-size: 0.74rem; font-weight: 600; letter-spacing: 0.01em;">
            <i class="fas fa-music" style="color: #6366F1;"></i>
            <span>${preset.quickSummary || ''}</span>
        </div>
    `;

    return summary.trim();
}

// ===========================================
// ⏰ FOCUS CLOCK CORE - TIMER LOGIC
// ===========================================

class FocusClockCore {
    constructor() {
        this.sittingTime = 20; // minutes (20:10 pattern)
        this.standingTime = 10; // minutes (20:10 pattern)
        this.currentTime = 0; // seconds
        this.isRunning = false;
        this.isSittingSession = true;
        // Removed cycleCount - now using database today's cycles instead
        this.intervalId = null;
        this.backupIntervalId = null; // Backup timer for background reliability
        this.completionTimeoutId = null; // Failsafe timeout for session completion
        this.alarmTimeoutId = null; // Scheduled alarm timeout for precise 30-second warning
        this.sessionStartTime = null; // When the current session started
        this.sessionDuration = 0; // Total duration of current session in seconds
        this.currentAlarm = null; // Track current alarm audio
        this.currentPopup = null; // Track current popup
        this.warningShown = false; // Track if 30-second warning has been shown for current session
        this.callbacks = {
            onTick: () => {},
            onSessionChange: () => {},
            onCycleComplete: () => {}
        };
        this.storage = null;
    }

    // Set storage instance
    setStorage(storage) {
        this.storage = storage;
    }

    // Save current state
    saveState() {
        if (this.storage) {
            this.storage.saveTimerState({
                isSittingSession: this.isSittingSession,
                sessionDuration: this.sessionDuration,
                currentTime: this.currentTime,
                isRunning: this.isRunning,
                sessionStartTime: this.sessionStartTime,
                lastUpdated: Date.now()
            });
        }
    }

    // Restore state
    restoreState() {
        if (!this.storage) return false;

        const state = this.storage.getTimerState();
        if (!state) return false;

        // Check if state is too old (e.g., > 24 hours)
        if (Date.now() - state.lastUpdated > 24 * 60 * 60 * 1000) {
            this.storage.clearTimerState();
            return false;
        }

        this.isSittingSession = state.isSittingSession;
        this.sessionDuration = state.sessionDuration;
        
        // Restore sitting/standing times from session duration to ensure consistency
        // This is an approximation but keeps the logic sound
        if (this.isSittingSession) {
            this.sittingTime = Math.round(this.sessionDuration / 60);
        } else {
            this.standingTime = Math.round(this.sessionDuration / 60);
        }

        if (state.isRunning && state.sessionStartTime) {
            // Calculate elapsed time since it was running
            const elapsed = Math.floor((Date.now() - state.sessionStartTime) / 1000);
            this.currentTime = Math.max(0, this.sessionDuration - elapsed);
            
            // Reconstruct sessionStartTime so start() knows we are resuming
            this.sessionStartTime = Date.now() - ((this.sessionDuration - this.currentTime) * 1000);
            
            if (this.currentTime > 0) {
                // Resume automatically
                this.start();
            } else {
                // Session finished while away
                this.currentTime = 0;
                this.completeSession();
            }
        } else {
            // Was paused or stopped
            this.currentTime = state.currentTime;
            this.isRunning = false;
            
            // Reconstruct sessionStartTime so start() knows we are resuming if user clicks start
            // Only if we are not at the very beginning (fresh start)
            if (this.currentTime < this.sessionDuration) {
                 this.sessionStartTime = Date.now() - ((this.sessionDuration - this.currentTime) * 1000);
            } else {
                 this.sessionStartTime = null; // Treat as fresh start
            }
            
            this.callbacks.onTick(this.currentTime, this.isSittingSession);
        }
        
        return true;
    }

    // Timer state persistence removed - cycles now come from database
    // No need to persist cycle count since it's always fetched fresh from database

    // Initialize with custom sitting and standing times
    initialize(sittingMinutes, standingMinutes) {
        this.sittingTime = Math.max(1, sittingMinutes);
        this.standingTime = Math.max(1, standingMinutes);
        this.currentTime = this.sittingTime * 60; // Convert to seconds
        this.sessionDuration = this.sittingTime * 60;
        this.isSittingSession = true;
        this.cycleCount = 0;
    }

    // Set callback functions
    setCallbacks(callbacks) {
        this.callbacks = { ...this.callbacks, ...callbacks };
    }

    // Start or resume the timer
    start() {
        if (this.isRunning) return;

        // Clear any existing interval first
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        if (this.backupIntervalId) {
            clearInterval(this.backupIntervalId);
            this.backupIntervalId = null;
        }
        if (this.completionTimeoutId) {
            clearTimeout(this.completionTimeoutId);
            this.completionTimeoutId = null;
        }
        if (this.alarmTimeoutId) {
            clearTimeout(this.alarmTimeoutId);
            this.alarmTimeoutId = null;
        }

        // Preload alarm audio files for instant playback
        this.preloadAlarmAudio();
        
        // Unlock audio context (must be called during user interaction)
        this.unlockAudio();

        // If resuming from pause, use existing currentTime
        // If starting fresh, set up new session duration
        if (!this.sessionStartTime || this.currentTime >= this.sessionDuration) {
            this.sessionDuration = this.isSittingSession ? this.sittingTime * 60 : this.standingTime * 60;
            this.currentTime = this.sessionDuration;
            this.warningShown = false; // Reset warning for new session
        }
        
        // Update the start time and status immediately
        this.sessionStartTime = Date.now() - ((this.sessionDuration - this.currentTime) * 1000);
        this.isRunning = true;
        this.saveState();
        
        // Calculate when this session should complete
        const timeUntilCompletion = this.currentTime * 1000; // Convert to milliseconds
        
        // Set a failsafe timeout to ensure session completes even if intervals are throttled
        if (this.completionTimeoutId) {
            clearTimeout(this.completionTimeoutId);
        }
        this.completionTimeoutId = setTimeout(() => {
            if (this.isRunning) {
                console.log('⏰ Failsafe timeout triggered - ensuring session completion');
                this.checkForMissedCompletion();
            }
        }, timeUntilCompletion + 100); // Add 100ms buffer
        
        // Schedule 30-second warning alarm (independent of tick intervals)
        if (this.alarmTimeoutId) {
            clearTimeout(this.alarmTimeoutId);
        }
        const timeUntilAlarm = (this.currentTime - 31) * 1000; // 31 seconds before end (compensate for delay)
        if (timeUntilAlarm > 0) {
            // Schedule stopping of current alarm if it would overlap
            this.scheduleAlarmStop(timeUntilAlarm);
            
            // Use RequestAnimationFrame for more reliable timing in background
            // Store the target time for the alarm
            const alarmTargetTime = Date.now() + timeUntilAlarm;
            
            const checkAlarmTiming = () => {
                const now = Date.now();
                const timeRemaining = alarmTargetTime - now;
                
                // If we're within 500ms of target or past it, trigger alarm
                if (timeRemaining <= 500) {
                    if (this.isRunning && !this.warningShown) {
                        console.log('⏰ Scheduled 30-second alarm triggered (RAF check)');
                        this.warningShown = true;
                        
                        // Play the MAIN alarm 30 seconds before session ends
                        if (this.isSittingSession) {
                            this.playAlarmAndShowPopup('standUp');
                        } else {
                            this.playAlarmAndShowPopup('backToWork');
                        }
                    }
                    return; // Stop checking
                }
                
                // If still waiting, check again with RAF (more reliable than setTimeout)
                if (this.isRunning && !this.warningShown) {
                    requestAnimationFrame(checkAlarmTiming);
                }
            };
            
            // Start with a setTimeout as primary trigger, but RAF as backup
            this.alarmTimeoutId = setTimeout(() => {
                if (this.isRunning && !this.warningShown) {
                    console.log('⏰ Scheduled 30-second alarm triggered at exact timing');
                    this.warningShown = true;
                    
                    // Play the MAIN alarm 30 seconds before session ends
                    if (this.isSittingSession) {
                        this.playAlarmAndShowPopup('standUp');
                    } else {
                        this.playAlarmAndShowPopup('backToWork');
                    }
                }
            }, timeUntilAlarm);
            
            // Also use RAF as backup in case setTimeout is throttled
            requestAnimationFrame(checkAlarmTiming);
            
            console.log(`⏰ Scheduled 30-second alarm to trigger in ${timeUntilAlarm}ms (with RAF backup)`);
        } else {
            console.log('⏰ Session too short for 30-second warning');
        }
        
        // Start the interval and update display immediately
        this.callbacks.onTick(this.currentTime, this.isSittingSession);
        
        // Use multiple timing strategies to prevent background throttling issues
        this.intervalId = setInterval(() => {
            this.tick();
        }, 50); // More frequent updates (50ms) for critical health alerts
        
        // Add a secondary backup timer that checks every second
        this.backupIntervalId = setInterval(() => {
            this.checkForMissedCompletion();
        }, 1000);
    }

    // Pause the timer
    pause() {
        this.isRunning = false;
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        if (this.backupIntervalId) {
            clearInterval(this.backupIntervalId);
            this.backupIntervalId = null;
        }
        if (this.completionTimeoutId) {
            clearTimeout(this.completionTimeoutId);
            this.completionTimeoutId = null;
        }
        if (this.alarmTimeoutId) {
            clearTimeout(this.alarmTimeoutId);
            this.alarmTimeoutId = null;
        }
        // Calculate remaining time when paused
        if (this.sessionStartTime) {
            const elapsed = Math.floor((Date.now() - this.sessionStartTime) / 1000);
            this.currentTime = Math.max(0, this.sessionDuration - elapsed);
        }
        this.saveState();
    }

    // Stop and reset the timer
    stop() {
        this.pause();
        this.currentTime = this.isSittingSession ? this.sittingTime * 60 : this.standingTime * 60;
        this.sessionDuration = this.currentTime;
        this.sessionStartTime = null; // This is key for distinguishing between fresh start and resume
        this.callbacks.onTick(this.currentTime, this.isSittingSession);
        if (this.storage) {
            this.storage.clearTimerState();
        }
    }

    // Timer tick function - always calculates based on session start time
    tick() {
        if (!this.isRunning || !this.sessionStartTime) return;

        // Calculate elapsed time since session started
        const now = Date.now();
        const elapsedSeconds = Math.floor((now - this.sessionStartTime) / 1000);
        const newTime = Math.max(0, this.sessionDuration - elapsedSeconds);
        
        // Only update if time has actually changed
        if (newTime !== this.currentTime) {
            this.currentTime = newTime;
            // Update display
            this.callbacks.onTick(this.currentTime, this.isSittingSession);
            
            // Save state periodically (every second)
            this.saveState();

            // Check if we need to switch sessions
            if (this.currentTime <= 0) {
                // Prevent multiple switches
                if (this.intervalId) {
                    clearInterval(this.intervalId);
                    this.intervalId = null;
                }
                this.currentTime = 0;
                this.completeSession();
                return;
            }
        }
    }

    // Check for missed session completions (called when tab becomes visible)
    checkForMissedCompletion() {
        if (!this.isRunning || !this.sessionStartTime) return;

        // Calculate how much time has actually passed
        const now = Date.now();
        const elapsedSeconds = Math.floor((now - this.sessionStartTime) / 1000);
        const actualTimeLeft = Math.max(0, this.sessionDuration - elapsedSeconds);
        
        // If the session should have completed while we were in background
        if (actualTimeLeft === 0 && this.currentTime > 0) {
            console.log('⚠️ Missed session completion detected! Completing immediately...');
            this.currentTime = 0;
            this.completeSession();
        }
    }

    // Complete current session and switch
    completeSession() {
        console.log(`🔄 Completing session - Current state: ${this.isSittingSession ? 'Sitting' : 'Standing'}`);
        
        // Validate timer settings before proceeding
        if (this.sittingTime <= 0 || this.standingTime <= 0) {
            console.error('❌ Invalid timer settings detected!', {
                sitting: this.sittingTime,
                standing: this.standingTime
            });
            return;
        }

        // Stop the current timer
        clearInterval(this.intervalId);
        this.intervalId = null;
        if (this.backupIntervalId) {
            clearInterval(this.backupIntervalId);
            this.backupIntervalId = null;
        }
        if (this.completionTimeoutId) {
            clearTimeout(this.completionTimeoutId);
            this.completionTimeoutId = null;
        }
        if (this.alarmTimeoutId) {
            clearTimeout(this.alarmTimeoutId);
            this.alarmTimeoutId = null;
        }
        this.isRunning = false;
        
        const wasSitting = this.isSittingSession;
        this.isSittingSession = !wasSitting;  // Switch session type

        // Note: We don't clean up alarm/popup here anymore so it persists through session changes
        // This gives users more time to interact with the 30-second warning popup

        if (wasSitting) {
            // Sitting session completed, switch to standing
            console.log('✅ Switching from sitting to standing');
            
            // No alarm here - it already played at 30 seconds warning
        } else {
            // Standing session completed, switch to sitting
            console.log('✅ Switching from standing to sitting');
            
            // Full cycle completed (sitting + standing) - trigger callback
            // Note: Cycle count now comes from database, not localStorage
            this.callbacks.onCycleComplete();
            
            // No alarm here - it already played at 2 minutes warning
        }

        // Update display and trigger callbacks before starting new session
        this.callbacks.onSessionChange(this.isSittingSession);
        
        // Clear any existing interval and reset session state
        this.sessionDuration = this.isSittingSession ? this.sittingTime * 60 : this.standingTime * 60;
        this.currentTime = this.sessionDuration;
        this.sessionStartTime = Date.now();
        this.isRunning = true;
        this.warningShown = false; // Reset warning for new session
        this.saveState();
        
        // Set failsafe timeout for new session
        if (this.completionTimeoutId) {
            clearTimeout(this.completionTimeoutId);
        }
        this.completionTimeoutId = setTimeout(() => {
            if (this.isRunning) {
                console.log('⏰ Failsafe timeout triggered - ensuring session completion');
                this.checkForMissedCompletion();
            }
        }, this.sessionDuration * 1000 + 100);
        
        // Schedule 30-second warning alarm for new session
        if (this.alarmTimeoutId) {
            clearTimeout(this.alarmTimeoutId);
        }
        const timeUntilAlarm = (this.sessionDuration - 31) * 1000; // 31 seconds before end (compensate for delay)
        if (timeUntilAlarm > 0) {
            // Schedule stopping of current alarm if it would overlap
            this.scheduleAlarmStop(timeUntilAlarm);
            
            // Use RequestAnimationFrame for more reliable timing in background
            const alarmTargetTime = Date.now() + timeUntilAlarm;
            
            const checkAlarmTiming = () => {
                const now = Date.now();
                const timeRemaining = alarmTargetTime - now;
                
                // If we're within 500ms of target or past it, trigger alarm
                if (timeRemaining <= 500) {
                    if (this.isRunning && !this.warningShown) {
                        console.log('⏰ Scheduled 30-second alarm triggered for new session (RAF check)');
                        this.warningShown = true;
                        
                        // Play the MAIN alarm 30 seconds before session ends
                        if (this.isSittingSession) {
                            this.playAlarmAndShowPopup('standUp');
                        } else {
                            this.playAlarmAndShowPopup('backToWork');
                        }
                    }
                    return;
                }
                
                // If still waiting, check again with RAF
                if (this.isRunning && !this.warningShown) {
                    requestAnimationFrame(checkAlarmTiming);
                }
            };
            
            // Start with a setTimeout as primary trigger, but RAF as backup
            this.alarmTimeoutId = setTimeout(() => {
                if (this.isRunning && !this.warningShown) {
                    console.log('⏰ Scheduled 30-second alarm triggered for new session at exact timing');
                    this.warningShown = true;
                    
                    // Play the MAIN alarm 30 seconds before session ends
                    if (this.isSittingSession) {
                        this.playAlarmAndShowPopup('standUp');
                    } else {
                        this.playAlarmAndShowPopup('backToWork');
                    }
                }
            }, timeUntilAlarm);
            
            // Also use RAF as backup in case setTimeout is throttled
            requestAnimationFrame(checkAlarmTiming);
            
            console.log(`⏰ Scheduled 30-second alarm for new session to trigger in ${timeUntilAlarm}ms (with RAF backup)`);
        }
        
        // Start new intervals with enhanced background support
        this.intervalId = setInterval(() => this.tick(), 50);
        this.backupIntervalId = setInterval(() => this.checkForMissedCompletion(), 1000);
        
        // Update display immediately with new session
        this.callbacks.onTick(this.currentTime, this.isSittingSession);
    }
    
    getActiveAlarmPreset() {
        let presetId = DEFAULT_ALARM_PRESET_ID;

        try {
            if (window.focusClockUI && window.focusClockUI.storage) {
                const settings = window.focusClockUI.storage.getSettings();
                if (settings && settings.alarmPreset && ALARM_PRESETS[settings.alarmPreset]) {
                    presetId = settings.alarmPreset;
                }
            }
        } catch (error) {
            console.warn('Unable to resolve alarm preset, falling back to default:', error);
        }

        return ALARM_PRESETS[presetId] || ALARM_PRESETS[DEFAULT_ALARM_PRESET_ID];
    }

    // Preload alarm audio for instant playback
    preloadAlarmAudio() {
        try {
            // Preload both alarm sounds
            if (!this.preloadedAudio) {
                this.preloadedAudio = {};
            }
            
            const audioFiles = new Set();
            ALARM_PRESET_LIST.forEach(preset => {
                Object.values(preset.files || {}).forEach(file => audioFiles.add(file));
                (preset.previewOrder || []).forEach(file => audioFiles.add(file));
            });

            audioFiles.forEach(file => {
                if (!this.preloadedAudio[file]) {
                    const audio = new Audio(file);
                    audio.preload = 'auto';
                    audio.load(); // Force loading
                    this.preloadedAudio[file] = audio;
                    console.log(`🔊 Preloaded audio: ${file}`);
                }
            });
        } catch (error) {
            console.warn('Audio preload failed:', error);
        }
    }

    // Unlock audio context (must be called during user interaction)
    unlockAudio() {
        if (!this.preloadedAudio) return;
        
        console.log('🔓 Unlocking audio context...');
        // Unlock ALL audio files to ensure any of them can play
        Object.values(this.preloadedAudio).forEach(audio => {
            try {
                // Play and immediately pause to unlock audio on mobile/laptops
                // We set volume to 0 to avoid noise
                const originalVolume = audio.volume;
                audio.volume = 0;
                
                const playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        audio.pause();
                        audio.currentTime = 0;
                        audio.volume = originalVolume;
                        console.log('✅ Audio unlocked successfully');
                    }).catch(error => {
                        console.warn('Audio unlock failed (expected if no interaction):', error);
                        audio.volume = originalVolume;
                    });
                }
            } catch (e) {
                console.warn('Error unlocking audio:', e);
            }
        });
    }
    
    // Calculate when to stop current alarm to avoid overlaps with next alarm
    scheduleAlarmStop(nextAlarmDelay) {
        // If no current alarm is playing, nothing to stop
        if (!this.currentAlarm || this.currentAlarm.paused || this.currentAlarm.ended) {
            return;
        }
        
        // Check if the alarm is set to loop (continuous play)
        const settings = window.focusClockUI ? window.focusClockUI.storage.getSettings() : { alertDuration: 'loop' };
        
        if (settings.alertDuration === 'loop') {
            // Stop the current alarm 15 seconds before the next one starts
            const stopTime = Math.max(0, nextAlarmDelay - (15 * 1000)); // 15 seconds before next alarm
            
            if (stopTime > 0) {
                // Use both setTimeout and RAF for reliability in background
                const stopTargetTime = Date.now() + stopTime;
                
                const checkStopTiming = () => {
                    const now = Date.now();
                    const timeRemaining = stopTargetTime - now;
                    
                    // If we're within 500ms of target or past it, stop alarm
                    if (timeRemaining <= 500) {
                        if (this.currentAlarm && !this.currentAlarm.paused) {
                            console.log('🔇 Stopping current alarm 15 seconds before next alarm (RAF check)');
                            this.cleanupAlarmAndPopup();
                        }
                        return;
                    }
                    
                    // Keep checking with RAF
                    if (this.currentAlarm && !this.currentAlarm.paused) {
                        requestAnimationFrame(checkStopTiming);
                    }
                };
                
                // Primary: setTimeout
                setTimeout(() => {
                    if (this.currentAlarm && !this.currentAlarm.paused) {
                        console.log('🔇 Stopping current alarm 15 seconds before next alarm');
                        this.cleanupAlarmAndPopup();
                    }
                }, stopTime);
                
                // Backup: RAF for background reliability
                requestAnimationFrame(checkStopTiming);
                
                console.log(`🔇 Scheduled current alarm to stop in ${stopTime / 1000}s (15s before next alarm) with RAF backup`);
            } else {
                // If next alarm is very soon, stop current alarm immediately
                console.log('🔇 Stopping current alarm immediately - next alarm starting soon');
                this.cleanupAlarmAndPopup();
            }
        }
    }
    
    // Get alarm sound based on session type
    getAlarmSoundForSession(sessionType = 'standUp') {
        const preset = this.getActiveAlarmPreset();
        const files = preset?.files || {};
        return files[sessionType] || files.standUp || files.backToWork || '/alarm_files/alarm_1.mp3';
    }

    // Get popup content based on session type
    getPopupContentForSession(sessionType) {
        // Get audio settings to show duration info
        const settings = window.focusClockUI ? window.focusClockUI.storage.getSettings() : { alertDuration: 'loop', audioEnabled: true };
        
        let durationInfo = '';
        if (settings.audioEnabled) {
            if (settings.alertDuration === 'once') {
                durationInfo = ' (Audio plays once)';
            } else if (settings.alertDuration !== 'loop') {
                durationInfo = ` (Audio plays for ${settings.alertDuration} seconds)`;
            }
        } else {
            durationInfo = ' (Audio disabled)';
        }

        const content = {
            standUp: {
                icon: '🚶‍♂️',
                title: 'Stand Up Break!',
                message: `${this.standingTime}-min break to stretch and move around${durationInfo}`,
                buttonText: settings.audioEnabled ? 'Stop Alarm' : 'Got It',
                buttonColor: '#EF4444'
            },
            backToWork: {
                icon: '💺',
                title: 'Back to Work',
                message: `Time for ${this.sittingTime} minutes of focused work${durationInfo}`,
                buttonText: settings.audioEnabled ? 'Stop Alarm' : 'Got It',
                buttonColor: '#3B82F6'
            },
            focused: {
                icon: '🎯',
                title: 'Focus Time',
                message: `Deep work session - ${this.sittingTime} minutes of concentration${durationInfo}`,
                buttonText: 'Start Focusing',
                buttonColor: '#059669'
            }
        };

        return content[sessionType] || content.standUp;
    }



    // Clean up any existing alarm and popup
    cleanupAlarmAndPopup() {
        console.log('🧹 Cleaning up existing alarm and popup');
        
        // Stop and cleanup alarm
        if (this.currentAlarm) {
            this.currentAlarm.pause();
            this.currentAlarm.currentTime = 0;
            this.currentAlarm = null;
        }

        // Immediately remove all existing modals to prevent overlap
        const existingModals = document.querySelectorAll('.clock-modal');
        existingModals.forEach(modal => {
            if (modal && modal.parentNode) {
                modal.remove();
            }
        });
        
        // Remove any existing notifications
        const existingNotifications = document.querySelectorAll('.login-prompt-notification, .points-notification');
        existingNotifications.forEach(notification => {
            if (notification && notification.parentNode) {
                notification.remove();
            }
        });

        // Clear the reference
        this.currentPopup = null;
        
        console.log('✅ Cleanup completed');
    }

    // Play alarm and show popup
    playAlarmAndShowPopup(sessionType = 'standUp') {
        // Get audio settings
        const settings = window.focusClockUI ? window.focusClockUI.storage.getSettings() : { audioEnabled: true, alertDuration: 'loop', globalVolume: 80 };
        
        // If audio is disabled, do nothing at all (no popup, no sound)
        if (!settings.audioEnabled) {
            console.log('🔇 Audio alerts disabled - no popup or sound will be shown');
            return;
        }
        
        // Clean up any existing alarm and popup first
        this.cleanupAlarmAndPopup();
        
        // Create new alarm popup immediately (no delay for instant audio)
        this.createNewAlarmPopup(sessionType, settings);
    }

    // Create new alarm popup (separated for better control)
    createNewAlarmPopup(sessionType, settings = { audioEnabled: true, alertDuration: 'loop' }) {
        console.log(`🔔 Creating ${sessionType} popup with settings:`, settings);
        
        // Only create audio if enabled
        if (settings.audioEnabled) {
            // Choose alarm sound based on session type
            const alarmSound = this.getAlarmSoundForSession(sessionType);
            
            // Determine alarm type for volume persistence
            const alarmType = sessionType === 'standUp' ? 'alarm1' : 'alarm2';
            const savedVolume = window.focusClockUI ? window.focusClockUI.storage.getAlarmVolume(alarmType) : 100;
            
            // Use preloaded audio if available for instant playback
            if (this.preloadedAudio && this.preloadedAudio[alarmSound]) {
                // Use the ORIGINAL preloaded audio object to benefit from the "blessing"
                // obtained during unlockAudio() (user interaction).
                // Cloning the node often loses the blessed status on some browsers.
                this.currentAlarm = this.preloadedAudio[alarmSound];
                
                // Reset state just in case
                this.currentAlarm.currentTime = 0;
                if (!this.currentAlarm.paused) {
                    this.currentAlarm.pause();
                }
                
                console.log(`🚀 Using preloaded audio (original): ${alarmSound}`);
            } else {
                // Fallback to creating new Audio object
                this.currentAlarm = new Audio(alarmSound);
                this.currentAlarm.setAttribute('preload', 'auto');
                console.log(`⏳ Creating new audio: ${alarmSound}`);
            }
            
            // Configure alarm properties
            this.currentAlarm.loop = settings.alertDuration === 'loop';
            this.currentAlarm.volume = savedVolume / 100; // Use saved volume (0-1 range)
            this.currentAlarm.setAttribute('autoplay', 'true');
            
            // Store the alarm type for volume saving
            this.currentAlarmType = alarmType;

            // Function to ensure alarm plays
            const ensureAlarmPlays = () => {
                const playPromise = this.currentAlarm.play();
                if (playPromise !== undefined) {
                    playPromise.catch(error => {
                        console.warn('Playback failed, will retry:', error);
                        const retryPlay = () => {
                            if (this.currentAlarm) {
                                this.currentAlarm.play().catch(console.warn);
                            }
                            document.removeEventListener('click', retryPlay);
                        };
                        document.addEventListener('click', retryPlay);
                    });
                }
            };

            // Try to play immediately
            ensureAlarmPlays();

            // Handle timed alerts (auto-stop after specified duration)
            if (settings.alertDuration !== 'loop') {
                let autoDismissDelay = 0;
                
                if (settings.alertDuration === 'once') {
                    // For single play, auto-dismiss when audio ends naturally
                    this.currentAlarm.addEventListener('ended', () => {
                        console.log('🎵 Single play audio finished, auto-dismissing popup');
                        this.cleanupAlarmAndPopup();
                    });
                } else {
                    // For timed alerts, auto-stop and dismiss after specified duration
                    autoDismissDelay = parseInt(settings.alertDuration) * 1000;
                    
                    // Use both setTimeout and RAF for reliable background stopping
                    const stopTargetTime = Date.now() + autoDismissDelay;
                    
                    const checkAutoStopTiming = () => {
                        const now = Date.now();
                        const timeRemaining = stopTargetTime - now;
                        
                        // If we're within 500ms of target or past it, stop alarm
                        if (timeRemaining <= 500) {
                            if (this.currentAlarm && !this.currentAlarm.paused) {
                                console.log(`⏰ Auto-stopping audio after ${settings.alertDuration} seconds (RAF check)`);
                                this.cleanupAlarmAndPopup();
                            }
                            return;
                        }
                        
                        // Keep checking with RAF
                        if (this.currentAlarm && !this.currentAlarm.paused) {
                            requestAnimationFrame(checkAutoStopTiming);
                        }
                    };
                    
                    // Primary: setTimeout
                    setTimeout(() => {
                        if (this.currentAlarm && !this.currentAlarm.paused) {
                            console.log(`⏰ Auto-stopping and dismissing after ${settings.alertDuration} seconds`);
                            this.cleanupAlarmAndPopup();
                        }
                    }, autoDismissDelay);
                    
                    // Backup: RAF for background reliability
                    requestAnimationFrame(checkAutoStopTiming);
                    
                    console.log(`⏰ Scheduled auto-stop after ${settings.alertDuration}s (with RAF backup)`);
                }
            }
        }

        // Create a completely fresh popup element
        this.currentPopup = document.createElement('div');
        this.currentPopup.className = 'clock-modal';
        this.currentPopup.style.display = 'flex';
        
        // Get content based on session type
        const popupContent = this.getPopupContentForSession(sessionType);
        console.log(`📋 Popup content for ${sessionType}:`, popupContent);
        
        this.currentPopup.innerHTML = `
            <div class="modal-content" style="max-width: 300px; padding: 1rem;">
                <div class="modal-header" style="padding: 0 0 0.5rem 0; margin: 0;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.5rem;">${popupContent.icon}</span>
                        <h3 style="margin: 0; font-size: 1.1rem;">${popupContent.title}</h3>
                    </div>
                </div>
                <div class="modal-body" style="padding: 0.5rem 0;">
                    <p style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #666;">
                        ${popupContent.message}
                    </p>
                    
                    <!-- Volume Control in Popup -->
                    <div class="popup-volume-control" style="margin: 1rem 0; 
                                                             padding: 0.75rem; 
                                                             background: rgba(0,0,0,0.05); 
                                                             border-radius: 8px;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-volume-up popup-volume-icon" style="color: #6B7280; min-width: 16px;"></i>
                            <input type="range" 
                                   class="popup-volume-slider"
                                   min="0" 
                                   max="100" 
                                   value="${this.currentAlarm ? Math.round(this.currentAlarm.volume * 100) : 100}"
                                   step="1"
                                   style="flex: 1; 
                                          height: 4px; 
                                          border-radius: 2px; 
                                          background: #E5E7EB; 
                                          outline: none; 
                                          cursor: pointer; 
                                          accent-color: ${popupContent.buttonColor}; 
                                          -webkit-appearance: none;">
                            <span class="popup-volume-percentage" style="font-size: 0.8rem; 
                                                                          color: #6B7280; 
                                                                          min-width: 35px; 
                                                                          text-align: center;">${this.currentAlarm ? Math.round(this.currentAlarm.volume * 100) : 100}%</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 0.5rem 0 0 0; margin: 0;">
                    <button class="stop-alarm-btn" 
                            style="width: 100%; 
                                   padding: 0.5rem; 
                                   font-size: 0.9rem; 
                                   display: flex; 
                                   align-items: center; 
                                   justify-content: center; 
                                   gap: 0.5rem;
                                   background: ${popupContent.buttonColor};
                                   color: white;
                                   border: none;
                                   border-radius: 0.375rem;
                                   cursor: pointer;">
                        <i class="fas fa-bell-slash"></i>
                        ${popupContent.buttonText}
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(this.currentPopup);

        // Set up event listeners directly here
        const stopBtn = this.currentPopup.querySelector('.stop-alarm-btn');
        const volumeSlider = this.currentPopup.querySelector('.popup-volume-slider');
        const volumeIcon = this.currentPopup.querySelector('.popup-volume-icon');
        const volumePercentage = this.currentPopup.querySelector('.popup-volume-percentage');
        
        if (stopBtn) {
            console.log('✅ Stop button found, setting up event listener');
            stopBtn.addEventListener('click', (e) => {
                console.log('🔴 Stop button clicked!');
                e.preventDefault();
                e.stopPropagation();
                this.cleanupAlarmAndPopup();
            });
        } else {
            console.warn('Stop button not found in popup');
        }

        // Set up volume control with persistence
        if (volumeSlider && volumeIcon && volumePercentage && this.currentAlarm) {
            // Initialize volume icon based on current volume
            const currentVolume = this.currentAlarm.volume;
            if (currentVolume === 0) {
                volumeIcon.className = 'fas fa-volume-mute popup-volume-icon';
            } else if (currentVolume < 0.5) {
                volumeIcon.className = 'fas fa-volume-down popup-volume-icon';
            } else {
                volumeIcon.className = 'fas fa-volume-up popup-volume-icon';
            }
            
            volumeSlider.addEventListener('input', (e) => {
                const volume = parseFloat(e.target.value) / 100;
                this.currentAlarm.volume = volume;
                
                // Update volume icon
                if (volume === 0) {
                    volumeIcon.className = 'fas fa-volume-mute popup-volume-icon';
                } else if (volume < 0.5) {
                    volumeIcon.className = 'fas fa-volume-down popup-volume-icon';
                } else {
                    volumeIcon.className = 'fas fa-volume-up popup-volume-icon';
                }
                
                // Update percentage display
                volumePercentage.textContent = Math.round(volume * 100) + '%';
                
                // Save volume for this alarm type
                if (this.currentAlarmType && window.focusClockUI) {
                    window.focusClockUI.storage.saveAlarmVolume(this.currentAlarmType, Math.round(volume * 100));
                    console.log('💾 Saved volume for', this.currentAlarmType + ':', Math.round(volume * 100) + '%');
                }
                
                console.log('🔊 Popup volume set to:', Math.round(volume * 100) + '%');
            });
        }
    }

    // Get current session info
    getCurrentSession() {
        return {
            isSitting: this.isSittingSession,
            timeLeft: this.currentTime,
            isRunning: this.isRunning
        };
    }

    // Update sitting/standing times
    updateTimes(sittingMinutes, standingMinutes) {
        const wasRunning = this.isRunning;
        this.pause();

        this.sittingTime = Math.max(1, sittingMinutes);
        this.standingTime = Math.max(1, standingMinutes);

        // Reset current time based on current session
        this.currentTime = this.isSittingSession ? this.sittingTime * 60 : this.standingTime * 60;
        this.sessionDuration = this.currentTime;

        if (wasRunning) {
            this.start();
        } else {
            this.saveState();
        }

        this.callbacks.onTick(this.currentTime, this.isSittingSession);
    }

    // Format time for display
    static formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    // Validate healthy sitting-to-standing ratio
    static validateHealthyRatio(sittingMinutes, standingMinutes) {
        const ratio = sittingMinutes / standingMinutes;
        const idealRatio = 2; // 20min:10min = 2:1 ratio (Cornell University research - 20:10 pattern)

        let recommendation = '';
        let isHealthy = true;
        let level = 'good';

        if (ratio >= 1.5 && ratio <= 2.5) {
            recommendation = 'Perfect! This follows Cornell University research for optimal desk worker health (20:10 pattern).';
            level = 'good';
        } else if (ratio < 1.5) {
            recommendation = 'Good effort! You might be standing a bit too much. Aim for the 20:10 ratio.';
            level = 'good';
        } else if (ratio <= 4) {
            recommendation = 'Consider more standing breaks. Cornell research shows 20 minutes sitting to 10 standing is ideal.';
            level = 'warning';
            isHealthy = false;
        } else {
            recommendation = 'Health Alert: Too much sitting. Try the 20:10 pattern recommended by Cornell University research.';
            level = 'warning';
            isHealthy = false;
        }

        return {
            isHealthy: isHealthy,
            ratio: ratio,
            idealRatio: idealRatio,
            recommendation: recommendation,
            level: level
        };
    }
}

// Export for use in other files
window.FocusClockCore = FocusClockCore;

// ===========================================
// ⏰ FOCUS CLOCK STORAGE - LOCAL STORAGE MANAGEMENT
// ===========================================

class FocusClockStorage {
    constructor() {
        this.storageKey = 'levelup_focus_clock_settings';
        // timerStateKey and pointsKey removed - database is source of truth
        this.defaultSettings = {
            sittingTime: 20, // 20:10 pattern
            standingTime: 10, // 20:10 pattern
            isFirstTime: true,
            totalCycles: 0,
            lastUsed: null,
            // Audio settings
            audioEnabled: true,
            alertDuration: 'loop', // 'loop', 'once', '10', '20', '30'
            // Volume settings for each alarm type
            volumeAlarm1: 100, // Volume for sitting to standing transition (alarm_1.mp3)
            volumeAlarm2: 100,  // Volume for standing to sitting transition (alarm_2.mp3)
            alarmPreset: DEFAULT_ALARM_PRESET_ID
        };
    }

    // Get user settings from localStorage
    getSettings() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            if (stored) {
                const settings = JSON.parse(stored);
                return { ...this.defaultSettings, ...settings };
            }
        } catch (error) {
            console.warn('Error loading Focus Clock settings:', error);
        }
        return { ...this.defaultSettings };
    }

    // Save user settings to localStorage
    saveSettings(settings) {
        try {
            const currentSettings = this.getSettings();
            const updatedSettings = {
                ...currentSettings,
                ...settings,
                sittingTime: Math.max(1, parseInt(settings.sittingTime) || currentSettings.sittingTime),
                standingTime: Math.max(1, parseInt(settings.standingTime) || currentSettings.standingTime),
                lastUsed: new Date().toISOString()
            };
            localStorage.setItem(this.storageKey, JSON.stringify(updatedSettings));
            return true;
        } catch (error) {
            console.error('Error saving Focus Clock settings:', error);
            return false;
        }
    }

    // Mark as not first time
    markAsConfigured() {
        this.saveSettings({ isFirstTime: false });
    }

    // Update sitting and standing times
    updateTimes(sittingTime, standingTime) {
        return this.saveSettings({
            sittingTime: sittingTime,
            standingTime: standingTime
        });
    }

    // Increment cycle count
    incrementCycles() {
        const settings = this.getSettings();
        return this.saveSettings({
            totalCycles: settings.totalCycles + 1
        });
    }

    // Check if user is first time
    isFirstTimeUser() {
        return this.getSettings().isFirstTime;
    }

    // Get total cycles completed
    getTotalCycles() {
        return this.getSettings().totalCycles;
    }

    // Save volume for specific alarm type
    saveAlarmVolume(alarmType, volume) {
        const volumeKey = alarmType === 'alarm1' ? 'volumeAlarm1' : 'volumeAlarm2';
        const volumePercent = Math.max(0, Math.min(100, parseInt(volume) || 100));
        return this.saveSettings({ [volumeKey]: volumePercent });
    }

    // Get volume for specific alarm type
    getAlarmVolume(alarmType) {
        const settings = this.getSettings();
        const volumeKey = alarmType === 'alarm1' ? 'volumeAlarm1' : 'volumeAlarm2';
        const storedValue = settings[volumeKey];
        if (typeof storedValue === 'number' && !Number.isNaN(storedValue)) {
            return Math.max(0, Math.min(100, storedValue));
        }
        return 100;
    }

    // Timer state removed - cycles now come from database, not localStorage

    // Points caching removed - database is the single source of truth
    // Points are always loaded fresh from the MySQL database via API calls

    // Reset device settings only (localStorage data)
    resetSettings() {
        try {
            console.log('🗑️ Clearing device preferences from localStorage...');
            
            // Clear device-specific preferences only
            localStorage.removeItem(this.storageKey);
            
            // Also clear any legacy cache data
            const keysToCheck = ['levelup_focus_clock_settings', 'levelup_timer_state', 'levelup_points_cache'];
            keysToCheck.forEach(key => {
                if (localStorage.getItem(key)) {
                    console.log(`🗑️ Removing ${key}`);
                    localStorage.removeItem(key);
                }
            });
            
            console.log('✅ Device preferences cleared (database points preserved)');
            return true;
        } catch (error) {
            console.error('Error resetting device settings:', error);
            return false;
        }
    }

    // Export settings for backup
    exportSettings() {
        return this.getSettings();
    }

    // Import settings from backup
    importSettings(settings) {
        if (typeof settings !== 'object' || settings === null) {
            throw new Error('Invalid settings format');
        }

        const validatedSettings = {
            sittingTime: Math.max(1, parseInt(settings.sittingTime) || this.defaultSettings.sittingTime),
            standingTime: Math.max(1, parseInt(settings.standingTime) || this.defaultSettings.standingTime),
            isFirstTime: false,
            totalCycles: Math.max(0, parseInt(settings.totalCycles) || 0)
        };

        return this.saveSettings(validatedSettings);
    }

    // Get usage statistics
    getUsageStats() {
        const settings = this.getSettings();
        const totalSittingTime = settings.totalCycles * settings.sittingTime; // in minutes
        const totalStandingTime = settings.totalCycles * settings.standingTime; // in minutes

        return {
            totalCycles: settings.totalCycles,
            totalSittingTime: totalSittingTime,
            totalStandingTime: totalStandingTime,
            averageSessionLength: settings.sittingTime + settings.standingTime,
            lastUsed: settings.lastUsed
        };
    }

    // Save timer state to localStorage
    saveTimerState(state) {
        try {
            localStorage.setItem('levelup_timer_state', JSON.stringify(state));
        } catch (error) {
            console.error('Error saving timer state:', error);
        }
    }

    // Get timer state from localStorage
    getTimerState() {
        try {
            const stored = localStorage.getItem('levelup_timer_state');
            return stored ? JSON.parse(stored) : null;
        } catch (error) {
            console.warn('Error loading timer state:', error);
            return null;
        }
    }

    // Clear timer state
    clearTimerState() {
        localStorage.removeItem('levelup_timer_state');
    }
}

// Export for use in other files
window.FocusClockStorage = FocusClockStorage;

// ===========================================
// ⏰ FOCUS CLOCK UI - USER INTERFACE MANAGEMENT
// ===========================================

class FocusClockUI {
    constructor() {
        console.log('🏗️ FocusClockUI constructor called');
        // Assign to window immediately so core can access it during initialization
        window.focusClockUI = this;
        
        this.core = new FocusClockCore();
        this.storage = new FocusClockStorage();
        this.core.setStorage(this.storage);
        this.elements = {};
        this.isInitialized = false;
        this.lastCheckedDate = this.getCurrentDateString(); // Track current date for daily reset
        this.dailyCheckIntervalId = null; // Interval for daily reset checks
        this.previewAudio = null;
        this.previewQueue = [];
        this.previewIndex = 0;
        this.previewButton = null;
        this.activePreviewContext = null;
        this.deskControl = (window.LevelUp && window.LevelUp.deskControl) ? window.LevelUp.deskControl : null;
        this.pendingDeskAction = null;

        this.init();
        console.log('✅ FocusClockUI initialization completed');
    }

    // Initialize the Focus Clock UI
    init() {
        // Check if we are on the home page (where the clock UI should be rendered)
        // We check for the existence of the welcome container which is unique to home.blade.php
        // OR if the path is exactly '/' or '/home'
        this.isHomePage = document.querySelector('.welcome-container') !== null || 
                          window.location.pathname === '/' || 
                          window.location.pathname === '/home';
        
        console.log(`Initializing FocusClockUI. isHomePage: ${this.isHomePage}`);

        if (this.isHomePage) {
            this.createHTML();
            this.bindElements();
            this.setupEventListeners();
        }
        
        this.setupCoreCallbacks();
        this.core.preloadAlarmAudio();

        // Check if first time user
        if (this.storage.isFirstTimeUser() && this.isHomePage) {
            this.showSetupModal();
        } else {
            this.loadSavedSettings();
        }

        this.isInitialized = true;
        
        // Start daily reset check (every 60 seconds)
        this.startDailyResetCheck();
    }

    // Create HTML structure
    createHTML() {
        const savedSettings = this.storage ? this.storage.getSettings() : null;
        const selectedPresetId = savedSettings && savedSettings.alarmPreset ? savedSettings.alarmPreset : DEFAULT_ALARM_PRESET_ID;
        const setupAlarmOptionsHtml = renderAlarmPresetSelectOptions(selectedPresetId);
        const settingsAlarmOptionsHtml = renderAlarmPresetSelectOptions(selectedPresetId);
        const setupAlarmSummaryHtml = renderAlarmPresetSummary(selectedPresetId);
        const settingsAlarmSummaryHtml = renderAlarmPresetSummary(selectedPresetId);

        const clockHTML = `
            <!-- Focus Clock Section -->
            <section class="clock-section">
                <div class="clock-container">
                    <div class="clock-header">
                        <div class="clock-cycle-info">
                            <span class="cycle-count" style="color: #3B82F6; font-weight: bold;">
                                <i class="fas fa-calendar-day" style="color: #3B82F6; margin-right: 0.25rem;"></i>
                                Today's Cycles: <span id="todaysCycles" style="font-weight: bold; color: #000000;">0</span>
                            </span>
                            <span class="clock-stat-inline">
                                <i class="fas fa-chair" style="color: #8B5CF6;"></i>
                                <span class="session-type sitting-label" id="sittingLabel" style="font-weight: bold;">Sitting</span>
                                <span class="stat-value" id="sittingTimeInfo">20 min</span>
                            </span>
                            <span class="clock-stat-inline">
                                <i class="fas fa-walking" style="color: #10B981;"></i>
                                <span class="session-type standing-label" id="standingLabel" style="font-weight: bold;">Standing</span>
                                <span class="stat-value" id="standingTimeInfo">10 min</span>
                            </span>
                        </div>
                    </div>

                    <div class="clock-main-content">
                        <!-- Left Side Image -->
                        <img src="/images/sitting-down/sitting_1.png"
                             alt="Sitting Position Left"
                             class="timer-side-image left sitting-image visible"
                             id="leftImage">

                        <div class="clock-display">
                            <div class="timer-progress">
                                <div class="progress-ring">
                                    <svg width="360" height="360" viewBox="0 0 360 360">
                                        <circle cx="180" cy="180" r="170" class="progress-ring-background"/>
                                        <circle cx="180" cy="180" r="170" class="progress-ring-fill" id="progressRing"/>
                                    </svg>
                                </div>
                                <div class="timer-display">
                                    <span class="time-left" id="timeDisplay">20:00</span>
                                    <div class="session-indicator" id="sessionIndicator">
                                        <i class="fas fa-chair session-icon"></i>
                                        <span class="session-label">Sit Down</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side Image -->
                        <img src="/images/sitting-down/sitting_2.png"
                             alt="Sitting Position Right"
                             class="timer-side-image right sitting-image visible"
                             id="rightImage">

                        <div class="clock-controls">
                            <button class="btn-clock btn-start" id="startBtn">
                                <i class="fas fa-play"></i>
                                <span>Start</span>
                            </button>
                            <button class="btn-clock btn-pause" id="pauseBtn" disabled>
                                <i class="fas fa-pause"></i>
                                <span>Pause</span>
                            </button>
                            <button class="btn-clock btn-stop" id="stopBtn" disabled>
                                <i class="fas fa-stop"></i>
                                <span>Reset</span>
                            </button>
                            <button class="btn-clock btn-settings" id="settingsBtn">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </button>
                        </div>


                    </div>
                </div>
            </section>

            <!-- Setup Modal -->
            <div class="clock-modal" id="setupModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>🪑 Desk Timer Setup</h3>
                        <p>Configure your timer and audio settings for better health</p>
                    </div>

                    <div class="modal-body">
                        <div class="time-input-group">
                            <label for="sittingTimeInput">
                                <i class="fas fa-chair"></i>
                                Sitting Time (minutes)
                                <span class="recommended-info">💡 Recommended: 20 minutes (20:10 pattern)</span>
                            </label>
                            <input type="number" id="sittingTimeInput" min="1" max="180" value="20" />
                        </div>

                        <div class="time-input-group">
                            <label for="standingTimeInput">
                                <i class="fas fa-walking"></i>
                                Standing Time (minutes)
                                <span class="recommended-info">💡 Recommended: 10 minutes (20:10 pattern)</span>
                            </label>
                            <input type="number" id="standingTimeInput" min="1" max="60" value="10" />
                        </div>

                        <div class="health-info" id="healthInfo">
                            <div class="health-indicator good">
                                <i class="fas fa-check-circle"></i>
                                <span>Great balance for your health!</span>
                            </div>
                        </div>

                        <!-- Audio Settings Section -->
                        <div class="audio-settings-section" style="margin-top: 1.5rem; padding: 1.35rem 1.45rem; border-radius: 1rem; background: linear-gradient(145deg, #F8FAFC, #EEF2FF); border: 1px solid rgba(99, 102, 241, 0.18);">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
                                <h4 style="margin: 0; color: #1E293B; font-size: 1rem; display: flex; align-items: center; gap: 0.6rem;">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 999px; background: rgba(99, 102, 241, 0.12); color: #4F46E5;">
                                        <i class="fas fa-volume-up"></i>
                                    </span>
                                    Audio Settings
                                </h4>
                                <span style="font-size: 0.78rem; color: #4338CA; background: rgba(79, 70, 229, 0.14); padding: 0.3rem 0.8rem; border-radius: 999px; font-weight: 600; letter-spacing: 0.02em;">
                                    Wellness tuned
                                </span>
                            </div>

                            <div class="audio-setting-group" style="margin-bottom: 0.95rem; padding: 0.85rem 1rem; border-radius: 0.9rem; background: rgba(255, 255, 255, 0.92); border: 1px solid rgba(148, 163, 184, 0.16);">
                                <label class="checkbox-label" style="display: flex; align-items: center; cursor: pointer; gap: 0.6rem; font-weight: 600; color: #1E293B;">
                                    <input type="checkbox" id="enableAudioAlerts" checked style="width: 1.05rem; height: 1.05rem; border-radius: 0.35rem; border: 1px solid #94A3B8;">
                                    Enable Audio Alerts
                                </label>
                                <p style="margin: 0.45rem 0 0 1.65rem; font-size: 0.82rem; color: #64748B;">
                                    Play sounds when it's time to sit or stand.
                                </p>
                            </div>

                            <div id="audioControls" class="audio-controls" style="display: flex; flex-direction: column; gap: 0.9rem;">
                                <div class="audio-setting-group" style="padding: 0.85rem 1rem; border-radius: 0.9rem; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(148, 163, 184, 0.16);">
                                    <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                                        <label for="alertDuration" style="margin: 0; min-width: 140px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #475569;">
                                            Duration
                                        </label>
                                        <select id="alertDuration" style="flex: 1; min-width: 220px; padding: 0.55rem 0.85rem; border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 0.65rem; background: #FFFFFF; font-size: 0.88rem; font-weight: 600; color: #0F172A;">
                                            <option value="loop">Loop until manually stopped</option>
                                            <option value="once">Play once then stop</option>
                                            <option value="10">Play for 10 seconds</option>
                                            <option value="20">Play for 20 seconds</option>
                                            <option value="30">Play for 30 seconds</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="audio-setting-group" style="padding: 0.85rem 1rem; border-radius: 0.9rem; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(148, 163, 184, 0.16);">
                                    <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                                        <label for="setupAlarmPreset" style="margin: 0; min-width: 140px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #475569;">
                                            Alarm Style
                                        </label>
                                        <select id="setupAlarmPreset" style="flex: 1; min-width: 220px; padding: 0.55rem 0.85rem; border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 0.65rem; background: #FFFFFF; font-size: 0.88rem; font-weight: 600; color: #0F172A;">
                                            ${setupAlarmOptionsHtml}
                                        </select>
                                    </div>
                                    <div style="margin-top: 0.55rem; display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                                        <div class="alarm-preset-details" id="setupAlarmPresetDetails" style="display: flex; align-items: center;">
                                            ${setupAlarmSummaryHtml}
                                        </div>
                                        <button type="button" class="btn-modal btn-save" id="setupPreviewAlarm" style="margin: 0; background: linear-gradient(135deg, #2563EB, #1D4ED8); display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.48rem 0.95rem; font-size: 0.82rem; font-weight: 600; border-radius: 999px; letter-spacing: 0.01em;">
                                            <i class="fas fa-headphones"></i>
                                            Preview Alarm
                                        </button>
                                    </div>
                                    <p style="margin: 0.4rem 0 0; font-size: 0.76rem; color: #64748B;">
                                        Preview lets you hear the highlighted option before saving.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn-modal btn-save" id="saveSettingsBtn">
                            <i class="fas fa-check"></i>
                            Start Timer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Settings Modal -->
            <div class="clock-modal" id="settingsModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>⚙️ Timer & Audio Settings</h3>
                        <button class="close-modal" id="closeSettingsBtn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="time-input-group">
                            <label for="editSittingTimeInput">
                                <i class="fas fa-chair"></i>
                                Sitting Work Time (minutes)
                                <span class="recommended-info">💡 Sweet spot: 20-30 minutes for sustained attention</span>
                            </label>
                            <input type="number" id="editSittingTimeInput" min="1" max="180" />
                        </div>

                        <div class="time-input-group">
                            <label for="editStandingTimeInput">
                                <i class="fas fa-walking"></i>
                                Standing Break Time (minutes)
                                <span class="recommended-info">💡 Even 10-15 minutes helps reset your posture and mind</span>
                            </label>
                            <input type="number" id="editStandingTimeInput" min="1" max="60" />
                        </div>

                        <div class="health-info" id="editHealthInfo">
                            <!-- Health info will be populated here -->
                        </div>

                        <!-- Audio Settings Section -->
                        <div class="audio-settings-section" style="margin-top: 1.5rem; padding: 1.35rem 1.45rem; border-radius: 1rem; background: linear-gradient(145deg, #F8FAFC, #EEF2FF); border: 1px solid rgba(99, 102, 241, 0.18);">
                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
                                <h4 style="margin: 0; color: #1E293B; font-size: 1rem; display: flex; align-items: center; gap: 0.6rem;">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 999px; background: rgba(99, 102, 241, 0.12); color: #4F46E5;">
                                        <i class="fas fa-volume-up"></i>
                                    </span>
                                    Audio Settings
                                </h4>
                                <span style="font-size: 0.78rem; color: #4338CA; background: rgba(79, 70, 229, 0.14); padding: 0.3rem 0.8rem; border-radius: 999px; font-weight: 600; letter-spacing: 0.02em;">
                                    Wellness tuned
                                </span>
                            </div>

                            <div class="audio-setting-group" style="margin-bottom: 0.95rem; padding: 0.85rem 1rem; border-radius: 0.9rem; background: rgba(255, 255, 255, 0.92); border: 1px solid rgba(148, 163, 184, 0.16);">
                                <label class="checkbox-label" style="display: flex; align-items: center; cursor: pointer; gap: 0.6rem; font-weight: 600; color: #1E293B;">
                                    <input type="checkbox" id="editEnableAudioAlerts" checked style="width: 1.05rem; height: 1.05rem; border-radius: 0.35rem; border: 1px solid #94A3B8;">
                                    Enable Audio Alerts
                                </label>
                                <p style="margin: 0.45rem 0 0 1.65rem; font-size: 0.82rem; color: #64748B;">
                                    Play sounds when it's time to sit or stand.
                                </p>
                            </div>

                            <div id="editAudioControls" class="audio-controls" style="display: flex; flex-direction: column; gap: 0.9rem;">
                                <div class="audio-setting-group" style="padding: 0.85rem 1rem; border-radius: 0.9rem; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(148, 163, 184, 0.16);">
                                    <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                                        <label for="editAlertDuration" style="margin: 0; min-width: 140px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #475569;">
                                            Duration
                                        </label>
                                        <select id="editAlertDuration" style="flex: 1; min-width: 220px; padding: 0.55rem 0.85rem; border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 0.65rem; background: #FFFFFF; font-size: 0.88rem; font-weight: 600; color: #0F172A;">
                                            <option value="loop">Loop until manually stopped</option>
                                            <option value="once">Play once then stop</option>
                                            <option value="10">Play for 10 seconds</option>
                                            <option value="20">Play for 20 seconds</option>
                                            <option value="30">Play for 30 seconds</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="audio-setting-group" style="padding: 0.85rem 1rem; border-radius: 0.9rem; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(148, 163, 184, 0.16);">
                                    <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                                        <label for="editAlarmPreset" style="margin: 0; min-width: 140px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #475569;">
                                            Alarm Style
                                        </label>
                                        <select id="editAlarmPreset" style="flex: 1; min-width: 220px; padding: 0.55rem 0.85rem; border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 0.65rem; background: #FFFFFF; font-size: 0.88rem; font-weight: 600; color: #0F172A;">
                                            ${settingsAlarmOptionsHtml}
                                        </select>
                                    </div>
                                    <div style="margin-top: 0.55rem; display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                                        <div class="alarm-preset-details" id="editAlarmPresetDetails" style="display: flex; align-items: center;">
                                            ${settingsAlarmSummaryHtml}
                                        </div>
                                        <button type="button" class="btn-modal btn-save" id="editPreviewAlarm" style="margin: 0; background: linear-gradient(135deg, #2563EB, #1D4ED8); display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.48rem 0.95rem; font-size: 0.82rem; font-weight: 600; border-radius: 999px; letter-spacing: 0.01em;">
                                            <i class="fas fa-headphones"></i>
                                            Preview Alarm
                                        </button>
                                    </div>
                                    <p style="margin: 0.4rem 0 0; font-size: 0.76rem; color: #64748B;">
                                        Preview lets you hear the highlighted option before saving.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="danger-zone" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #E5E7EB;">
                            <h4><i class="fas fa-exclamation-triangle"></i> Reset Zone</h4>
                            <button class="btn-danger" id="resetSettingsBtn">
                                <i class="fas fa-trash"></i>
                                Reset All Settings & History
                            </button>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn-modal btn-cancel" id="cancelSettingsBtn">Cancel</button>
                        <button class="btn-modal btn-save" id="updateSettingsBtn">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Insert the HTML between welcome container and footer
        const mainContent = document.querySelector('main.content');
        if (mainContent) {
            mainContent.insertAdjacentHTML('afterend', clockHTML);
        }
    }

    // Bind DOM elements
    bindElements() {
        console.log('🔗 Binding DOM elements...');
        this.elements = {
            // Main elements
            timeDisplay: document.getElementById('timeDisplay'),
            sessionIndicator: document.getElementById('sessionIndicator'),
            todaysCycles: document.getElementById('todaysCycles'),
            progressRing: document.getElementById('progressRing'),

            // Control buttons
            startBtn: document.getElementById('startBtn'),
            pauseBtn: document.getElementById('pauseBtn'),
            stopBtn: document.getElementById('stopBtn'),
            settingsBtn: document.getElementById('settingsBtn'),

            // Stats
            sittingTimeInfo: document.getElementById('sittingTimeInfo'),
            standingTimeInfo: document.getElementById('standingTimeInfo'),
            sittingLabel: document.getElementById('sittingLabel'),
            standingLabel: document.getElementById('standingLabel'),

            // Setup modal
            setupModal: document.getElementById('setupModal'),
            sittingTimeInput: document.getElementById('sittingTimeInput'),
            standingTimeInput: document.getElementById('standingTimeInput'),
            healthInfo: document.getElementById('healthInfo'),
            saveSettingsBtn: document.getElementById('saveSettingsBtn'),
            enableAudioAlerts: document.getElementById('enableAudioAlerts'),
            alertDuration: document.getElementById('alertDuration'),
            setupAlarmPresetSelect: document.getElementById('setupAlarmPreset'),
            setupAlarmPresetDetails: document.getElementById('setupAlarmPresetDetails'),
            setupPreviewAlarmBtn: document.getElementById('setupPreviewAlarm'),

            // Settings modal
            settingsModal: document.getElementById('settingsModal'),
            editSittingTimeInput: document.getElementById('editSittingTimeInput'),
            editStandingTimeInput: document.getElementById('editStandingTimeInput'),
            editHealthInfo: document.getElementById('editHealthInfo'),
            closeSettingsBtn: document.getElementById('closeSettingsBtn'),
            updateSettingsBtn: document.getElementById('updateSettingsBtn'),
            cancelSettingsBtn: document.getElementById('cancelSettingsBtn'),
            resetSettingsBtn: document.getElementById('resetSettingsBtn'),
            editEnableAudioAlerts: document.getElementById('editEnableAudioAlerts'),
            editAlertDuration: document.getElementById('editAlertDuration'),
            editAlarmPresetSelect: document.getElementById('editAlarmPreset'),
            editAlarmPresetDetails: document.getElementById('editAlarmPresetDetails'),
            editPreviewAlarmBtn: document.getElementById('editPreviewAlarm'),


        };
        
        console.log('✅ DOM elements bound:', {
            settingsBtn: !!this.elements.settingsBtn,
            settingsModal: !!this.elements.settingsModal,
            editSittingTimeInput: !!this.elements.editSittingTimeInput,
            editStandingTimeInput: !!this.elements.editStandingTimeInput,
            editEnableAudioAlerts: !!this.elements.editEnableAudioAlerts,
            editAlertDuration: !!this.elements.editAlertDuration,
            setupPreviewAlarmBtn: !!this.elements.setupPreviewAlarmBtn,
            editPreviewAlarmBtn: !!this.elements.editPreviewAlarmBtn
        });
    }

    // Setup event listeners
    setupEventListeners() {
        // Control buttons
        if (this.elements.startBtn) this.elements.startBtn.addEventListener('click', () => this.startTimer());
        if (this.elements.pauseBtn) this.elements.pauseBtn.addEventListener('click', () => this.pauseTimer());
        if (this.elements.stopBtn) this.elements.stopBtn.addEventListener('click', () => this.stopTimer());
        if (this.elements.settingsBtn) {
            this.elements.settingsBtn.addEventListener('click', () => {
                console.log('⚙️ Settings button clicked!');
                
                // Debug: Check what elements are available
                console.log('📊 Available elements check:');
                console.log('- settingsModal:', !!this.elements.settingsModal);
                console.log('- editSittingTimeInput:', !!this.elements.editSittingTimeInput);
                console.log('- editStandingTimeInput:', !!this.elements.editStandingTimeInput);
                console.log('- editEnableAudioAlerts:', !!this.elements.editEnableAudioAlerts);
                console.log('- editAlertDuration:', !!this.elements.editAlertDuration);
                
                // Debug: Check DOM directly
                const modalInDom = document.getElementById('settingsModal');
                console.log('- settingsModal in DOM:', !!modalInDom);
                if (modalInDom) {
                    console.log('- settingsModal classes:', modalInDom.className);
                    console.log('- settingsModal current style:', modalInDom.style.cssText);
                }
                
                try {
                    this.showSettingsModal();
                } catch (error) {
                    console.error('❌ Error opening settings modal:', error);
                    console.error(error.stack);
                }
            });
            console.log('✅ Settings button event listener attached');
        } else {
            // Only log error if we are on home page where button should exist
            if (this.isHomePage) {
                console.error('❌ Settings button element not found during event listener setup');
            }
        }

        // Setup modal
        if (this.elements.saveSettingsBtn) this.elements.saveSettingsBtn.addEventListener('click', () => this.saveInitialSettings());
        if (this.elements.sittingTimeInput) this.elements.sittingTimeInput.addEventListener('input', () => this.validateSetupInputs());
        if (this.elements.standingTimeInput) this.elements.standingTimeInput.addEventListener('input', () => this.validateSetupInputs());
        if (this.elements.enableAudioAlerts) this.elements.enableAudioAlerts.addEventListener('change', () => this.toggleAudioControls());

        if (this.elements.setupAlarmPresetSelect) {
            this.elements.setupAlarmPresetSelect.addEventListener('change', () => {
                this.stopAlarmPreview();
                this.updatePresetDetails('setup');
            });
        }
        if (this.elements.setupPreviewAlarmBtn) {
            this.elements.setupPreviewAlarmBtn.addEventListener('click', () => this.previewSelectedAlarm('setup'));
        }

        // Settings modal
        if (this.elements.closeSettingsBtn) this.elements.closeSettingsBtn.addEventListener('click', () => this.hideSettingsModal());
        if (this.elements.cancelSettingsBtn) this.elements.cancelSettingsBtn.addEventListener('click', () => this.hideSettingsModal());
        if (this.elements.updateSettingsBtn) this.elements.updateSettingsBtn.addEventListener('click', () => this.updateSettings());
        if (this.elements.resetSettingsBtn) this.elements.resetSettingsBtn.addEventListener('click', () => this.resetSettings());
        if (this.elements.editSittingTimeInput) this.elements.editSittingTimeInput.addEventListener('input', () => this.validateEditInputs());
        if (this.elements.editStandingTimeInput) this.elements.editStandingTimeInput.addEventListener('input', () => this.validateEditInputs());
        if (this.elements.editEnableAudioAlerts) this.elements.editEnableAudioAlerts.addEventListener('change', () => this.toggleEditAudioControls());

        if (this.elements.editAlarmPresetSelect) {
            this.elements.editAlarmPresetSelect.addEventListener('change', () => {
                this.stopAlarmPreview();
                this.updatePresetDetails('edit');
            });
        }
        if (this.elements.editPreviewAlarmBtn) {
            this.elements.editPreviewAlarmBtn.addEventListener('click', () => this.previewSelectedAlarm('edit'));
        }



        // Modal backdrop clicks
        if (this.elements.setupModal) {
            this.elements.setupModal.addEventListener('click', (e) => {
                if (e.target === this.elements.setupModal) {
                    // Don't allow closing setup modal by clicking backdrop on first time
                }
            });
        }

        if (this.elements.settingsModal) {
            this.elements.settingsModal.addEventListener('click', (e) => {
                if (e.target === this.elements.settingsModal) {
                    this.hideSettingsModal();
                }
            });
        }

        // Ensure initial preset summaries are accurate
        this.updatePresetDetails('setup');
    }

    // Setup core timer callbacks
    setupCoreCallbacks() {
        this.core.setCallbacks({
            onTick: (timeLeft, isSitting) => this.updateDisplay(timeLeft, isSitting),
            onSessionChange: (isSitting) => this.handleSessionChange(isSitting),
            onCycleComplete: () => this.handleCycleComplete()
        });
    }

    // Load saved settings
    loadSavedSettings() {
        const settings = this.storage.getSettings();
        console.log('📋 Loaded settings:', settings);
        console.log(`⏰ Initializing timer: ${settings.sittingTime} min sitting, ${settings.standingTime} min standing`);
        
        // Ensure minimum values to prevent timer loops
        settings.sittingTime = Math.max(1, settings.sittingTime);
        settings.standingTime = Math.max(1, settings.standingTime);
        
        this.core.initialize(settings.sittingTime, settings.standingTime);
        
        // Try to restore active timer state
        const restored = this.core.restoreState();
        if (restored) {
            console.log('🔄 Restored active timer state');
            this.updateButtonStates(this.core.isRunning);
        }
        
        this.updateStatsDisplay();
        this.updateDisplay(this.core.currentTime, this.core.isSittingSession);

        // Load user's points and today's cycles from backend
        this.loadPointsStatus();
    }

    getSelectedRadioValue(inputs) {
        if (!inputs) {
            return null;
        }
        if (typeof Element !== 'undefined' && inputs instanceof Element) {
            return 'value' in inputs ? inputs.value : null;
        }
        if (typeof inputs.length === 'number') {
            const list = Array.from(inputs);
            const selected = list.find((input) => input.checked || input.selected);
            if (selected && 'value' in selected) {
                return selected.value;
            }
        }
        return null;
    }

    setRadioGroupValue(inputs, value) {
        if (!inputs) {
            return;
        }
        if (typeof Element !== 'undefined' && inputs instanceof Element) {
            if ('value' in inputs) {
                inputs.value = value;
            }
            return;
        }
        if (typeof inputs.length === 'number') {
            Array.from(inputs).forEach((input) => {
                if ('checked' in input) {
                    input.checked = input.value === value;
                }
                if ('value' in input && input.tagName === 'OPTION') {
                    input.selected = input.value === value;
                }
            });
        }
    }

    // Show setup modal for first-time users
    showSetupModal() {
        this.stopAlarmPreview();
        const settings = this.storage.getSettings();
        this.setRadioGroupValue(this.elements.setupAlarmPresetSelect, settings.alarmPreset || DEFAULT_ALARM_PRESET_ID);
        this.updatePresetDetails('setup');
        this.elements.setupModal.style.display = 'flex';
        this.validateSetupInputs();
        this.toggleAudioControls();
    }

    // Hide setup modal
    hideSetupModal() {
        this.stopAlarmPreview();
        this.elements.setupModal.style.display = 'none';
    }

    // Show settings modal
    showSettingsModal() {
        console.log('📝 Opening settings modal...');
        this.stopAlarmPreview();
        try {
            // Check if elements exist
            console.log('Checking elements:', {
                editSittingTimeInput: !!this.elements.editSittingTimeInput,
                editStandingTimeInput: !!this.elements.editStandingTimeInput,
                settingsModal: !!this.elements.settingsModal,
                editEnableAudioAlerts: !!this.elements.editEnableAudioAlerts,
                editAlertDuration: !!this.elements.editAlertDuration
            });
            
            const settings = this.storage.getSettings();
            console.log('Settings loaded:', settings);
            
            // Always refresh the DOM references to ensure they're current
            console.log('🔄 Refreshing DOM element references...');
            
            // Remove existing modal if it exists to force recreation with new design
            const existingModal = document.getElementById('settingsModal');
            if (existingModal) {
                console.log('🗑️ Removing old settings modal to create new compact version');
                existingModal.remove();
            }
            
            // Always create a fresh modal with the new design
            console.log('🏗️ Creating fresh compact settings modal');
            this.createSettingsModal();
            
            // Refresh all element references after creating the modal
            this.elements.settingsModal = document.getElementById('settingsModal');
            this.elements.editSittingTimeInput = document.getElementById('editSittingTimeInput');
            this.elements.editStandingTimeInput = document.getElementById('editStandingTimeInput');
            this.elements.editEnableAudioAlerts = document.getElementById('editEnableAudioAlerts');
            this.elements.editAlertDuration = document.getElementById('editAlertDuration');
            this.elements.editHealthInfo = document.getElementById('editHealthInfo');
            this.elements.closeSettingsBtn = document.getElementById('closeSettingsBtn');
            this.elements.updateSettingsBtn = document.getElementById('updateSettingsBtn');
            this.elements.cancelSettingsBtn = document.getElementById('cancelSettingsBtn');
            this.elements.resetSettingsBtn = document.getElementById('resetSettingsBtn');
            this.elements.editAlarmPresetSelect = document.getElementById('editAlarmPreset');
            this.elements.editAlarmPresetDetails = document.getElementById('editAlarmPresetDetails');
            this.elements.editPreviewAlarmBtn = document.getElementById('editPreviewAlarm');
            
            if (!this.elements.settingsModal) {
                console.error('❌ Failed to create settings modal');
                alert('Failed to create settings modal. Please refresh the page and try again.');
                return;
            }
            
            console.log('✅ Fresh DOM elements obtained:', {
                settingsModal: !!this.elements.settingsModal,
                editSittingTimeInput: !!this.elements.editSittingTimeInput,
                editStandingTimeInput: !!this.elements.editStandingTimeInput,
                editEnableAudioAlerts: !!this.elements.editEnableAudioAlerts,
                editAlertDuration: !!this.elements.editAlertDuration,
                editHealthInfo: !!this.elements.editHealthInfo
            });
            
            // Load timer settings
            this.elements.editSittingTimeInput.value = settings.sittingTime;
            this.elements.editStandingTimeInput.value = settings.standingTime;
            
            // Load audio settings
            this.elements.editEnableAudioAlerts.checked = settings.audioEnabled;
            this.elements.editAlertDuration.value = settings.alertDuration;
            this.setRadioGroupValue(this.elements.editAlarmPresetSelect, settings.alarmPreset || DEFAULT_ALARM_PRESET_ID);
            this.updatePresetDetails('edit');
            
            // Force show the modal with important styles
            this.elements.settingsModal.style.cssText = 'display: flex !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 10000 !important; background: rgba(0,0,0,0.7) !important; justify-content: center !important; align-items: center !important;';
            
            // Also force show the modal content
            const modalContent = this.elements.settingsModal.querySelector('.modal-content');
            if (modalContent) {
                modalContent.style.cssText = 'display: block !important; visibility: visible !important; opacity: 1 !important; position: relative !important; z-index: 10001 !important; background: white !important; padding: 2rem !important; border-radius: 8px !important; max-width: 500px !important; width: 90% !important;';
                console.log('✅ Modal content also forced to show');
            }
            
            console.log('✅ Settings modal forced to show with inline styles');
            
            // Double-check if modal is actually visible
            setTimeout(() => {
                const computedStyle = window.getComputedStyle(this.elements.settingsModal);
                console.log('Settings modal computed display:', computedStyle.display);
                console.log('Settings modal visibility:', computedStyle.visibility);
                console.log('Settings modal z-index:', computedStyle.zIndex);
            }, 100);
            
            // Re-attach event listeners to fresh elements
            this.attachSettingsModalListeners();
            
            // Update health info and audio controls based on current values
            this.validateEditInputs();
            this.toggleEditAudioControls();
        } catch (error) {
            console.error('Error in showSettingsModal:', error);
        }
    }

    // Create settings modal dynamically if it doesn't exist
    createSettingsModal() {
        console.log('🏗️ Creating settings modal dynamically...');
        const savedSettings = this.storage ? this.storage.getSettings() : null;
        const selectedPresetId = savedSettings && savedSettings.alarmPreset ? savedSettings.alarmPreset : DEFAULT_ALARM_PRESET_ID;
        const settingsAlarmOptionsHtml = renderAlarmPresetSelectOptions(selectedPresetId);
        const settingsAlarmSummaryHtml = renderAlarmPresetSummary(selectedPresetId);
        
        const modalHtml = `
            <div class="clock-modal" id="settingsModal" style="display: none;">
                <div class="modal-content" style="max-width: 450px;">
                    <div class="modal-header" style="padding-bottom: 0.75rem; position: relative;">
                        <button class="close-modal" id="closeSettingsBtn" style="position: absolute; top: 0; right: 0; background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #6B7280; padding: 0.25rem; line-height: 1;">
                            <i class="fas fa-times"></i>
                        </button>
                        <h3>⚙️ Settings</h3>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem;">Update your timer and audio preferences</p>
                    </div>

                    <div class="modal-body" style="padding: 1rem 0;">
                        <!-- Timer Settings in Horizontal Layout -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                            <div class="time-input-group" style="margin-bottom: 0;">
                                <label for="editSittingTimeInput">
                                    <i class="fas fa-chair"></i>
                                    Sitting (minutes)
                                    <span class="recommended-info">💡 20 min</span>
                                </label>
                                <input type="number" id="editSittingTimeInput" min="1" max="180" value="20" />
                            </div>

                            <div class="time-input-group" style="margin-bottom: 0;">
                                <label for="editStandingTimeInput">
                                    <i class="fas fa-walking"></i>
                                    Standing (minutes)
                                    <span class="recommended-info">💡 10 min</span>
                                </label>
                                <input type="number" id="editStandingTimeInput" min="1" max="60" value="10" />
                            </div>
                        </div>

                        <div class="health-info" id="editHealthInfo" style="margin-bottom: 1rem;">
                            <div class="health-indicator good">
                                <i class="fas fa-check-circle"></i>
                                <span>Great balance for your health!</span>
                            </div>
                        </div>

                        <!-- Audio Settings Section -->
                        <div class="audio-settings-section" style="margin-top: 1rem; padding: 1rem 1.1rem; border-radius: 0.95rem; background: linear-gradient(150deg, #F8FAFC, #EEF2FF); border: 1px solid rgba(99, 102, 241, 0.18);">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.85rem;">
                                <h4 style="margin: 0; color: #1E293B; font-size: 0.95rem; display: flex; align-items: center; gap: 0.55rem;">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.8rem; height: 1.8rem; border-radius: 999px; background: rgba(99, 102, 241, 0.12); color: #4F46E5;">
                                        <i class="fas fa-volume-up"></i>
                                    </span>
                                    Audio
                                </h4>
                                <span style="font-size: 0.7rem; color: #4338CA; background: rgba(79, 70, 229, 0.15); padding: 0.28rem 0.75rem; border-radius: 999px; font-weight: 600; letter-spacing: 0.02em;">
                                    Refined alerts
                                </span>
                            </div>

                            <div class="audio-setting-group" style="margin-bottom: 0.8rem; padding: 0.75rem 0.85rem; border-radius: 0.85rem; background: rgba(255, 255, 255, 0.92); border: 1px solid rgba(148, 163, 184, 0.16);">
                                <label class="checkbox-label" style="display: flex; align-items: center; cursor: pointer; gap: 0.5rem; font-weight: 600; color: #1E293B;">
                                    <input type="checkbox" id="editEnableAudioAlerts" checked style="width: 1rem; height: 1rem; border-radius: 0.3rem; border: 1px solid #94A3B8;">
                                    Enable Alerts
                                </label>
                                <p style="margin: 0.35rem 0 0 1.45rem; font-size: 0.78rem; color: #64748B;">
                                    Play sounds for transitions.
                                </p>
                            </div>

                            <div id="editAudioControls" class="audio-controls" style="display: flex; flex-direction: column; gap: 0.8rem;">
                                <div class="audio-setting-group" style="padding: 0.8rem 0.85rem; border-radius: 0.85rem; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(148, 163, 184, 0.16);">
                                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                        <label for="editAlertDuration" style="margin: 0; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #475569;">
                                            Duration
                                        </label>
                                        <select id="editAlertDuration" style="flex: 1; min-width: 200px; padding: 0.5rem 0.75rem; border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 0.6rem; background: #FFFFFF; font-size: 0.85rem; font-weight: 600; color: #0F172A;">
                                            <option value="loop">Loop until manually stopped</option>
                                            <option value="once">Play once then stop</option>
                                            <option value="10">Play for 10 seconds</option>
                                            <option value="20">Play for 20 seconds</option>
                                            <option value="30">Play for 30 seconds</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="audio-setting-group" style="padding: 0.8rem 0.85rem; border-radius: 0.85rem; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(148, 163, 184, 0.16);">
                                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                        <label for="editAlarmPreset" style="margin: 0; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #475569;">
                                            Alarm Style
                                        </label>
                                        <select id="editAlarmPreset" style="flex: 1; min-width: 200px; padding: 0.5rem 0.75rem; border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 0.6rem; background: #FFFFFF; font-size: 0.85rem; font-weight: 600; color: #0F172A;">
                                            ${settingsAlarmOptionsHtml}
                                        </select>
                                    </div>
                                    <div style="margin-top: 0.45rem; display: flex; align-items: center; gap: 0.55rem; flex-wrap: wrap;">
                                        <div class="alarm-preset-details" id="editAlarmPresetDetails" style="display: flex; align-items: center;">
                                            ${settingsAlarmSummaryHtml}
                                        </div>
                                        <button type="button" class="btn-modal btn-save" id="editPreviewAlarm" style="margin: 0; background: linear-gradient(135deg, #2563EB, #1D4ED8); display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.85rem; font-size: 0.8rem; font-weight: 600; border-radius: 999px; letter-spacing: 0.01em;">
                                            <i class="fas fa-headphones"></i>
                                            Preview Alarm
                                        </button>
                                    </div>
                                    <p style="margin: 0.35rem 0 0; font-size: 0.74rem; color: #64748B;">
                                        Preview lets you hear the highlighted option before saving.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer" style="display: flex; gap: 0.75rem; padding-top: 1.25rem; border-top: 1px solid #E2E8F0; margin-top: 0.5rem;">
                        <button class="btn-modal btn-save" id="updateSettingsBtn" style="flex: 1; padding: 0.7rem 1rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; background-color: #10B981; color: white; border: none; cursor: pointer; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
                            <i class="fas fa-save"></i>
                            Save
                        </button>
                        <button class="btn-danger" id="resetSettingsBtn" style="flex: 1; padding: 0.7rem 1rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; background-color: #EF4444; color: white; border: none; cursor: pointer; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
                            <i class="fas fa-trash-alt"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Add the modal to the document body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        console.log('✅ Settings modal created and added to DOM');
    }

    // Attach event listeners specifically for settings modal
    attachSettingsModalListeners() {
        if (this.elements.closeSettingsBtn) {
            // Remove existing listeners to prevent duplicates
            this.elements.closeSettingsBtn.removeEventListener('click', this.hideSettingsModal.bind(this));
            this.elements.closeSettingsBtn.addEventListener('click', this.hideSettingsModal.bind(this));
        }
        
        if (this.elements.updateSettingsBtn) {
            this.elements.updateSettingsBtn.removeEventListener('click', this.updateSettings.bind(this));
            this.elements.updateSettingsBtn.addEventListener('click', this.updateSettings.bind(this));
        }
        
        if (this.elements.resetSettingsBtn) {
            this.elements.resetSettingsBtn.removeEventListener('click', this.resetSettings.bind(this));
            this.elements.resetSettingsBtn.addEventListener('click', this.resetSettings.bind(this));
        }
        
        if (this.elements.editEnableAudioAlerts) {
            this.elements.editEnableAudioAlerts.removeEventListener('change', this.toggleEditAudioControls.bind(this));
            this.elements.editEnableAudioAlerts.addEventListener('change', this.toggleEditAudioControls.bind(this));
        }
        
        // Add input event listeners for real-time health info updates
        if (this.elements.editSittingTimeInput) {
            this.elements.editSittingTimeInput.removeEventListener('input', this.validateEditInputs.bind(this));
            this.elements.editSittingTimeInput.addEventListener('input', this.validateEditInputs.bind(this));
        }
        
        if (this.elements.editStandingTimeInput) {
            this.elements.editStandingTimeInput.removeEventListener('input', this.validateEditInputs.bind(this));
            this.elements.editStandingTimeInput.addEventListener('input', this.validateEditInputs.bind(this));
        }

        if (this.elements.editAlarmPresetSelect) {
            this.elements.editAlarmPresetSelect.addEventListener('change', () => {
                this.stopAlarmPreview();
                this.updatePresetDetails('edit');
            });
        }

        if (this.elements.editPreviewAlarmBtn) {
            this.elements.editPreviewAlarmBtn.addEventListener('click', () => this.previewSelectedAlarm('edit'));
        }
        
        if (this.elements.settingsModal) {
            this.elements.settingsModal.addEventListener('click', (e) => {
                if (e.target === this.elements.settingsModal) {
                    this.hideSettingsModal();
                }
            });
        }

        console.log('✅ Settings modal event listeners attached');

        this.updatePresetDetails('edit');
    }

    // Hide settings modal
    hideSettingsModal() {
        this.stopAlarmPreview();
        if (this.elements.settingsModal) {
            this.elements.settingsModal.style.display = 'none';
            console.log('✅ Settings modal hidden');
        } else {
            console.warn('⚠️ Cannot hide settings modal - element not found');
        }
    }

    // Validate setup inputs (no strict minimums)
    validateSetupInputs() {
        const sittingTime = parseInt(this.elements.sittingTimeInput.value) || 20;
        const standingTime = parseInt(this.elements.standingTimeInput.value) || 10;

        // Ensure positive values
        if (sittingTime < 1) this.elements.sittingTimeInput.value = 1;
        if (standingTime < 1) this.elements.standingTimeInput.value = 1;

        this.updateHealthInfo(sittingTime, standingTime, this.elements.healthInfo);
    }

    // Validate edit inputs (no strict minimums)
    validateEditInputs() {
        const sittingTime = parseInt(this.elements.editSittingTimeInput?.value) || 20;
        const standingTime = parseInt(this.elements.editStandingTimeInput?.value) || 10;

        console.log('🔍 validateEditInputs called:', {
            sittingTime,
            standingTime,
            editHealthInfo: !!this.elements.editHealthInfo
        });

        if (this.elements.editHealthInfo) {
            this.updateHealthInfo(sittingTime, standingTime, this.elements.editHealthInfo);
        } else {
            console.warn('⚠️ editHealthInfo element not found, cannot update health indicator');
        }
    }

    // Update health info display
    updateHealthInfo(sittingTime, standingTime, container) {
        const validation = FocusClockCore.validateHealthyRatio(sittingTime, standingTime);

        container.innerHTML = `
            <div class="health-indicator ${validation.level}">
                <i class="fas fa-${validation.isHealthy ? 'check-circle' : (validation.level === 'warning' ? 'exclamation-triangle' : 'info-circle')}"></i>
                <span>${validation.recommendation}</span>
            </div>
        `;
    }

    // Save initial settings from setup modal
    saveInitialSettings() {
        const sittingTime = Math.max(1, parseInt(this.elements.sittingTimeInput.value) || 20);
        const standingTime = Math.max(1, parseInt(this.elements.standingTimeInput.value) || 10);
        const audioEnabled = this.elements.enableAudioAlerts.checked;
        const alertDuration = this.elements.alertDuration.value;
        const alarmPreset = this.getSelectedRadioValue(this.elements.setupAlarmPresetSelect) || DEFAULT_ALARM_PRESET_ID;

        // Save timer settings
        this.storage.updateTimes(sittingTime, standingTime);
        
        // Save audio settings
        this.storage.saveSettings({
            audioEnabled: audioEnabled,
            alertDuration: alertDuration,
            alarmPreset: alarmPreset
        });
        
        this.storage.markAsConfigured();
        this.core.initialize(sittingTime, standingTime);

        this.stopAlarmPreview();
        this.hideSetupModal();
        this.updateStatsDisplay();
        this.updateDisplay(sittingTime * 60, true);
        
        // Load points from database after setup completion
        this.loadPointsStatus();
        
        // Auto-start the timer after initial setup
        this.startTimer();
    }

    // Update settings from settings modal
    updateSettings() {
        const sittingTime = Math.max(1, parseInt(this.elements.editSittingTimeInput.value) || 20);
        const standingTime = Math.max(1, parseInt(this.elements.editStandingTimeInput.value) || 10);
        const audioEnabled = this.elements.editEnableAudioAlerts.checked;
        const alertDuration = this.elements.editAlertDuration.value;
        const alarmPreset = this.getSelectedRadioValue(this.elements.editAlarmPresetSelect) || DEFAULT_ALARM_PRESET_ID;

        // Save timer settings
        this.storage.updateTimes(sittingTime, standingTime);
        
        // Save audio settings
        this.storage.saveSettings({
            audioEnabled: audioEnabled,
            alertDuration: alertDuration,
            alarmPreset: alarmPreset
        });
        
        this.core.updateTimes(sittingTime, standingTime);

        this.stopAlarmPreview();
        this.hideSettingsModal();
        this.updateStatsDisplay();
    }

    // Reset all settings
    resetSettings() {
        this.stopAlarmPreview();
        if (confirm('⚠️ Reset Device Settings?\n\nThis will clear:\n• Timer settings (back to 20:10)\n• Volume preferences\n• Audio settings\n\n(Your points and cycles in database will NOT be affected)\n\nAre you sure?')) {
            // Stop any running timer
            this.core.pause();
            
            // Reset storage (clears only localStorage - device preferences)
            this.storage.resetSettings();
            
            // Reset core to defaults
            this.core.initialize(20, 10);
            
            console.log('🧹 Cleared device settings and preferences');
            
            // Update UI to defaults
            this.updateStatsDisplay();
            this.updateDisplay(20 * 60, true);
            this.updateButtonStates(false);
            
            // Hide modal first
            this.hideSettingsModal();
            
            // Check if user is now first-time and show setup modal
            if (this.storage.isFirstTimeUser()) {
                console.log('🔄 User is now first-time after reset - showing setup modal');
                setTimeout(() => {
                    this.showSetupModal();
                }, 300);
            } else {
                // If not first-time, reload points and cycles from database
                setTimeout(() => {
                    this.loadPointsStatus();
                }, 200);
                
                // Show confirmation
                setTimeout(() => {
                    alert('✅ Device settings reset!\n\nYour points and cycles in the database are preserved.\n\nToday\'s cycles will reload from database.');
                }, 100);
            }
            
            console.log('🔄 Device settings reset, database points preserved');
        }
    }

    // Timer control methods
    startTimer() {
        console.log('▶️ Starting timer...');
        const state = this.core.getCurrentSession();
        console.log('Starting state:', state);
        this.core.start();
        this.syncDeskPosition(this.core.isSittingSession);
        
        // Notify server about resume
        this.sendPauseState(false);
        
        this.updateButtonStates(true);
    }

    pauseTimer() {
        this.core.pause();
        
        // Notify server about pause
        this.sendPauseState(true);
        
        this.updateButtonStates(false);
    }

    stopTimer() {
        this.core.stop();
        this.updateButtonStates(false);
    }

    // Update button states
    updateButtonStates(isRunning) {
        const hasPausedTime = !isRunning && this.core.currentTime < this.core.sessionDuration;
        if (this.elements.startBtn) this.elements.startBtn.disabled = isRunning;
        if (this.elements.pauseBtn) this.elements.pauseBtn.disabled = !isRunning;
        if (this.elements.stopBtn) this.elements.stopBtn.disabled = !isRunning && !hasPausedTime; // Enable reset when paused
    }

    // Update main display
    updateDisplay(timeLeft, isSitting) {
        console.log(`Display update - Time: ${FocusClockCore.formatTime(timeLeft)}, Session: ${isSitting ? 'Sitting' : 'Standing'}`);
        
        // Only update UI elements if they exist (i.e., we are on the home page)
        if (!this.elements.timeDisplay) {
            return;
        }

        // Update time display
        this.elements.timeDisplay.textContent = FocusClockCore.formatTime(timeLeft);

        // Update session indicator
        const icon = this.elements.sessionIndicator.querySelector('.session-icon');
        const label = this.elements.sessionIndicator.querySelector('.session-label');

        if (isSitting) {
            icon.className = 'fas fa-chair session-icon';
            label.textContent = 'Sit Down';
        } else {
            icon.className = 'fas fa-walking session-icon';
            label.textContent = 'Stand Up';
        }

        // Update header session labels based on current session
        if (this.elements.sittingLabel && this.elements.standingLabel) {
            if (isSitting) {
                // Currently sitting - make sitting text purple, standing text default
                this.elements.sittingLabel.style.color = '#8B5CF6'; // Purple
                this.elements.standingLabel.style.color = '#374151'; // Default gray
            } else {
                // Currently standing - make standing text green, sitting text default
                this.elements.sittingLabel.style.color = '#374151'; // Default gray
                this.elements.standingLabel.style.color = '#10B981'; // Green
            }
        }

        // Update side images based on session
        this.updateSideImages(isSitting);

        // Update progress ring
        this.updateProgressRing(timeLeft, isSitting);

        // Update container class for styling
        const container = document.querySelector('.clock-container');
        if (container) {
            container.className = `clock-container ${isSitting ? 'sitting-session' : 'standing-session'}`;
        }
    }

    // Update side images
    updateSideImages(isSitting) {
        const leftImage = document.getElementById('leftImage');
        const rightImage = document.getElementById('rightImage');

        if (leftImage && rightImage) {
            // Instantly switch images without fade animation
            if (isSitting) {
                leftImage.src = "/images/sitting-down/sitting_1.png";
                leftImage.alt = "Sitting Position Left";
                rightImage.src = "/images/sitting-down/sitting_2.png";
                rightImage.alt = "Sitting Position Right";
            } else {
                leftImage.src = "/images/standing-up/standing_1.png";
                leftImage.alt = "Standing Position Left";
                rightImage.src = "/images/standing-up/standing_2.png";
                rightImage.alt = "Standing Position Right";
            }
        }
    }

    // Update progress ring
    updateProgressRing(timeLeft, isSitting) {
        if (!this.elements.progressRing) return;

        const totalTime = isSitting ? this.core.sittingTime * 60 : this.core.standingTime * 60;
        const progress = ((totalTime - timeLeft) / totalTime) * 100;
        const circumference = 2 * Math.PI * 170; // radius = 170
        const offset = circumference - (progress / 100) * circumference;

        this.elements.progressRing.style.strokeDasharray = circumference;
        this.elements.progressRing.style.strokeDashoffset = offset;
    }

    // Handle session change
    handleSessionChange(isSitting) {
        console.log(`🎯 handleSessionChange called with: ${isSitting ? 'Sitting' : 'Standing'}`);
        this.syncDeskPosition(isSitting);
    }

    // Centralized method that calls sit or stand
    syncDeskPosition(isSitting) {
        if (!this.deskControl || !this.deskControl.enabled) {
            return;
        }

        const action = isSitting ? 'sit' : 'stand';

        if (this.pendingDeskAction === action) {
            return;
        }

        this.pendingDeskAction = action;
        this.sendDeskMoveCommand(action)
            .catch(error => {
                console.error(`❌ Desk ${action} command failed:`, error);
            })
            .finally(() => {
                this.pendingDeskAction = null;
            });
    }

    async sendDeskMoveCommand(action) {
        if (!this.deskControl) {
            return;
        }

        const url = action === 'sit' ? this.deskControl?.sitUrl : this.deskControl?.standUrl;

        if (!url) {
            console.warn(`⚠️ Missing desk control URL for ${action} command`);
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        console.log(`📡 Sending desk ${action} command to simulator`);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({})
        });

        if (!response.ok) {
            const errorBody = await response.text();
            throw new Error(`HTTP ${response.status}: ${errorBody || response.statusText}`);
        }

        try {
            const data = await response.json();
            console.log(`🪑 Desk ${action} command acknowledged`, data);
        } catch (error) {
            console.log(`🪑 Desk ${action} command completed (no JSON body)`);
        }
    }

    // Handle cycle completion
    async handleCycleComplete() {
        console.log('🔄 Cycle completed');

        // Submit cycle to backend for scoring (this will increment database count and return all updated data)
        await this.submitHealthCycle();

        // No need for additional loadPointsStatus() call since submitHealthCycle now updates everything
    }

    // Submit completed health cycle to backend
    async submitHealthCycle() {
        const settings = this.storage.getSettings();

        // Get current cycle count from database for submission
        let cycleNumber = 1; // Default for first cycle
        try {
            // Get user's current date in their LOCAL timezone (not UTC)
            const now = new Date();
            const userDate = now.getFullYear() + '-' + 
                           String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(now.getDate()).padStart(2, '0');
            const statusResponse = await fetch(`/api/health-cycle/points-status?user_date=${userDate}`);
            const statusData = await statusResponse.json();
            cycleNumber = (statusData.todays_cycles || 0) + 1; // Next cycle number
        } catch (error) {
            console.log('Could not get current cycle count, using default');
        }

        console.log('🚀 Submitting health cycle:', {
            sitting_minutes: settings.sittingTime,
            standing_minutes: settings.standingTime,
            cycle_number: cycleNumber
        });

        try {
            // Get user's current date in their LOCAL timezone (not UTC)
            const now = new Date();
            const userDate = now.getFullYear() + '-' + 
                           String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(now.getDate()).padStart(2, '0');
            
            const response = await fetch('/api/health-cycle/complete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({
                    sitting_minutes: settings.sittingTime,
                    standing_minutes: settings.standingTime,
                    cycle_number: cycleNumber,
                    user_date: userDate
                })
            });

            console.log('📡 API Response status:', response.status);
            const data = await response.json();
            console.log('📊 API Response data:', data);

            // Check if authentication is required
            if (data.requires_auth || response.status === 401) {
                this.showLoginPrompt(data);
            } else if (data.success) {
                this.showPointsFeedback(data);
            } else if (data.health_score !== undefined) {
                // Handle daily limit reached case
                this.showPointsFeedback(data);
            } else {
                // Fallback to login prompt for any other case
                this.showLoginPrompt(data);
            }
        } catch (error) {
            console.log('Points system unavailable (not logged in or network error):', error);
            // Show login prompt with custom message for errors
            this.showLoginPrompt({
                message: 'Please log in to track your desk health cycles and earn points.'
            });
        }
    }

    // Update points display in navbar
    updatePointsDisplay(totalPoints, dailyPoints) {
        console.log('🔧 updatePointsDisplay called with:', { totalPoints, dailyPoints });
        
        const totalPointsEl = document.getElementById('totalPoints');

        if (totalPointsEl) {
            totalPointsEl.textContent = String(totalPoints ?? 0);
            console.log('✅ Updated totalPoints element to:', String(totalPoints ?? 0));
        } else {
            console.warn('⚠️ totalPoints element not found in navbar');
        }

        // Daily points display has been removed from UI
        console.log('📊 Points display updated from database:', { totalPoints, dailyPoints });
    }

    // Update today's cycles display
    updateTodaysCyclesDisplay(todaysCycles) {
        console.log('🔧 updateTodaysCyclesDisplay called with:', todaysCycles);
        console.log('🔧 Type of todaysCycles:', typeof todaysCycles);
        
        if (this.elements.todaysCycles) {
            console.log('🔧 Current element content before update:', this.elements.todaysCycles.textContent);
            this.elements.todaysCycles.textContent = todaysCycles;
            console.log('📅 Today\'s cycles updated from database:', todaysCycles);
            console.log('🔧 Element content after update:', this.elements.todaysCycles.textContent);
            
            // Double-check the element exists and has the right content
            const elementCheck = document.getElementById('todaysCycles');
            if (elementCheck) {
                console.log('🔧 Double-check: element found with content:', elementCheck.textContent);
            }
        } else {
            console.log('🔧 this.elements.todaysCycles is null, trying direct DOM access');
            // If element not bound yet, try to find it directly
            const todaysCyclesEl = document.getElementById('todaysCycles');
            if (todaysCyclesEl) {
                console.log('🔧 Found element directly, current content:', todaysCyclesEl.textContent);
                todaysCyclesEl.textContent = todaysCycles;
                console.log('📅 Today\'s cycles updated directly from DOM:', todaysCycles);
                console.log('🔧 Element content after direct update:', todaysCyclesEl.textContent);
            } else {
                console.warn('⚠️ todaysCycles element not found in DOM at all');
                console.log('🔧 Available elements with id:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));
            }
        }
    }

        // Setup popup event listeners
    setupPopupEventListeners(popup) {
        if (!popup) {
            console.warn('No popup provided to setupPopupEventListeners');
            return;
        }

        const stopBtn = popup.querySelector('.stop-alarm-btn');
        if (!stopBtn) {
            console.warn('Stop button not found in popup - available classes:', popup.innerHTML);
            return;
        }

        console.log('✅ Stop button found, setting up event listener');
        
        // Simple click handler to stop alarm
        stopBtn.addEventListener('click', (e) => {
            console.log('🔴 Stop button clicked!');
            e.preventDefault();
            e.stopPropagation();
            this.cleanupAlarmAndPopup();
        });
    }

    // Show points feedback notification
    showPointsFeedback(data) {
        // Remove any existing notifications first
        this.removeExistingNotifications();

        // Log the data for debugging
        console.log('🎯 Showing points feedback with data:', {
            total: data.total_points,
            daily: data.daily_points,
            earned: data.points_earned,
            cycles: data.todays_cycles
        });
        
        // IMPORTANT: Log what we're about to pass to updatePointsDisplay
        console.log('🔍 About to update points display with:', {
            totalPoints: data.total_points,
            dailyPoints: data.daily_points
        });
        
        // Update points display with the latest data from API response
        this.updatePointsDisplay(data.total_points, data.daily_points);
        
        // Update today's cycles if provided in the response
        if (data.todays_cycles !== undefined) {
            this.updateTodaysCyclesDisplay(data.todays_cycles);
        }
        
        // Show detailed feedback notification only if we have valid data
        console.log('🏆 Health Cycle Complete!', {
            healthScore: data.health_score,
            pointsEarned: data.points_earned,
            feedback: data.feedback,
            totalPoints: data.total_points,
            dailyPoints: data.daily_points
        });
        
        // Only show feedback if we have valid health score AND points data
        if (data.health_score !== undefined && data.total_points !== undefined && data.points_earned !== undefined) {
            const notification = document.createElement('div');
            notification.className = 'points-notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                border-radius: 8px;
                padding: 1rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                min-width: 300px;
                border-left: 4px solid ${data.color === 'green' ? '#10B981' : data.color === 'yellow' ? '#F59E0B' : data.color === 'orange' ? '#F97316' : '#EF4444'};
            `;
            
            // Simplified notification - show only points earned to avoid stale data
            const isLimitReached = data.daily_limit_reached || data.daily_points >= 100;
            
            notification.innerHTML = `
                <div style="position: relative;">
                    <button class="dismiss-notification" style="
                        position: absolute;
                        top: -0.5rem;
                        right: -0.5rem;
                        background: #6B7280;
                        color: white;
                        border: none;
                        border-radius: 50%;
                        width: 24px;
                        height: 24px;
                        cursor: pointer;
                        font-size: 0.8rem;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: background-color 0.2s;
                        z-index: 1;
                    " onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#6B7280'">×</button>
                    
                    <div style="font-weight: 600; margin-bottom: 0.5rem;">Cycle Complete!</div>
                    <div style="font-size: 0.9rem; margin-bottom: 0.5rem;">
                        Health Score: <strong>${data.health_score}/100</strong>
                    </div>
                    <div style="font-size: 0.9rem; margin-bottom: 0.5rem;">
                        Points Earned: <strong>+${data.points_earned}</strong> ${isLimitReached ? '🏆 Daily limit reached!' : ''}
                    </div>
                    <div style="font-size: 0.85rem; color: #6B7280;">
                        ${data.feedback || 'Cycle completed successfully!'}
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Add click event to dismiss button
            const dismissBtn = notification.querySelector('.dismiss-notification');
            let autoRemoveTimeout;
            
            if (dismissBtn) {
                dismissBtn.addEventListener('click', () => {
                    if (autoRemoveTimeout) {
                        clearTimeout(autoRemoveTimeout);
                    }
                    if (notification && notification.parentNode) {
                        notification.remove();
                    }
                });
            }
            
            // Auto-remove after 8 seconds (increased time so users can read it)
            autoRemoveTimeout = setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 8000);
        }
    }

    // Remove any existing notifications
    removeExistingNotifications() {
        // Remove all existing notifications
        const existingNotifications = document.querySelectorAll('.login-prompt-notification, .points-notification');
        existingNotifications.forEach(notification => {
            if (notification && notification.parentNode) {
                notification.remove();
            }
        });
    }

    // Show login prompt for users not logged in
    showLoginPrompt(data) {
        console.log('🔐 Showing login prompt - user not logged in or test user not found');
        
        // Remove any existing notifications first
        this.removeExistingNotifications();

        // Show persistent notification asking user to log in
        const notification = document.createElement('div');
        notification.className = 'login-prompt-notification';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            min-width: 350px;
            border-left: 4px solid #3B82F6;
        `;

        notification.innerHTML = `
            <div style="position: relative;">
                <button class="dismiss-login-prompt" style="
                    position: absolute;
                    top: -0.5rem;
                    right: -0.5rem;
                    background: #6B7280;
                    color: white;
                    border: none;
                    border-radius: 50%;
                    width: 24px;
                    height: 24px;
                    cursor: pointer;
                    font-size: 0.8rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: background-color 0.2s;
                    z-index: 1;
                " onmouseover="this.style.background='#374151'" onmouseout="this.style.background='#6B7280'">×</button>

                <div style="font-weight: 600; margin-bottom: 0.5rem; color: #3B82F6;">
                    <i class="fas fa-user-lock" style="margin-right: 0.5rem;"></i>Login Required
                </div>
                <div style="font-size: 0.9rem; margin-bottom: 1rem; color: #4B5563;">
                    Sign in to track your progress and earn points for maintaining a healthy work pattern.
                </div>
                <div style="font-size: 0.85rem; color: #DC2626; font-weight: 500;">
                    Your cycles will not be counted until you log in.
                </div>
            </div>
        `;

        document.body.appendChild(notification);

        // Add click event to dismiss button
        const dismissBtn = notification.querySelector('.dismiss-login-prompt');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => {
                if (notification && notification.parentNode) {
                    notification.remove();
                }
            });
        }

        // This notification is persistent - NO auto-remove timeout
        // User must click the X to dismiss it
    }

    // Load user's points on page load
    async loadPointsStatus() {
        try {
            // Show loading state immediately
            console.log('📊 Loading points from database...');
            this.updatePointsDisplay(0, 0);
            this.updateTodaysCyclesDisplay(0);

            // Get user's current date in their LOCAL timezone (not UTC)
            const now = new Date();
            const userDate = now.getFullYear() + '-' + 
                           String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(now.getDate()).padStart(2, '0');
            console.log('📅 User LOCAL timezone date:', userDate);
            console.log('🔧 UTC date would be:', new Date().toISOString().split('T')[0]);

            // Fetch fresh data from database with user's timezone date
            const url = `/api/health-cycle/points-status?user_date=${userDate}`;
            console.log('🌐 Fetching from URL:', url);
            
            const response = await fetch(url);
            const data = await response.json();

            console.log('📊 Points loaded from database:', data);
            console.log('🔧 CRITICAL: todays_cycles value:', data.todays_cycles);
            console.log('🔧 CRITICAL: About to call updateTodaysCyclesDisplay with:', data.todays_cycles);
            
            this.updatePointsDisplay(data.total_points, data.daily_points);
            
            // Update today's cycles display
            if (data.todays_cycles !== undefined) {
                this.updateTodaysCyclesDisplay(data.todays_cycles);
            } else {
                console.warn('⚠️ todays_cycles is undefined in API response');
                this.updateTodaysCyclesDisplay(0);
            }
        } catch (error) {
            console.log('❌ Points system unavailable (not logged in or network error):', error);
            
            // If network fails, show default values
            this.updatePointsDisplay(0, 0);
            this.updateTodaysCyclesDisplay(0);
        }
    }

    // Update stats display
    updateStatsDisplay() {
        const settings = this.storage.getSettings();

        if (this.elements.sittingTimeInfo) {
            this.elements.sittingTimeInfo.textContent = `${settings.sittingTime} min`;
        }
        if (this.elements.standingTimeInfo) {
            this.elements.standingTimeInfo.textContent = `${settings.standingTime} min`;
        }
        // Today's cycles are now updated via loadPointsStatus() from database
    }

    // Toggle audio controls visibility in setup modal
    toggleAudioControls() {
        const audioControls = document.getElementById('audioControls');
        if (audioControls) {
            const isEnabled = this.elements.enableAudioAlerts.checked;
            audioControls.style.opacity = isEnabled ? '1' : '0.5';
            audioControls.style.pointerEvents = isEnabled ? 'auto' : 'none';
            if (this.elements.setupAlarmPresetSelect) {
                this.elements.setupAlarmPresetSelect.disabled = !isEnabled;
            }
            if (this.elements.setupPreviewAlarmBtn) {
                this.elements.setupPreviewAlarmBtn.disabled = !isEnabled;
            }
            if (!isEnabled) {
                this.stopAlarmPreview();
            }
        }
    }

    // Toggle audio controls visibility in edit modal
    toggleEditAudioControls() {
        const audioControls = document.getElementById('editAudioControls');
        if (audioControls && this.elements.editEnableAudioAlerts) {
            const isEnabled = this.elements.editEnableAudioAlerts.checked;
            audioControls.style.opacity = isEnabled ? '1' : '0.5';
            audioControls.style.pointerEvents = isEnabled ? 'auto' : 'none';
            if (this.elements.editAlarmPresetSelect) {
                this.elements.editAlarmPresetSelect.disabled = !isEnabled;
            }
            if (this.elements.editPreviewAlarmBtn) {
                this.elements.editPreviewAlarmBtn.disabled = !isEnabled;
            }
            if (!isEnabled) {
                this.stopAlarmPreview();
            }
        }
    }

    updatePresetDetails(context = 'setup') {
        const isEditContext = context === 'edit';
        const targetDetails = isEditContext ? this.elements.editAlarmPresetDetails : this.elements.setupAlarmPresetDetails;
        if (!targetDetails) {
            return;
        }

        const control = isEditContext ? this.elements.editAlarmPresetSelect : this.elements.setupAlarmPresetSelect;
        const selectedId = this.getSelectedRadioValue(control) || DEFAULT_ALARM_PRESET_ID;
        targetDetails.innerHTML = renderAlarmPresetSummary(selectedId);
    }

    previewSelectedAlarm(context = 'setup') {
        const isEditContext = context === 'edit';
        const audioEnabled = isEditContext
            ? !!this.elements.editEnableAudioAlerts?.checked
            : !!this.elements.enableAudioAlerts?.checked;

        if (!audioEnabled) {
            console.warn('Audio alerts disabled, preview skipped');
            return;
        }

        if (this.activePreviewContext === context && this.previewAudio) {
            this.stopAlarmPreview();
            return;
        }

        const inputs = isEditContext ? this.elements.editAlarmPresetSelect : this.elements.setupAlarmPresetSelect;
        const presetId = this.getSelectedRadioValue(inputs) || DEFAULT_ALARM_PRESET_ID;

        this.playAlarmPreview(presetId, context);
    }

    playAlarmPreview(presetId, context) {
        const preset = ALARM_PRESETS[presetId] || ALARM_PRESETS[DEFAULT_ALARM_PRESET_ID];
        if (!preset) {
            console.warn('Unknown alarm preset, preview skipped');
            return;
        }

        if (this.core && typeof this.core.preloadAlarmAudio === 'function') {
            this.core.preloadAlarmAudio();
        }

        // Stop any existing preview before starting a new one
        this.stopAlarmPreview();

        const previewFiles = (preset.previewOrder && preset.previewOrder.length
            ? preset.previewOrder
            : Object.values(preset.files || {}))
            .filter(Boolean);

        if (!previewFiles.length) {
            console.warn('No audio files available for preview');
            return;
        }

        this.previewQueue = previewFiles;
        this.previewIndex = 0;
        this.activePreviewContext = context;
        this.previewButton = context === 'edit' ? this.elements.editPreviewAlarmBtn : this.elements.setupPreviewAlarmBtn;

        this.updatePreviewButtonState(this.previewButton, true);

        const playNext = () => {
            if (!this.previewQueue || this.previewIndex >= this.previewQueue.length) {
                this.stopAlarmPreview();
                return;
            }

            const file = this.previewQueue[this.previewIndex];
            let audio = null;

            // Always create new Audio for preview to ensure it works with user interaction
            // and avoids any issues with cloned nodes from preloaded audio
            audio = new Audio(file);
            audio.preload = 'auto';

            this.previewAudio = audio;

            let alarmType = 'alarm1';
            if (preset.files?.backToWork && file === preset.files.backToWork) {
                alarmType = 'alarm2';
            }
            const savedVolume = this.storage ? this.storage.getAlarmVolume(alarmType) : 100;
            this.previewAudio.volume = Math.max(0, Math.min(1, savedVolume / 100));

            this.previewAudio.addEventListener('ended', () => {
                this.previewIndex += 1;
                playNext();
            }, { once: true });

            this.previewAudio.addEventListener('error', () => {
                console.warn('Preview audio failed to play:', file);
                this.previewIndex += 1;
                playNext();
            }, { once: true });

            const playPromise = this.previewAudio.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch((error) => {
                    if (error && error.name === 'AbortError') {
                        // Expected when preview is stopped or restarted quickly; no action needed
                        return;
                    }
                    console.warn('Preview playback blocked:', error);
                    this.stopAlarmPreview();
                });
            }
        };

        playNext();
    }

    stopAlarmPreview() {
        if (this.previewAudio) {
            try {
                this.previewAudio.pause();
                this.previewAudio.currentTime = 0;
            } catch (error) {
                console.warn('Error stopping preview audio:', error);
            }
        }

        if (this.previewButton) {
            this.updatePreviewButtonState(this.previewButton, false);
        }

        this.previewAudio = null;
        this.previewQueue = [];
        this.previewIndex = 0;
        this.previewButton = null;
        this.activePreviewContext = null;
    }

    updatePreviewButtonState(button, isPlaying) {
        if (!button) {
            return;
        }

        if (!button.dataset.originalContent) {
            button.dataset.originalContent = button.innerHTML;
        }

        if (isPlaying) {
            button.innerHTML = '<i class="fas fa-stop"></i><span style="margin-left: 0.35rem;">Stop Preview</span>';
            button.classList.add('preview-playing');
        } else {
            button.innerHTML = button.dataset.originalContent;
            button.classList.remove('preview-playing');
        }

        button.setAttribute('aria-pressed', isPlaying ? 'true' : 'false');
    }

    // Get current date as string (YYYY-MM-DD) in user's local timezone
    getCurrentDateString() {
        const now = new Date();
        return now.getFullYear() + '-' + 
               String(now.getMonth() + 1).padStart(2, '0') + '-' + 
               String(now.getDate()).padStart(2, '0');
    }

    // Start checking for daily reset (every 60 seconds)
    startDailyResetCheck() {
        console.log('⏰ Starting daily reset check (every 60 seconds)');
        
        this.dailyCheckIntervalId = setInterval(() => {
            this.checkAndRefreshForNewDay();
        }, 60000); // Check every 60 seconds (1 minute)
    }

    // Stop daily reset check
    stopDailyResetCheck() {
        if (this.dailyCheckIntervalId) {
            clearInterval(this.dailyCheckIntervalId);
            this.dailyCheckIntervalId = null;
            console.log('⏹️ Stopped daily reset check');
        }
    }

    // Check if day has changed and refresh UI if needed
    checkAndRefreshForNewDay() {
        const currentDateString = this.getCurrentDateString();
        
        if (currentDateString !== this.lastCheckedDate) {
            console.log(`🌅 Day changed from ${this.lastCheckedDate} to ${currentDateString} - Refreshing UI`);
            this.lastCheckedDate = currentDateString;
            
            // Reload points and cycles from database (triggers daily reset on backend)
            this.loadPointsStatus();
        }
    }













    // Show error if alarm audio fails to load

    async sendPauseState(isPaused) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const response = await fetch('/api/pico/timer-pause', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ paused: isPaused })
            });
            
            if (!response.ok) {
                console.error('Failed to update pause state on server');
            } else {
                console.log(`✅ Timer ${isPaused ? 'paused' : 'resumed'} on server`);
            }
        } catch (error) {
            console.error('Error updating pause state:', error);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the Focus Clock UI globally
    // It will handle whether to render the full UI or just run in background
    window.focusClockUI = new FocusClockUI();

    // Handle page visibility changes - ensure timer accuracy when tab becomes active
    document.addEventListener('visibilitychange', function() {
        if (!window.focusClockUI || !window.focusClockUI.core) return;

        if (!document.hidden && window.focusClockUI.core.isRunning) {
            // Tab became visible and timer is running
            console.log('🔄 Tab became visible - checking for missed session completions and alarms');
            
            // Check if alarm should have fired while tab was hidden
            const timeLeft = window.focusClockUI.core.currentTime;
            const warningTime = 30; // 30 seconds warning
            
            // If we're at or past the 30-second mark and alarm hasn't shown yet, trigger it
            if (timeLeft <= warningTime && !window.focusClockUI.core.warningShown) {
                console.log('⚠️ Alarm was delayed while tab was hidden - playing now!');
                window.focusClockUI.core.warningShown = true;
                const sessionType = window.focusClockUI.core.isSittingSession ? 'standUp' : 'backToWork';
                window.focusClockUI.core.playAlarmAndShowPopup(sessionType);
            }
            
            // Force an immediate update to catch up
            window.focusClockUI.core.tick();
            
            // Also check if we missed a session completion while in background
            window.focusClockUI.core.checkForMissedCompletion();
        }
    });
});

// Export for use in other files
window.FocusClockUI = FocusClockUI;