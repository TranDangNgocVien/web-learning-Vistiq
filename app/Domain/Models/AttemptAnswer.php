<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    protected $fillable = ['exam_attempt_id', 'exam_section_question_id', 'item_key', 'response', 'response_text', 'response_asset_path', 'is_correct', 'awarded_points', 'answered_at', 'version', 'spent_seconds', 'flagged', 'grade'];

    protected function casts(): array
    {
        return ['response' => 'array', 'grade' => 'array', 'flagged' => 'boolean', 'is_correct' => 'boolean', 'answered_at' => 'datetime'];
    }
}
