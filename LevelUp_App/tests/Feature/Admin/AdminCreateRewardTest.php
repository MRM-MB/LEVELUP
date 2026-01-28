<?php

use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('admin can create a reward without image', function () {
    $admin = adminForUsers();

    $rewardData = [
        'card_name' => 'Test Reward',
        'card_description' => 'A test reward description',
        'points_amount' => 100,
        // Remove card_image since it's nullable and causes validation errors
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.rewards.store'), $rewardData);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Check database without card_image and archived (set by controller)
    $this->assertDatabaseHas('rewards_catalog', [
        'card_name' => 'Test Reward',
        'card_description' => 'A test reward description', 
        'points_amount' => 100,
        'archived' => false,
    ]);
});

test('admin can create a reward with image', function () {
    $admin = adminForUsers();

    $image = UploadedFile::fake()->createWithContent('reward.jpg', base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q=='));

    $rewardData = [
        'card_name' => 'Test Reward with Image',
        'card_description' => 'A test reward description',
        'points_amount' => 150,
        'card_image' => $image,
    ];

    $response = $this->actingAs($admin)
        ->post(route('admin.rewards.store'), $rewardData);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('rewards_catalog', [
        'card_name' => 'Test Reward with Image',
        'card_description' => 'A test reward description',
        'points_amount' => 150,
        'archived' => false,
    ]);

    // Verify image was stored
    $reward = Reward::where('card_name', 'Test Reward with Image')->first();
    expect($reward->card_image)->not()->toBeNull();
});

test('create reward validation fails with missing required fields', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)
        ->post(route('admin.rewards.store'), []);

    $response->assertSessionHasErrors(['card_name', 'points_amount']);
});

test('create reward validation fails with invalid points cost', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)
        ->post(route('admin.rewards.store'), [
            'card_name' => 'Test Reward',
            'points_amount' => -10,
        ]);

    $response->assertSessionHasErrors(['points_amount']);
});

test('create reward validation fails with duplicate name', function () {
    $admin = adminForUsers();
    $existingReward = Reward::factory()->create(['card_name' => 'Unique Reward']);

    $response = $this->actingAs($admin)
        ->post(route('admin.rewards.store'), [
            'card_name' => 'Unique Reward',
            'points_amount' => 100,
        ]);

    $response->assertSessionHasErrors(['card_name']);
});

test('create reward validation fails with invalid image file', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)
        ->post(route('admin.rewards.store'), [
            'card_name' => 'Test Reward',
            'points_amount' => 100,
            'card_image' => 'not-an-image.txt', // Invalid file type
        ]);

    $response->assertSessionHasErrors(['card_image']);
});