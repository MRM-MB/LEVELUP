<?php

use App\Services\PicoDisplayService;
use Illuminate\Support\Facades\Cache;

test('pico display endpoint returns default state initially', function () {
    // Clear cache to ensure fresh state
    Cache::forget('pico.display.state');

    $response = $this->getJson(route('api.pico.display'));

    $response->assertOk()
        ->assertJson([
            'message' => 'Welcome to LevelUp!',
            'logged_in' => false,
            'timer_phase' => null,
            'is_paused' => false,
        ]);
});

test('pico display endpoint returns updated timer phase', function () {
    $response = $this->postJson(route('api.pico.timer-phase'), [
        'phase' => 'sitting',
        'time_remaining' => 300,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'phase' => 'sitting',
            'time_remaining' => 300,
        ]);

    // Verify state is persisted
    $this->getJson(route('api.pico.display'))
        ->assertJson([
            'timer_phase' => 'sitting',
            'time_remaining' => 300,
        ]);
});

test('pico display endpoint generates warning message when time is low', function () {
    $this->postJson(route('api.pico.timer-phase'), [
        'phase' => 'sitting',
        'time_remaining' => 20, // Less than 30s
    ]);

    $this->getJson(route('api.pico.display'))
        ->assertJson([
            'timer_phase' => 'sitting',
            'warning_message' => 'Get ready to stand up!',
        ]);
        
    $this->postJson(route('api.pico.timer-phase'), [
        'phase' => 'standing',
        'time_remaining' => 15, // Less than 30s
    ]);

    $this->getJson(route('api.pico.display'))
        ->assertJson([
            'timer_phase' => 'standing',
            'warning_message' => 'Get ready to sit down!',
        ]);
});

test('pico display endpoint updates pause state', function () {
    $response = $this->postJson(route('api.pico.timer-pause'), [
        'paused' => true,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'paused' => true,
            'message' => 'Timer paused',
        ]);

    // Verify state is persisted
    $this->getJson(route('api.pico.display'))
        ->assertJson([
            'is_paused' => true,
        ]);
        
    // Unpause
    $this->postJson(route('api.pico.timer-pause'), [
        'paused' => false,
    ]);
    
    $this->getJson(route('api.pico.display'))
        ->assertJson([
            'is_paused' => false,
        ]);
});

test('pico timer phase validation fails with invalid data', function () {
    $response = $this->postJson(route('api.pico.timer-phase'), [
        'phase' => 'invalid_phase',
        'time_remaining' => -10,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['phase', 'time_remaining']);
});

test('pico timer pause validation fails with missing data', function () {
    $response = $this->postJson(route('api.pico.timer-pause'), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['paused']);
});

test('pico display updates message on user login and logout', function () {
    $user = \App\Models\User::factory()->create([
        'name' => 'Test User',
        'username' => 'testuser',
        'password' => bcrypt('password'),
    ]);

    // Login
    $this->post('/login', [
        'username' => 'testuser',
        'password' => 'password',
    ])->assertRedirect(route('home'));

    // Check Pico display state
    $this->getJson(route('api.pico.display'))
        ->assertJson([
            'message' => 'Hello Test User!',
            'username' => 'testuser',
            'logged_in' => true,
        ]);

    // Logout
    $this->post('/logout')
        ->assertRedirect(route('login'));

    // Check Pico display state reverted
    $this->getJson(route('api.pico.display'))
        ->assertJson([
            'message' => 'Welcome to LevelUp!',
            'logged_in' => false,
        ]);
});
