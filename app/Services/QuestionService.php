<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class QuestionService
{
    public function __construct(
        protected Question $question
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Question
    {
        return DB::transaction(function () use ($data) {
            $question = $this->question->newQuery()->create([
                'survey_id' => $data['survey_id'],
                'question_text' => $data['question_text'],
                'type' => QuestionType::from($data['type']),
                'required' => Arr::get($data, 'required', false),
            ]);

            $options = collect(Arr::get($data, 'options', []))
                ->map(fn ($option) => [
                    'question_id' => $question->id,
                    'option_text' => $option['option_text'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if (! empty($options)) {
                $question->options()->insert($options);
            }

            return $question->load('options');
        });
    }
}
