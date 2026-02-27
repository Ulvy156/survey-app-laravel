<?php

namespace App\Http\Requests\Question;

use App\Enums\QuestionType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
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
            'question_text' => ['required', 'string'],
            'type' => ['required', Rule::enum(QuestionType::class)],
            'required' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*.option_text' => ['required_with:options', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');
            $options = $this->input('options', []);

            if ($type === 'text' && ! empty($options)) {
                $validator->errors()->add('options', 'Text questions cannot have options.');
            }

            if (in_array($type, ['single_choice', 'multiple_choice'], true) && empty($options)) {
                $validator->errors()->add('options', 'Choice questions must include options.');
            }
        });
    }
}
