<?php

namespace App\Http\Requests\Survey;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invitation_token' => ['nullable', 'uuid'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer_text' => ['nullable', 'string'],
            'answers.*.selected_option_id' => ['nullable', 'integer'],
            'answers.*.selected_option_ids' => ['nullable', 'array', 'min:1'],
            'answers.*.selected_option_ids.*' => ['integer'],
        ];
    }
}
