<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Survey\ShareSettingsRequest;
use App\Http\Resources\SurveyResource;
use App\Models\Survey;
use App\Services\ShareService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SurveyShareController extends Controller
{
    public function __construct(
        protected ShareService $shareService
    ) {
    }

    public function store(ShareSettingsRequest $request, Survey $survey): JsonResponse
    {
        $this->ensureSurveyManager($request->user(), $survey);

        $updated = $this->shareService->updateShareSettings(
            $survey,
            (bool) $request->boolean('is_public'),
            $request->date('expires_at')
        );

        return response()->json([
            'success' => true,
            'message' => $updated->is_public
                ? 'Public sharing enabled.'
                : 'Public sharing disabled.',
            'data' => new SurveyResource($updated),
        ], Response::HTTP_OK);
    }

    protected function ensureSurveyManager($user, Survey $survey): void
    {
        if ($user->role === UserRole::Admin) {
            return;
        }

        if ((int) $survey->created_by !== (int) $user->id) {
            abort(Response::HTTP_FORBIDDEN, 'You cannot modify this survey.');
        }
    }
}
