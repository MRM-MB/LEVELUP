<?php

use App\Models\Reward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('admin can edit a reward without changing image', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->create();

    $updatedData = [
        'card_name' => 'Updated Reward Name',
        'card_description' => 'Updated description',
        'points_amount' => 150,
        // Remove card_image since it's nullable and causes validation errors
    ];

    $response = $this->actingAs($admin)
        ->put(route('admin.rewards.update', $reward), $updatedData);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('rewards_catalog', [
        'id' => $reward->id,
        'card_name' => 'Updated Reward Name',
        'card_description' => 'Updated description',
        'points_amount' => 150,
    ]);
});

test('admin can edit a reward with new image', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->create();

    $image = UploadedFile::fake()->createWithContent('updated-image.jpg', base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q=='));

    $updatedData = [
        'card_name' => 'Updated Reward Name',
        'card_description' => 'Updated description',
        'points_amount' => 150,
        'card_image' => $image,
    ];

    $response = $this->actingAs($admin)
        ->put(route('admin.rewards.update', $reward), $updatedData);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('rewards_catalog', [
        'id' => $reward->id,
        'card_name' => 'Updated Reward Name',
        'card_description' => 'Updated description',
        'points_amount' => 150,
    ]);

    // Verify image was updated
    $reward->refresh();
    expect($reward->card_image)->not()->toBeNull();
});

test('edit reward validation fails with invalid data', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->create();

    $response = $this->actingAs($admin)
        ->put(route('admin.rewards.update', $reward), [
            'card_name' => '',
            'points_amount' => -50,
        ]);

    $response->assertSessionHasErrors(['card_name', 'points_amount']);
});

test('admin cannot edit reward name to existing name', function () {
    $admin = adminForUsers();
    $reward1 = Reward::factory()->create(['card_name' => 'First Reward']);
    $reward2 = Reward::factory()->create(['card_name' => 'Second Reward']);

    $response = $this->actingAs($admin)
        ->put(route('admin.rewards.update', $reward2), [
            'card_name' => 'First Reward',
            'points_amount' => 100,
        ]);

    $response->assertSessionHasErrors(['card_name']);
});

test('edit validation fails with invalid image file', function () {
    $admin = adminForUsers();
    $reward = Reward::factory()->create();

    $response = $this->actingAs($admin)
        ->put(route('admin.rewards.update', $reward), [
            'card_name' => 'Valid Name',
            'points_amount' => 100,
            'card_image' => 'not-an-image.txt', // Invalid file type
        ]);

    $response->assertSessionHasErrors(['card_image']);
});

test('edit validation fails with invalid reward id', function () {
    $admin = adminForUsers();

    $response = $this->actingAs($admin)
        ->put(route('admin.rewards.update', 99999), [
            'card_name' => 'Test',
            'points_amount' => 100,
        ]);

    $response->assertNotFound();
});