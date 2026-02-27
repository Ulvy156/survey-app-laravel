<?php

namespace App\Http\Resources;

use App\Enums\QuestionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Question
 */
class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'survey_id' => $this->survey_id,
            'question_text' => $this->question_text,
            'type' => $this->type instanceof QuestionType ? $this->type->value : (string) $this->type,
            'required' => (bool) $this->required,
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
