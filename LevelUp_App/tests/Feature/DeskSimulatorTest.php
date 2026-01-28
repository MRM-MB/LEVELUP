<?php

use App\Models\Desk;
use App\Models\User;
use App\Services\Wifi2BleSimulatorClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery as MockeryFacade;

uses(RefreshDatabase::class);

function makeDeskControlUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'desk_id' => null,
        'sitting_position' => 90,
        'standing_position' => 110,
    ], $overrides));
}

function createDeskWithSerial(): Desk
{
    return Desk::create([
        'desk_model' => 'FocusLift',
        'serial_number' => 'DL-' . Str::random(8),
    ]);
}

test('move to sit logs desk payload and forwards mm target', function () {
    $desk = createDeskWithSerial();

    $user = makeDeskControlUser([
        'desk_id' => $desk->id,
        'sitting_position' => 90,
    ]);

    $client = MockeryFacade::mock(Wifi2BleSimulatorClient::class);
    $client->shouldReceive('updateDeskState')
        ->once()
        ->with($desk->serial_number, ['position_mm' => 900])
        ->andReturn(['status' => 'ok']);

    $this->app->instance(Wifi2BleSimulatorClient::class, $client);

    Log::shouldReceive('info')
        ->once()
        ->with('DeskSimulator: moveToSit triggered', MockeryFacade::on(function ($context) use ($user, $desk) {
            return $context['user_id'] === $user->getKey()
                && $context['desk_id'] === $desk->serial_number
                && $context['position_mm'] === 900
                && $context['position_cm'] === 90;
        }));

    $response = $this->actingAs($user)->postJson(route('simulator.desks.sit', ['desk' => $desk->serial_number]));

    $response->assertOk()->assertJson(['status' => 'ok']);
});

test('move to stand logs desk payload and forwards mm target', function () {
    $desk = createDeskWithSerial();

    $user = makeDeskControlUser([
        'desk_id' => $desk->id,
        'standing_position' => 115,
    ]);

    $client = MockeryFacade::mock(Wifi2BleSimulatorClient::class);
    $client->shouldReceive('updateDeskState')
        ->once()
        ->with($desk->serial_number, ['position_mm' => 1150])
        ->andReturn(['status' => 'ok']);

    $this->app->instance(Wifi2BleSimulatorClient::class, $client);

    Log::shouldReceive('info')
        ->once()
        ->with('DeskSimulator: moveToStand triggered', MockeryFacade::on(function ($context) use ($user, $desk) {
            return $context['user_id'] === $user->getKey()
                && $context['desk_id'] === $desk->serial_number
                && $context['position_mm'] === 1150
                && $context['position_cm'] === 115;
        }));

    $response = $this->actingAs($user)->postJson(route('simulator.desks.stand', ['desk' => $desk->serial_number]));

    $response->assertOk()->assertJson(['status' => 'ok']);
});
