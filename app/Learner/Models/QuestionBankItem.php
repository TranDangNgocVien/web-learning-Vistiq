<?php

namespace VistiqLearner\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['learning_item_id', 'status', 'published_at', 'created_by', 'exam_part_id', 'question_type_id', 'question_difficulty_id', 'learning_stimulus_id', 'prompt', 'explanation'])]
class QuestionBankItem extends Model
{
    use \VistiqCore\Models\Concerns\StoresClassifications;

    public function contentStatus(): string
    {
        return $this->learningItem?->status ?? $this->status ?? 'draft';
    }

    /** @return BelongsTo<LearningItem, $this> */
    public function learningItem(): BelongsTo
    {
        return $this->belongsTo(LearningItem::class);
    }

    /** @return BelongsTo<ExamPart, $this> */
    public function examPart(): BelongsTo
    {
        return $this->belongsTo(ExamPart::class);
    }

    /** @return BelongsTo<QuestionType, $this> */
    public function questionType(): BelongsTo
    {
        return $this->belongsTo(QuestionType::class);
    }

    /** @return BelongsTo<QuestionDifficulty, $this> */
    public function difficulty(): BelongsTo
    {
        return $this->belongsTo(QuestionDifficulty::class, 'question_difficulty_id');
    }

    /** @return BelongsTo<LearningStimulus, $this> */
    public function stimulus(): BelongsTo
    {
        return $this->belongsTo(LearningStimulus::class, 'learning_stimulus_id');
    }

    /** @return HasMany<QuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    /** @return HasMany<QuestionAcceptedAnswer, $this> */
    public function acceptedAnswers(): HasMany
    {
        return $this->hasMany(QuestionAcceptedAnswer::class);
    }
}
