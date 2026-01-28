<?php

use App\Models\User;
use App\Models\Desk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Helper: create an admin user for actingAs()
 */
function createAdminForDeskUpdate(): User
{
    return User::factory()->create([
        'role'     => 'admin',
        'password' => Hash::make('password'),
    ]);
}

test('admin can assign and change a desk for an existing user', function () {
    $admin = createAdminForDeskUpdate();

    $desk1 = Desk::create([
        'desk_model'    => 'Linak Desk',
        'serial_number' => '11:22:33:44:55:66',
        'name'          => 'Desk 1',
    ]);

    $desk2 = Desk::create([
        'desk_model'    => 'Linak Desk',
        'serial_number' => '77:88:99:aa:bb:cc',
        'name'          => 'Desk 2',
    ]);

    $user = User::factory()->create([
        'role'   => 'user',
        'desk_id'=> null,
    ]);

    // assign first desk
    $response = $this->actingAs($admin)->patch(route('admin.users.update', $user), [
        'name'              => $user->name,
        'surname'           => $user->surname,
        'username'          => $user->username,
        'date_of_birth'     => optional($user->date_of_birth)->format('Y-m-d'),
        'sitting_position'  => $user->sitting_position,
        'standing_position' => $user->standing_position,
        'desk_id'           => $desk1->id,
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'user_id' => $user->user_id,
        'desk_id' => $desk1->id,
    ]);

    // change to second desk
    $response = $this->actingAs($admin)->patch(route('admin.users.update', $user), [
        'name'              => $user->name,
        'surname'           => $user->surname,
        'username'          => $user->username,
        'date_of_birth'     => optional($user->date_of_birth)->format('Y-m-d'),
        'sitting_position'  => $user->sitting_position,
        'standing_position' => $user->standing_position,
        'desk_id'           => $desk2->id,
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'user_id' => $user->user_id,
        'desk_id' => $desk2->id,
    ]);
});

test('admin can remove desk assignment from a user', function () {
    $admin = createAdminForDeskUpdate();

    $desk = Desk::create([
        'desk_model'    => 'Linak Desk',
        'serial_number' => 'de:sk:00:00:00:01',
        'name'          => 'Desk Remove',
    ]);

    $user = User::factory()->create([
        'role'   => 'user',
        'desk_id'=> $desk->id,
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.users.update', $user), [
        'name'              => $user->name,
        'surname'           => $user->surname,
        'username'          => $user->username,
        'date_of_birth'     => optional($user->date_of_birth)->format('Y-m-d'),
        'sitting_position'  => $user->sitting_position,
        'standing_position' => $user->standing_position,
        'desk_id'           => null, // remove
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'user_id' => $user->user_id,
        'desk_id' => null,
    ]);
});

test('validation fails when updating a user with a non existing desk id', function () {
    $admin = createAdminForDeskUpdate();

    $user = User::factory()->create([
        'role'   => 'user',
        'desk_id'=> null,
    ]);

    $nonExistingDeskId = 9999;

    $response = $this->actingAs($admin)->patch(route('admin.users.update', $user), [
        'name'              => $user->name,
        'surname'           => $user->surname,
        'username'          => $user->username,
        'date_of_birth'     => optional($user->date_of_birth)->format('Y-m-d'),
        'sitting_position'  => $user->sitting_position,
        'standing_position' => $user->standing_position,
        'desk_id'           => $nonExistingDeskId,
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('desk_id');

    // ensure desk_id did not change
    $this->assertDatabaseHas('users', [
        'user_id' => $user->user_id,
        'desk_id' => null,
    ]);
});
