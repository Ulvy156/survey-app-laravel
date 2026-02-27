<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\SurveyAnswer;

class Question extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'survey_id',
        'question_text',
        'type',
        'required',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'type' => QuestionType::class,
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
