<?php

use App\Models\User;
use App\Models\Desk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Helper: create an admin user for actingAs()
 */
function createAdminForDesks(): User
{
    return User::factory()->create([
        'role'     => 'admin',
        'password' => Hash::make('password'),
    ]);
}

test('admin can move multiple desks to a given height', function () {
    $admin = createAdminForDesks();

    // Create some managed desks
    $desk1 = Desk::create([
        'desk_model'    => 'Linak Desk',
        'serial_number' => 'aa:bb:cc:dd:ee:01',
        'name'          => 'Desk 1',
    ]);
    $desk2 = Desk::create([
        'desk_model'    => 'Linak Desk',
        'serial_number' => 'aa:bb:cc:dd:ee:02',
        'name'          => 'Desk 2',
    ]);
    $desk3 = Desk::create([
        'desk_model'    => 'Linak Desk',
        'serial_number' => 'aa:bb:cc:dd:ee:03',
        'name'          => 'Desk 3',
    ]);

    // Fake all HTTP calls made by Wifi2BleSimulatorClient
    Http::fake([
        '*' => Http::response(['ok' => true], 200),
    ]);

    $response = $this->actingAs($admin)->post(route('admin.desks.bulk-height'), [
        'desk_ids'  => [$desk1->id, $desk2->id, $desk3->id],
        'height_cm' => 110,
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();
    $response->assertSessionHas('success');   // bulk-height should set a success flash

    // We expect one HTTP request per desk to the simulator
    Http::assertSentCount(3);
});

test('bulk move validation fails with missing height or desks', function () {
    $admin = createAdminForDesks();

    // no desk_ids, no height
    $response = $this->actingAs($admin)->post(route('admin.desks.bulk-height'), []);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['desk_ids', 'height_cm']);
});

test('admin can rename a managed desk', function () {
    $admin = createAdminForDesks();

    $desk = Desk::create([
        'desk_model'    => 'Linak Desk',
        'serial_number' => 'ff:ee:dd:cc:bb:aa',
        'name'          => 'Old Name',
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.desks.update', $desk), [
        'name' => 'New Fancy Name',
        'tab'  => 'desks',  // your form sends this, but validation shouldn’t require it
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('desks', [
        'id'   => $desk->id,
        'name' => 'New Fancy Name',
    ]);
});
