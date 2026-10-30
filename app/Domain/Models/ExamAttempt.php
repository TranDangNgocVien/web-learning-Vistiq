<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    protected $fillable = ['user_id', 'exam_template_id', 'attempt_number', 'status', 'leaderboard_status', 'started_at', 'submitted_at', 'expires_at', 'exam_revision_id', 'snapshot', 'active_section', 'section_started_at', 'paused_at', 'paused_seconds', 'client_session', 'current_item_index'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'started_at' => 'datetime', 'submitted_at' => 'datetime', 'expires_at' => 'datetime', 'section_started_at' => 'datetime', 'paused_at' => 'datetime'];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(ExamTemplate::class, 'exam_template_id')->withTrashed();
    }
}
