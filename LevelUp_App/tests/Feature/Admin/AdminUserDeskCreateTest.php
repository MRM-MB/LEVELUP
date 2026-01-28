<?php

use App\Models\User;
use App\Models\Desk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Helper: create an admin user for actingAs()
 */
function createAdminForDeskCreate(): User
{
    return User::factory()->create([
        'role'     => 'admin',
        'password' => Hash::make('password'),
    ]);
}

test('admin can create a user without a desk', function () {
    $admin = createAdminForDeskCreate();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name'                  => 'John',
        'surname'               => 'Doe',
        'username'              => 'johndoe',
        'date_of_birth'         => '1990-01-01',
        'password'              => 'StrongPass1!@#Strong',
        'password_confirmation' => 'StrongPass1!@#Strong',
        'sitting_position'      => null,
        'standing_position'     => null,
        'desk_id'               => null,
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'username' => 'johndoe',
        'desk_id'  => null,
    ]);
});

test('admin can create a user with an assigned desk', function () {
    $admin = createAdminForDeskCreate();

    $desk = Desk::create([
        'desk_model'    => 'Linak Desk',
        'serial_number' => 'aa:bb:cc:dd:ee:ff',
        'name'          => 'Desk A',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name'                  => 'Jane',
        'surname'               => 'Smith',
        'username'              => 'janesmith',
        'date_of_birth'         => '1992-05-10',
        'password'              => 'StrongPass1!@#Strong',
        'password_confirmation' => 'StrongPass1!@#Strong',
        'sitting_position'      => 100,
        'standing_position'     => 120,
        'desk_id'               => $desk->id,
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'username' => 'janesmith',
        'desk_id'  => $desk->id,
    ]);
});

test('validation fails when creating a user with a non existing desk id', function () {
    $admin = createAdminForDeskCreate();

    $nonExistingDeskId = 9999;

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name'                  => 'Bad',
        'surname'               => 'Desk',
        'username'              => 'baddeskuser',
        'date_of_birth'         => '1992-05-10',
        'password'              => 'secret123',
        'password_confirmation' => 'secret123',
        'sitting_position'      => 100,
        'standing_position'     => 120,
        'desk_id'               => $nonExistingDeskId,
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('desk_id');

    // user should not be created
    $this->assertDatabaseMissing('users', [
        'username' => 'baddeskuser',
    ]);
});
