<?php

namespace App\Mail;

use App\Models\SurveyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SurveyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SurveyInvitation $invitation
    ) {
    }

    public function build(): self
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $invitePath = trim(config('frontend.invite_path', 'invite'), '/');
        $frontendUrl = sprintf(
            '%s/%s/%s',
            $baseUrl,
            $invitePath,
            $this->invitation->invitation_token
        );

        return $this->subject('You have been invited to take a survey')
            ->view('emails.survey_invitation', [
                'survey' => $this->invitation->survey,
                'invitation' => $this->invitation,
                'frontendUrl' => $frontendUrl,
            ]);
    }
}
