<?php

namespace App\Http\Controllers;

use App\Http\Requests\Survey\SubmitSurveyRequest;
use App\Models\Survey;
use App\Services\InvitationService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SurveySubmissionController extends Controller
{
    public function __construct(
        protected ResponseService $responseService,
        protected InvitationService $invitationService
    ) {
    }

    public function submit(SubmitSurveyRequest $request, Survey $survey): JsonResponse
    {
        $respondent = $request->user();

        $this->ensureSurveyCanBeSubmitted($survey, $respondent->id);

        $data = $request->validated();
        $invitation = null;

        if (! empty($data['invitation_token'])) {
            $invitation = $this->invitationService->getPendingInvitationForUser(
                $data['invitation_token'],
                $survey,
                $respondent
            );
        }

        $response = $this->responseService->submit(
            $survey,
            $respondent,
            $data['answers'],
            $invitation
        );

        return response()->json([
            'success' => true,
            'message' => 'Survey submitted successfully.',
            'data' => [
                'response_id' => $response->id,
                'submitted_at' => optional($response->submitted_at)->toISOString(),
            ],
        ], Response::HTTP_CREATED);
    }

    protected function ensureSurveyCanBeSubmitted(Survey $survey, int $respondentId): void
    {
        if ((int) $survey->created_by === $respondentId) {
            abort(Response::HTTP_FORBIDDEN, 'Creators cannot submit their own surveys.');
        }

        if (! $survey->isAvailableNow()) {
            $this->denySurvey();
        }
    }

    protected function denySurvey(): void
    {
        abort(Response::HTTP_FORBIDDEN, 'Survey is closed');
    }
}
