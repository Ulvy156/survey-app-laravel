<?php

use App\Enums\QuestionType;
use App\Enums\SurveyType;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin can analyze responses for a survey', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $creator = User::factory()->create([
        'role' => UserRole::Creator,
    ]);

    $respondentOne = User::factory()->create([
        'role' => UserRole::Respondent,
    ]);

    $respondentTwo = User::factory()->create([
        'role' => UserRole::Respondent,
    ]);

    $survey = Survey::query()->create([
        'title' => 'Product Feedback',
        'description' => 'Quarterly feedback survey',
        'type' => SurveyType::Survey,
        'created_by' => $creator->id,
        'is_active' => true,
        'is_closed' => false,
        'is_public' => false,
    ]);

    $textQuestion = Question::query()->create([
        'survey_id' => $survey->id,
        'question_text' => 'What do you think about the product?',
        'type' => QuestionType::Text,
        'required' => true,
    ]);

    $singleChoiceQuestion = Question::query()->create([
        'survey_id' => $survey->id,
        'question_text' => 'How would you rate the product?',
        'type' => QuestionType::SingleChoice,
        'required' => true,
    ]);

    $multipleChoiceQuestion = Question::query()->create([
        'survey_id' => $survey->id,
        'question_text' => 'Which areas do you value most?',
        'type' => QuestionType::MultipleChoice,
        'required' => false,
    ]);

    $excellent = QuestionOption::query()->create([
        'question_id' => $singleChoiceQuestion->id,
        'option_text' => 'Excellent',
    ]);

    $good = QuestionOption::query()->create([
        'question_id' => $singleChoiceQuestion->id,
        'option_text' => 'Good',
    ]);

    $poor = QuestionOption::query()->create([
        'question_id' => $singleChoiceQuestion->id,
        'option_text' => 'Poor',
    ]);

    $ui = QuestionOption::query()->create([
        'question_id' => $multipleChoiceQuestion->id,
        'option_text' => 'UI',
    ]);

    $speed = QuestionOption::query()->create([
        'question_id' => $multipleChoiceQuestion->id,
        'option_text' => 'Speed',
    ]);

    $support = QuestionOption::query()->create([
        'question_id' => $multipleChoiceQuestion->id,
        'option_text' => 'Support',
    ]);

    $firstResponse = SurveyResponse::query()->create([
        'survey_id' => $survey->id,
        'respondent_id' => $respondentOne->id,
        'submitted_at' => now()->subHour(),
    ]);

    $secondResponse = SurveyResponse::query()->create([
        'survey_id' => $survey->id,
        'respondent_id' => $respondentTwo->id,
        'submitted_at' => now(),
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $firstResponse->id,
        'question_id' => $textQuestion->id,
        'answer_text' => 'Fast and reliable.',
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $firstResponse->id,
        'question_id' => $singleChoiceQuestion->id,
        'selected_option_id' => $good->id,
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $firstResponse->id,
        'question_id' => $multipleChoiceQuestion->id,
        'selected_option_id' => $ui->id,
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $firstResponse->id,
        'question_id' => $multipleChoiceQuestion->id,
        'selected_option_id' => $speed->id,
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $secondResponse->id,
        'question_id' => $textQuestion->id,
        'answer_text' => 'Needs better onboarding.',
    ]);

    SurveyAnswer::query()->create([
        'response_id' => $secondResponse->id,
        'question_id' => $singleChoiceQuestion->id,
        'selected_option_id' => $poor->id,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson("/api/admin/surveys/{$survey->id}/analysis");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data.questions')
        ->assertJsonPath('data.summary.total_questions', 3)
        ->assertJsonPath('data.summary.total_responses', 2)
        ->assertJsonPath('data.summary.total_respondents', 2)
        ->assertJsonPath('data.summary.total_invitations', 0)
        ->assertJsonPath('data.questions.0.type', 'text')
        ->assertJsonPath('data.questions.0.responses_count', 2)
        ->assertJsonPath('data.questions.0.skipped_count', 0)
        ->assertJsonPath('data.questions.0.response_rate', 100)
        ->assertJsonPath('data.questions.0.text_answers.0.answer_text', 'Fast and reliable.')
        ->assertJsonPath('data.questions.0.text_answers.1.answer_text', 'Needs better onboarding.')
        ->assertJsonPath('data.questions.1.type', 'single_choice')
        ->assertJsonPath('data.questions.1.options.0.selections_count', 0)
        ->assertJsonPath('data.questions.1.options.1.selections_count', 1)
        ->assertJsonPath('data.questions.1.options.1.selection_rate', 50)
        ->assertJsonPath('data.questions.1.options.2.selections_count', 1)
        ->assertJsonPath('data.questions.2.type', 'multiple_choice')
        ->assertJsonPath('data.questions.2.responses_count', 1)
        ->assertJsonPath('data.questions.2.skipped_count', 1)
        ->assertJsonPath('data.questions.2.response_rate', 50)
        ->assertJsonPath('data.questions.2.options.0.selections_count', 1)
        ->assertJsonPath('data.questions.2.options.0.selection_share', 100)
        ->assertJsonPath('data.questions.2.options.1.selections_count', 1)
        ->assertJsonPath('data.questions.2.options.1.selection_share', 100)
        ->assertJsonPath('data.questions.2.options.2.selections_count', 0);
});
