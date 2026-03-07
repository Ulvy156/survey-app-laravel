<?php

use App\Enums\QuestionType;
use App\Enums\SurveyType;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('creator can get their survey by id with questions and options', function () {
    $creator = User::factory()->create([
        'role' => UserRole::Creator,
    ]);

    $survey = Survey::query()->create([
        'title' => 'Employee Feedback',
        'description' => 'Quarterly internal survey',
        'type' => SurveyType::Survey,
        'created_by' => $creator->id,
        'is_active' => true,
        'is_closed' => false,
    ]);

    $textQuestion = Question::query()->create([
        'survey_id' => $survey->id,
        'question_text' => 'What should we improve?',
        'type' => QuestionType::Text,
        'required' => true,
    ]);

    $choiceQuestion = Question::query()->create([
        'survey_id' => $survey->id,
        'question_text' => 'How satisfied are you?',
        'type' => QuestionType::SingleChoice,
        'required' => true,
    ]);

    $option = QuestionOption::query()->create([
        'question_id' => $choiceQuestion->id,
        'option_text' => 'Very satisfied',
    ]);

    Sanctum::actingAs($creator);

    $response = $this->getJson("/api/surveys/{$survey->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Survey retrieved successfully')
        ->assertJsonPath('data.id', $survey->id)
        ->assertJsonPath('data.questions.0.id', $textQuestion->id)
        ->assertJsonPath('data.questions.0.question_text', 'What should we improve?')
        ->assertJsonPath('data.questions.0.type', 'text')
        ->assertJsonPath('data.questions.1.id', $choiceQuestion->id)
        ->assertJsonPath('data.questions.1.options.0.id', $option->id)
        ->assertJsonPath('data.questions.1.options.0.option_text', 'Very satisfied');
});

test('creator cannot get another creators survey by id', function () {
    $owner = User::factory()->create([
        'role' => UserRole::Creator,
    ]);

    $otherCreator = User::factory()->create([
        'role' => UserRole::Creator,
    ]);

    $survey = Survey::query()->create([
        'title' => 'Private Survey',
        'type' => SurveyType::Survey,
        'created_by' => $owner->id,
        'is_active' => true,
        'is_closed' => false,
    ]);

    Sanctum::actingAs($otherCreator);

    $this->getJson("/api/surveys/{$survey->id}")
        ->assertNotFound();
});
