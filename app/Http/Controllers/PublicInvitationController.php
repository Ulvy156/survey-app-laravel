<?php

namespace App\Http\Controllers;

use App\Http\Resources\SurveyResource;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;

class PublicInvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {
    }

    public function show(string $invitation_token): JsonResponse
    {
        $invitation = $this->invitationService->getPendingInvitation($invitation_token);

        return response()->json([
            'success' => true,
            'data' => [
                'survey' => new SurveyResource($invitation->survey),
                'email' => $invitation->email,
                'expires_at' => optional($invitation->expires_at)->toISOString(),
            ],
        ]);
    }
}
