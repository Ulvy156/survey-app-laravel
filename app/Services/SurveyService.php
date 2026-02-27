<?php

namespace App\Services;

use App\Enums\SurveyInvitationStatus;
use App\Enums\SurveyType;
use App\Enums\UserRole;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
}
