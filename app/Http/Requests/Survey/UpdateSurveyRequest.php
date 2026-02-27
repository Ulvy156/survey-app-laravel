<?php

namespace App\Http\Requests\Survey;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSurveyRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', Rule::in(['poll', 'survey'])],
            'is_active' => ['sometimes', 'boolean'],
            'available_from_time' => ['nullable', 'date_format:H:i'],
            'available_until_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('available_from_time');
            $until = $this->input('available_until_time');

            if ($from && ! $until) {
                $validator->errors()->add('available_until_time', 'The available until time is required when available from time is set.');
            }

            if ($until && ! $from) {
                $validator->errors()->add('available_from_time', 'The available from time is required when available until time is set.');
            }

            if ($from && $until) {
                $fromTime = Carbon::createFromFormat('H:i', $from);
                $untilTime = Carbon::createFromFormat('H:i', $until);

                if ($fromTime->greaterThanOrEqualTo($untilTime)) {
                    $validator->errors()->add('available_from_time', 'Available from time must be earlier than available until time.');
                }
            }
        });
    }
}
