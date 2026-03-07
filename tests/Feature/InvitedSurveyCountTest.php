<?php

use App\Enums\SurveyInvitationStatus;
use App\Enums\SurveyType;
use App\Enums\UserRole;
use App\Models\Survey;
use App\Models\SurveyInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can get invited survey counts', function () {
    $creator = User::factory()->create([
        'role' => UserRole::Creator,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Respondent,
        'email' => 'Respondent@Example.com',
    ]);

    $otherUser = User::factory()->create([
        'role' => UserRole::Respondent,
        'email' => 'other@example.com',
    ]);

    $surveyOne = Survey::query()->create([
        'title' => 'Survey One',
        'type' => SurveyType::Survey,
        'created_by' => $creator->id,
        'is_active' => true,
        'is_closed' => false,
    ]);

    $surveyTwo = Survey::query()->create([
        'title' => 'Survey Two',
        'type' => SurveyType::Poll,
        'created_by' => $creator->id,
        'is_active' => true,
        'is_closed' => false,
    ]);

    $surveyThree = Survey::query()->create([
        'title' => 'Survey Three',
        'type' => SurveyType::Survey,
        'created_by' => $creator->id,
        'is_active' => true,
        'is_closed' => false,
    ]);

    SurveyInvitation::query()->create([
        'survey_id' => $surveyOne->id,
        'email' => 'respondent@example.com',
        'invitation_token' => (string) str()->uuid(),
        'status' => SurveyInvitationStatus::Pending,
    ]);

    SurveyInvitation::query()->create([
        'survey_id' => $surveyTwo->id,
        'email' => 'RESPONDENT@example.com',
        'invitation_token' => (string) str()->uuid(),
        'status' => SurveyInvitationStatus::Completed,
    ]);

    SurveyInvitation::query()->create([
        'survey_id' => $surveyThree->id,
        'email' => $otherUser->email,
        'invitation_token' => (string) str()->uuid(),
        'status' => SurveyInvitationStatus::Pending,
    ]);

    Sanctum::actingAs($user);

    $this->getJson('/api/me/invited-surveys/count')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_invited_surveys', 2)
        ->assertJsonPath('data.pending_invited_surveys', 1)
        ->assertJsonPath('data.completed_invited_surveys', 1);
});
