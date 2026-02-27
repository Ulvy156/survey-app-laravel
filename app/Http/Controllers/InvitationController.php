<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Survey\SurveyInviteRequest;
use App\Http\Resources\SurveyInvitationResource;
use App\Models\Survey;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {
    }

    public function store(SurveyInviteRequest $request, Survey $survey): JsonResponse
    {
        $this->ensureSurveyManager($request->user(), $survey);

        $invitations = $this->invitationService->sendInvitations(
            $survey,
            $request->validated()['emails'],
            $request->date('expires_at')
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitations queued successfully.',
            'data' => SurveyInvitationResource::collection($invitations),
        ], Response::HTTP_ACCEPTED);
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
