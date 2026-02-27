<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Enums\SurveyInvitationStatus;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyInvitation;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResponseService
{
    public function __construct(
        protected SurveyResponse $response,
        protected SurveyAnswer $answer
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $answers
     */
    public function submit(Survey $survey, User $respondent, array $answers, ?SurveyInvitation $invitation = null): SurveyResponse
    {
        $survey->loadMissing(['questions.options']);

        $this->guardAgainstDuplicateSubmission($survey, $respondent);

        [$normalizedAnswers, $answeredQuestionIds] = $this->prepareAnswers($survey, $answers);
        $this->ensureRequiredQuestionsAnswered($survey, $answeredQuestionIds);

        return DB::transaction(function () use ($survey, $respondent, $normalizedAnswers, $invitation) {
            $response = $this->response->newQuery()->create([
                'survey_id' => $survey->id,
                'respondent_id' => $respondent->id,
                'submitted_at' => now(),
            ]);

            $this->storeAnswers($response, $normalizedAnswers);

            if ($invitation) {
                $invitation->update([
                    'status' => SurveyInvitationStatus::Completed,
                ]);
            }

            return $response->load('answers');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $answers
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, int>}
     */
    protected function prepareAnswers(Survey $survey, array $answers): array
    {
        $questions = $survey->questions->keyBy('id');
        $normalized = [];
        $answered = [];

        foreach ($answers as $index => $answer) {
            $questionId = $answer['question_id'] ?? null;

            if (! $questionId || ! $questions->has($questionId)) {
                throw ValidationException::withMessages([
                    "answers.$index.question_id" => ['Invalid question provided.'],
                ]);
            }

            /** @var Question $question */
            $question = $questions->get($questionId);
            $answered[$questionId] = true;

            $normalized = array_merge(
                $normalized,
                $this->normalizeAnswerByType($question, $answer, $index)
            );
        }

        return [$normalized, array_keys($answered)];
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeAnswerByType(Question $question, array $answer, int $index): array
    {
        return match ($question->type) {
            QuestionType::Text => $this->normalizeTextAnswer($question, $answer, $index),
            QuestionType::SingleChoice => $this->normalizeSingleChoiceAnswer($question, $answer, $index),
            QuestionType::MultipleChoice => $this->normalizeMultipleChoiceAnswer($question, $answer, $index),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeTextAnswer(Question $question, array $answer, int $index): array
    {
        $text = trim((string) ($answer['answer_text'] ?? ''));

        if ($text === '') {
            throw ValidationException::withMessages([
                "answers.$index.answer_text" => ['This question requires a text response.'],
            ]);
        }

        if (! empty($answer['selected_option_id']) || ! empty($answer['selected_option_ids'])) {
            throw ValidationException::withMessages([
                "answers.$index" => ['Text questions do not accept option selections.'],
            ]);
        }

        return [[
            'question_id' => $question->id,
            'answer_text' => $text,
            'selected_option_id' => null,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeSingleChoiceAnswer(Question $question, array $answer, int $index): array
    {
        $optionId = $answer['selected_option_id'] ?? null;

        if (! $optionId) {
            throw ValidationException::withMessages([
                "answers.$index.selected_option_id" => ['A single choice question requires one option selection.'],
            ]);
        }

        $option = $this->findOption($question, $optionId, "answers.$index.selected_option_id");

        if (! empty($answer['answer_text']) || ! empty($answer['selected_option_ids'])) {
            throw ValidationException::withMessages([
                "answers.$index" => ['Single choice questions accept only one option selection.'],
            ]);
        }

        return [[
            'question_id' => $question->id,
            'answer_text' => null,
            'selected_option_id' => $option->id,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeMultipleChoiceAnswer(Question $question, array $answer, int $index): array
    {
        $optionIds = $answer['selected_option_ids'] ?? null;

        if (! $optionIds && isset($answer['selected_option_id'])) {
            $optionIds = [$answer['selected_option_id']];
        }

        if (! is_array($optionIds) || empty($optionIds)) {
            throw ValidationException::withMessages([
                "answers.$index.selected_option_ids" => ['A multiple choice question requires at least one option.'],
            ]);
        }

        if (! empty($answer['answer_text'])) {
            throw ValidationException::withMessages([
                "answers.$index" => ['Multiple choice questions accept only option selections.'],
            ]);
        }

        $rows = [];
        $uniqueOptionIds = array_unique($optionIds);

        foreach ($uniqueOptionIds as $optionId) {
            $option = $this->findOption($question, $optionId, "answers.$index.selected_option_ids");

            $rows[] = [
                'question_id' => $question->id,
                'answer_text' => null,
                'selected_option_id' => $option->id,
            ];
        }

        return $rows;
    }

    protected function findOption(Question $question, int $optionId, string $path): QuestionOption
    {
        $option = $question->options->firstWhere('id', $optionId);

        if (! $option) {
            throw ValidationException::withMessages([
                $path => ['Selected option is invalid for this question.'],
            ]);
        }

        return $option;
    }

    protected function ensureRequiredQuestionsAnswered(Survey $survey, array $answeredIds): void
    {
        $required = $survey->questions
            ->filter(fn (Question $question) => $question->required)
            ->pluck('id')
            ->all();

        $missing = array_values(array_diff($required, $answeredIds));

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'answers' => ['Some required questions are missing responses.'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $answers
     */
    protected function storeAnswers(SurveyResponse $response, array $answers): void
    {
        $rows = collect($answers)->map(function (array $answer) use ($response) {
            return [
                'response_id' => $response->id,
                'question_id' => $answer['question_id'],
                'answer_text' => $answer['answer_text'] ?? null,
                'selected_option_id' => $answer['selected_option_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->all();

        $this->answer->newQuery()->insert($rows);
    }

    protected function guardAgainstDuplicateSubmission(Survey $survey, User $respondent): void
    {
        $alreadySubmitted = $this->response->newQuery()
            ->where('survey_id', $survey->id)
            ->where('respondent_id', $respondent->id)
            ->exists();

        if ($alreadySubmitted) {
            throw ValidationException::withMessages([
                'survey' => ['You have already submitted this survey.'],
            ]);
        }
    }
}
