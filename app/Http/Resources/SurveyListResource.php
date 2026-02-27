<?php

namespace App\Http\Resources;

use App\Enums\SurveyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Survey
 */
class SurveyListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->type instanceof SurveyType ? $this->type->value : (string) $this->type;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $type,
            'is_active' => (bool) $this->is_active,
            'is_closed' => (bool) $this->is_closed,
            'available_now' => (bool) ($this->available_now ?? false),
            'already_submitted' => (bool) ($this->already_submitted ?? false),
        ];
    }
}
