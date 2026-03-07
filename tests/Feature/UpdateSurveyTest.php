<?php

use App\Enums\SurveyType;
use App\Enums\UserRole;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('creator can update their own survey', function () {
    $creator = User::factory()->create([
        'role' => UserRole::Creator,
    ]);

    $survey = Survey::query()->create([
        'title' => 'Initial Survey',
        'description' => 'Initial description',
        'type' => SurveyType::Survey,
        'created_by' => $creator->id,
        'is_active' => true,
        'is_closed' => false,
        'available_from_time' => '2026-03-08 09:00:00',
        'available_until_time' => '2026-03-08 17:00:00',
    ]);

    Sanctum::actingAs($creator);

    $updatedFrom = '2026-03-10 08:00:00';
    $updatedUntil = '2026-03-10 18:30:00';

    $response = $this->patchJson("/api/surveys/{$survey->id}", [
        'title' => 'Updated Survey',
        'description' => null,
        'type' => 'poll',
        'is_active' => false,
        'available_from_time' => $updatedFrom,
        'available_until_time' => $updatedUntil,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Survey updated successfully')
        ->assertJsonPath('data.title', 'Updated Survey')
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.type', 'poll')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.available_from_time', '2026-03-10T08:00:00.000000Z')
        ->assertJsonPath('data.available_until_time', '2026-03-10T18:30:00.000000Z');

    $survey->refresh();

    expect($survey->title)->toBe('Updated Survey')
        ->and($survey->description)->toBeNull()
        ->and($survey->type)->toBe(SurveyType::Poll)
        ->and($survey->is_active)->toBeFalse()
        ->and($survey->available_from_time?->format('Y-m-d H:i:s'))->toBe($updatedFrom)
        ->and($survey->available_until_time?->format('Y-m-d H:i:s'))->toBe($updatedUntil);
});

test('creator cannot update another creators survey', function () {
    $owner = User::factory()->create([
        'role' => UserRole::Creator,
    ]);

    $otherCreator = User::factory()->create([
        'role' => UserRole::Creator,
    ]);

    $survey = Survey::query()->create([
        'title' => 'Protected Survey',
        'type' => SurveyType::Survey,
        'created_by' => $owner->id,
        'is_active' => true,
        'is_closed' => false,
    ]);

    Sanctum::actingAs($otherCreator);

    $this->patchJson("/api/surveys/{$survey->id}", [
        'title' => 'Hijacked Title',
    ])->assertForbidden();

    expect($survey->fresh()->title)->toBe('Protected Survey');
});
