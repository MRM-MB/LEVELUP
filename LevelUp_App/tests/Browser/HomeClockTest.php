<?php

use App\Models\Desk;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;

uses(DatabaseMigrations::class);

function createClockUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'surname' => 'Tester',
        'username' => 'clock_' . Str::random(8),
        'role' => 'user',
        'sitting_position' => null,
        'standing_position' => null,
        'desk_id' => null,
    ], $attributes));
}

function createDeskForUser(User $user, array $overrides = []): Desk
{
    $desk = Desk::create(array_merge([
        'desk_model' => 'FocusLift',
        'serial_number' => 'DL-' . Str::random(8),
    ], $overrides));

    $user->update([
        'desk_id' => $desk->id,
        'sitting_position' => $overrides['sitting_position'] ?? 90,
        'standing_position' => $overrides['standing_position'] ?? 110,
    ]);

    return $desk;
}

function dismissSetupWizard(Browser $browser): void
{
    $browser->waitUsing(5, 100, function () use ($browser) {
        $ready = $browser->script('return !!window.focusClockUI;');
        return !empty($ready) && $ready[0] === true;
    });

    $browser->script("if (window.focusClockUI && window.focusClockUI.storage) {\n    try {\n        window.focusClockUI.storage.markAsConfigured();\n        window.focusClockUI.storage.updateTimes(1, 1);\n        window.focusClockUI.hideSetupModal();\n    } catch (e) { console.warn(e); }\n}");
}

// --------- START / PAUSE FLOW ---------
test('timer start and pause buttons toggle states', function () {
    $user = createClockUser();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visitRoute('home')
            ->waitFor('#startBtn');

        dismissSetupWizard($browser);

        $browser->assertEnabled('#startBtn')
            ->assertDisabled('#pauseBtn')
            ->press('#startBtn')
            ->pause(300);

        // Fast-forward the timer so pausing records elapsed time
        $browser->script('window.focusClockUI.core.sessionStartTime = Date.now() - 65000;');

        $browser->pause(200)
            ->assertDisabled('#startBtn')
            ->assertEnabled('#pauseBtn')
            ->press('#pauseBtn')
            ->pause(500)
            ->assertEnabled('#startBtn')
            ->assertDisabled('#pauseBtn')
            ->assertEnabled('#stopBtn');
    });
});

// --------- SESSION STYLING & IMAGES ---------
test('cycle transitions update session styling and imagery', function () {
    $user = createClockUser();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visitRoute('home')
            ->waitFor('#startBtn');

        dismissSetupWizard($browser);

        $browser->press('#startBtn')
            ->pause(500);

        $browser->script([
            'window.focusClockUI.core.sittingTime = 0.01;',
            'window.focusClockUI.core.standingTime = 0.01;',
            'window.focusClockUI.core.sessionDuration = 1;',
            'window.focusClockUI.core.currentTime = 0;',
            'window.focusClockUI.core.completeSession();',
        ]);

        $browser->waitUsing(5, 100, function () use ($browser) {
            $state = $browser->script('return window.focusClockUI.core.isSittingSession === false;');
            return !empty($state) && $state[0] === true;
        });

        $browser->waitUsing(5, 100, function () use ($browser) {
            $label = $browser->script("return document.querySelector('#sessionIndicator .session-label')?.textContent || ''; ");
            return str_contains($label[0] ?? '', 'Stand Up');
        });

        $browser->script([
            'window.focusClockUI.core.currentTime = 0;',
            'window.focusClockUI.core.completeSession();',
        ]);

        $browser->waitUsing(5, 100, function () use ($browser) {
            $state = $browser->script('return window.focusClockUI.core.isSittingSession === true;');
            return !empty($state) && $state[0] === true;
        });

        $browser->waitUsing(5, 100, function () use ($browser) {
            $label = $browser->script("return document.querySelector('#sessionIndicator .session-label')?.textContent || ''; ");
            return str_contains($label[0] ?? '', 'Sit Down');
        });
    });
});

// --------- SETTINGS MODAL ---------
test('editing timer settings updates displayed durations', function () {
    $user = createClockUser();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visitRoute('home')
            ->waitFor('#startBtn');

        dismissSetupWizard($browser);

        $browser->click('#settingsBtn')
            ->waitFor('#settingsModal', 5)
            ->type('#editSittingTimeInput', 15)
            ->type('#editStandingTimeInput', 5)
            ->press('#updateSettingsBtn')
            ->waitUntilMissing('#settingsModal')
            ->waitForText('15 min')
            ->assertSeeIn('#sittingTimeInfo', '15 min')
            ->assertSeeIn('#standingTimeInfo', '5 min');
    });
});

// --------- ALARM PREVIEW ---------
test('alarm preview toggle updates button state', function () {
    $user = createClockUser();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visitRoute('home')
            ->waitFor('#startBtn');

        dismissSetupWizard($browser);

        $browser->click('#settingsBtn')
            ->waitFor('#settingsModal', 5)
            ->waitFor('#editPreviewAlarm')
            ->click('#editPreviewAlarm')
            ->pause(300)
            ->assertAttribute('#editPreviewAlarm', 'aria-pressed', 'true')
            ->assertPresent('#editPreviewAlarm.preview-playing')
            ->click('#editPreviewAlarm')
            ->pause(300)
            ->assertAttribute('#editPreviewAlarm', 'aria-pressed', 'false');
    });
});
