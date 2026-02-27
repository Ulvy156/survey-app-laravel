<?php

namespace App\Http\Resources;

use App\Enums\SurveyInvitationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SurveyInvitation
 */
class SurveyInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof SurveyInvitationStatus
            ? $this->status->value
            : (string) $this->status;

        return [
            'id' => $this->id,
            'survey_id' => $this->survey_id,
            'email' => $this->email,
            'status' => $status,
            'invitation_token' => $this->invitation_token,
            'expires_at' => optional($this->expires_at)->toISOString(),
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
