<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can list users', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'name' => 'Admin User',
    ]);

    User::factory()->create([
        'role' => UserRole::Creator,
        'name' => 'Creator Alpha',
        'email' => 'creator@example.com',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/users?search=creator')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Users retrieved successfully')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Creator Alpha')
        ->assertJsonPath('data.0.role', 'creator');
});

test('admin can create user', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    Sanctum::actingAs($admin);

    $this->postJson('/api/admin/users', [
        'name' => 'New Respondent',
        'email' => 'respondent@example.com',
        'password' => 'password123',
        'role' => 'respondent',
    ])->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User created successfully')
        ->assertJsonPath('data.email', 'respondent@example.com')
        ->assertJsonPath('data.role', 'respondent');

    $user = User::query()->where('email', 'respondent@example.com')->firstOrFail();

    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('admin can show user', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Creator,
        'email' => 'creator@example.com',
    ]);

    Sanctum::actingAs($admin);

    $this->getJson("/api/admin/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User retrieved successfully')
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', 'creator@example.com');
});

test('admin can update user', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Respondent,
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'password' => 'password123',
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/admin/users/{$user->id}", [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'password' => 'newpassword123',
        'role' => 'creator',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User updated successfully')
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.email', 'updated@example.com')
        ->assertJsonPath('data.role', 'creator');

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->role)->toBe(UserRole::Creator)
        ->and(Hash::check('newpassword123', $user->password))->toBeTrue();
});

test('admin can delete user but not self', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Respondent,
    ]);

    Sanctum::actingAs($admin);

    $this->deleteJson("/api/admin/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'User deleted successfully');

    expect(User::query()->find($user->id))->toBeNull();

    $this->deleteJson("/api/admin/users/{$admin->id}")
        ->assertForbidden();
});
