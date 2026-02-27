<?php

namespace App\Http\Resources;

use App\Enums\SurveyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Survey
 */
class SurveyResource extends JsonResource
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
            'description' => $this->description,
            'type' => $type,
            'is_active' => (bool) $this->is_active,
            'is_closed' => (bool) $this->is_closed,
            'created_by' => $this->created_by,
            'available_from_time' => $this->available_from_time,
            'available_until_time' => $this->available_until_time,
            'created_at' => optional($this->created_at)->toISOString(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }
}
