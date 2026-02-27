<?php

namespace App\Services;

use App\Models\Survey;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ShareService
{
    public function __construct(
        protected Survey $survey
    ) {
    }

    public function updateShareSettings(Survey $survey, bool $isPublic, ?Carbon $expiresAt = null): Survey
    {
        if ($isPublic) {
            $survey->share_token = $survey->share_token ?? (string) Str::uuid();
            $survey->is_public = true;
            $survey->expires_at = $expiresAt;
        } else {
            $survey->is_public = false;
            $survey->share_token = null;
            $survey->expires_at = null;
        }

        $survey->save();

        return $survey->refresh();
    }

    public function findActivePublicSurvey(string $shareToken): Survey
    {
        $survey = $this->survey->newQuery()
            ->where('share_token', $shareToken)
            ->first();

        if (! $survey) {
            abort(Response::HTTP_NOT_FOUND, 'Survey not found.');
        }

        $this->ensureSurveyIsAccessible($survey);

        return $survey;
    }

    public function ensureSurveyIsAccessible(Survey $survey): void
    {
        if (! $survey->is_active || ! $survey->is_public) {
            abort(Response::HTTP_FORBIDDEN, 'Survey is not publicly accessible.');
        }

        if ($survey->expires_at && $survey->expires_at->isPast()) {
            abort(Response::HTTP_GONE, 'Survey link has expired.');
        }
    }
}
