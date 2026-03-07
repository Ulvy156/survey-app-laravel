<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Enums\SurveyInvitationStatus;
use App\Enums\SurveyType;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class SurveyService
{
    public function __construct(
        protected Survey $survey
    ) {
    }

    /**
     * Create a survey for the authenticated user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Authenticatable $user): Survey
    {
        $payload = [
            'title' => $data['title'],
            'description' => Arr::get($data, 'description'),
            'type' => SurveyType::from($data['type']),
            'is_active' => Arr::get($data, 'is_active', true),
            'created_by' => $user->id,
            'available_from_time' => Arr::get($data, 'available_from_time'),
            'available_until_time' => Arr::get($data, 'available_until_time'),
        ];

        return $this->survey->newQuery()->create($payload);
    }

    public function getForUser(Survey $survey, User $user): Survey
    {
        $query = $this->survey->newQuery()
            ->with([
                'questions' => fn ($questionQuery) => $questionQuery
                    ->with(['options' => fn ($optionQuery) => $optionQuery->orderBy('id')])
                    ->orderBy('id'),
            ]);

        if ($user->role === UserRole::Admin) {
            return $query->findOrFail($survey->id);
        }

        if ($user->role === UserRole::Creator) {
            return $query
                ->where('created_by', $user->id)
                ->findOrFail($survey->id);
        }

        $matchedSurvey = $query
            ->whereKey($survey->id)
            ->active()
            ->where('is_closed', false)
            ->notExpired(now())
            ->availableAt(now())
            ->whereDoesntHave('responses', function (Builder $responses) use ($user): void {
                $responses->where('respondent_id', $user->id);
            })
            ->where(function (Builder $scope) use ($user): void {
                $email = Str::lower($user->email);

                $scope->where('is_public', true)
                    ->orWhereHas('invitations', function (Builder $invitation) use ($email): void {
                        $invitation->where('email', $email)
                            ->where('status', SurveyInvitationStatus::Pending->value)
                            ->where(function (Builder $window): void {
                                $window->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->first();

        if (! $matchedSurvey) {
            throw new NotFoundHttpException();
        }

        return $matchedSurvey;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Survey $survey, array $data, Authenticatable $user): Survey
    {
        $this->ensureSurveyManager($user, $survey);

        $payload = [];

        if (Arr::has($data, 'title')) {
            $payload['title'] = $data['title'];
        }

        if (Arr::has($data, 'description')) {
            $payload['description'] = Arr::get($data, 'description');
        }

        if (Arr::has($data, 'type')) {
            $payload['type'] = SurveyType::from($data['type']);
        }

        if (Arr::has($data, 'is_active')) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        if (Arr::has($data, 'available_from_time')) {
            $payload['available_from_time'] = Arr::get($data, 'available_from_time');
        }

        if (Arr::has($data, 'available_until_time')) {
            $payload['available_until_time'] = Arr::get($data, 'available_until_time');
        }

        if ($payload !== []) {
            $survey->update($payload);
        }

        return $survey->refresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listForUser(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->survey->newQuery();
        $now = now();
        $role = $user->role;

        if ($role === UserRole::Respondent) {
            $email = Str::lower($user->email);

            $query->active()
                ->where('is_closed', false)
                ->notExpired($now)
                ->availableAt($now)
                ->whereDoesntHave('responses', function (Builder $responses) use ($user): void {
                    $responses->where('respondent_id', $user->id);
                })
                ->where(function (Builder $scope) use ($email, $now): void {
                    $scope->where('is_public', true)
                        ->orWhereHas('invitations', function (Builder $invitation) use ($email, $now): void {
                            $invitation->where('email', $email)
                                ->where('status', SurveyInvitationStatus::Pending->value)
                                ->where(function (Builder $window) use ($now): void {
                                    $window->whereNull('expires_at')
                                        ->orWhere('expires_at', '>', $now);
                                });
                        });
                })
                ->withExists(['responses as already_submitted' => function ($responses) use ($user): void {
                    $responses->where('respondent_id', $user->id);
                }]);
        } else {
            $query->withExists(['responses as already_submitted' => function ($responses) use ($user): void {
                $responses->where('respondent_id', $user->id);
            }]);
        }

        if ($role === UserRole::Creator) {
            $query->where('created_by', $user->id);
        }

        $this->applyFilters($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = $perPage > 0 ? $perPage : 15;

        $paginator = $query
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $collection = $paginator->getCollection()->transform(function (Survey $survey) use ($now): Survey {
            $survey->available_now = $survey->isAvailableNow($now);
            $survey->already_submitted = (bool) ($survey->already_submitted ?? false);

            return $survey;
        });

        $paginator->setCollection($collection);

        return $paginator;
    }

    public function deleteSurvey(Survey $survey, User $user): void
    {
        $this->ensureSurveyManager($user, $survey);

        $survey->delete();
    }

    public function closeSurvey(Survey $survey, User $user): Survey
    {
        $this->ensureSurveyManager($user, $survey);

        $survey->update(['is_closed' => true]);

        return $survey->refresh();
    }

    public function reopenSurvey(Survey $survey, User $user): Survey
    {
        $this->ensureSurveyManager($user, $survey);

        $survey->update(['is_closed' => false]);

        return $survey->refresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listDeleted(array $filters): LengthAwarePaginator
    {
        $query = $this->survey->newQuery()->onlyTrashed();
        $now = now();

        $this->applyFilters($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = $perPage > 0 ? $perPage : 15;

        $paginator = $query
            ->latest('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        $collection = $paginator->getCollection()->transform(function (Survey $survey) use ($now): Survey {
            $survey->available_now = false;
            $survey->already_submitted = false;
            $survey->is_closed = (bool) $survey->is_closed;

            return $survey;
        });

        $paginator->setCollection($collection);

        return $paginator;
    }

    public function restoreSurvey(int $surveyId): Survey
    {
        $survey = $this->survey->newQuery()->onlyTrashed()->findOrFail($surveyId);
        $survey->restore();

        return $survey->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSurveyAnalysis(int $surveyId): array
    {
        $survey = $this->survey->newQuery()
            ->withTrashed()
            ->with([
                'creator:id,name,email',
                'questions' => fn ($query) => $query
                    ->with(['options' => fn ($optionQuery) => $optionQuery->orderBy('id')])
                    ->orderBy('id'),
                'responses' => fn ($query) => $query
                    ->with(['respondent:id,name,email', 'answers'])
                    ->orderBy('submitted_at'),
            ])
            ->withCount([
                'questions',
                'responses',
                'invitations',
                'invitations as completed_invitations_count' => fn ($query) => $query
                    ->where('status', SurveyInvitationStatus::Completed->value),
                'invitations as pending_invitations_count' => fn ($query) => $query
                    ->where('status', SurveyInvitationStatus::Pending->value),
            ])
            ->findOrFail($surveyId);

        $responses = $survey->responses->values();
        $answerBuckets = $this->bucketAnswersByQuestion($responses);
        $totalResponses = $responses->count();

        return [
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'description' => $survey->description,
                'type' => $survey->type instanceof SurveyType ? $survey->type->value : (string) $survey->type,
                'is_active' => (bool) $survey->is_active,
                'is_closed' => (bool) $survey->is_closed,
                'is_public' => (bool) $survey->is_public,
                'created_at' => optional($survey->created_at)->toISOString(),
                'updated_at' => optional($survey->updated_at)->toISOString(),
                'deleted_at' => optional($survey->deleted_at)->toISOString(),
                'creator' => $survey->creator ? [
                    'id' => $survey->creator->id,
                    'name' => $survey->creator->name,
                    'email' => $survey->creator->email,
                ] : null,
            ],
            'summary' => [
                'total_questions' => (int) $survey->questions_count,
                'total_responses' => (int) $survey->responses_count,
                'total_respondents' => $responses->pluck('respondent_id')->unique()->count(),
                'total_invitations' => (int) $survey->invitations_count,
                'completed_invitations' => (int) $survey->completed_invitations_count,
                'pending_invitations' => (int) $survey->pending_invitations_count,
            ],
            'questions' => $survey->questions
                ->map(fn (Question $question): array => $this->buildQuestionAnalysis(
                    $question,
                    $answerBuckets[$question->id] ?? [],
                    $totalResponses
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['type'])) {
            $types = array_map(static fn (SurveyType $type) => $type->value, SurveyType::cases());

            if (in_array($filters['type'], $types, true)) {
                $query->where('type', $filters['type']);
            }
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        }

        if (! empty($filters['search'])) {
            $keyword = $filters['search'];

            $query->where(function (Builder $scope) use ($keyword): void {
                $scope->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }
    }

    protected function ensureSurveyManager(User $user, Survey $survey): void
    {
        $role = $user->role;

        if ($role === UserRole::Admin) {
            return;
        }

        if ((int) $survey->created_by !== (int) $user->id) {
            abort(HttpResponse::HTTP_FORBIDDEN, 'You cannot modify this survey.');
        }
    }

    /**
     * @param  Collection<int, SurveyResponse>  $responses
     * @return array<int, array<int, array{answer: \App\Models\SurveyAnswer, response: SurveyResponse}>>
     */
    protected function bucketAnswersByQuestion(Collection $responses): array
    {
        return $responses->reduce(
            function (array $carry, SurveyResponse $response): array {
                foreach ($response->answers as $answer) {
                    $carry[$answer->question_id] ??= [];
                    $carry[$answer->question_id][] = [
                        'answer' => $answer,
                        'response' => $response,
                    ];
                }

                return $carry;
            },
            []
        );
    }

    /**
     * @param  array<int, array{answer: \App\Models\SurveyAnswer, response: SurveyResponse}>  $entries
     * @return array<string, mixed>
     */
    protected function buildQuestionAnalysis(Question $question, array $entries, int $totalResponses): array
    {
        $answeredResponsesCount = collect($entries)
            ->pluck('response.id')
            ->unique()
            ->count();

        $analysis = [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'type' => $question->type instanceof QuestionType ? $question->type->value : (string) $question->type,
            'required' => (bool) $question->required,
            'responses_count' => $answeredResponsesCount,
            'skipped_count' => max(0, $totalResponses - $answeredResponsesCount),
            'response_rate' => $this->percent($answeredResponsesCount, $totalResponses),
        ];

        if ($question->type === QuestionType::Text) {
            $analysis['text_answers'] = collect($entries)
                ->filter(fn (array $entry): bool => filled($entry['answer']->answer_text))
                ->map(function (array $entry): array {
                    $response = $entry['response'];
                    $respondent = $response->respondent;

                    return [
                        'response_id' => $response->id,
                        'respondent' => $respondent ? [
                            'id' => $respondent->id,
                            'name' => $respondent->name,
                            'email' => $respondent->email,
                        ] : null,
                        'answer_text' => $entry['answer']->answer_text,
                        'submitted_at' => optional($response->submitted_at)->toISOString(),
                    ];
                })
                ->values()
                ->all();

            return $analysis;
        }

        $analysis['options'] = $question->options
            ->map(fn (QuestionOption $option): array => $this->buildOptionAnalysis(
                $option,
                $entries,
                $totalResponses,
                $answeredResponsesCount
            ))
            ->values()
            ->all();

        return $analysis;
    }

    /**
     * @param  array<int, array{answer: \App\Models\SurveyAnswer, response: SurveyResponse}>  $entries
     * @return array<string, mixed>
     */
    protected function buildOptionAnalysis(
        QuestionOption $option,
        array $entries,
        int $totalResponses,
        int $answeredResponsesCount
    ): array {
        $selectionsCount = collect($entries)
            ->filter(fn (array $entry): bool => (int) $entry['answer']->selected_option_id === (int) $option->id)
            ->count();

        return [
            'id' => $option->id,
            'option_text' => $option->option_text,
            'selections_count' => $selectionsCount,
            'selection_rate' => $this->percent($selectionsCount, $totalResponses),
            'selection_share' => $this->percent($selectionsCount, $answeredResponsesCount),
        ];
    }

    protected function percent(int $count, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($count / $total) * 100, 2);
    }
}
