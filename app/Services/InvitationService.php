<?php

namespace App\Services;

use App\Enums\SurveyInvitationStatus;
use App\Models\Survey;
use App\Models\SurveyInvitation;
use App\Models\User;
use App\Mail\SurveyInvitationMail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InvitationService
{
    public function __construct(
        protected SurveyInvitation $invitation
    ) {
    }

    /**
     * @param  array<int, string>  $emails
     */
    public function sendInvitations(Survey $survey, array $emails, ?Carbon $expiresAt = null): Collection
    {
        $normalized = collect($emails)
            ->map(fn (string $email) => strtolower(trim($email)))
            ->unique()
            ->values();

        $invitations = $normalized->map(function (string $email) use ($survey, $expiresAt) {
            $invitation = $this->invitation->newQuery()->updateOrCreate(
                ['survey_id' => $survey->id, 'email' => $email],
                [
                    'invitation_token' => (string) Str::uuid(),
                    'status' => SurveyInvitationStatus::Pending->value,
                    'expires_at' => $expiresAt,
                ]
            );

            Mail::to($email)->send(new SurveyInvitationMail($invitation));

            return $invitation;
        });

        return $invitations;
    }

    public function getPendingInvitation(string $token): SurveyInvitation
    {
        $invitation = $this->invitation->newQuery()
            ->where('invitation_token', $token)
            ->first();

        if (! $invitation) {
            abort(Response::HTTP_NOT_FOUND, 'Invitation not found.');
        }

        if ($invitation->status !== SurveyInvitationStatus::Pending) {
            abort(Response::HTTP_GONE, 'Invitation already used.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            abort(Response::HTTP_GONE, 'Invitation expired.');
        }

        $survey = $invitation->survey;
        if (! $survey->is_active) {
            abort(Response::HTTP_FORBIDDEN, 'Survey is not active.');
        }

        return $invitation;
    }

    public function getPendingInvitationForUser(string $token, Survey $survey, User $user): SurveyInvitation
    {
        $invitation = $this->getPendingInvitation($token);

        if ((int) $invitation->survey_id !== (int) $survey->id) {
            abort(Response::HTTP_FORBIDDEN, 'Invitation does not belong to this survey.');
        }

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            abort(Response::HTTP_FORBIDDEN, 'Invitation email does not match your account.');
        }

        return $invitation;
    }
}
