<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamTemplate extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use \VistiqCore\Models\Concerns\StoresClassifications;

    protected $fillable = ['exam_type_id', 'practice_mode_id', 'score_scale_id', 'created_by', 'title', 'slug', 'provider', 'description', 'status', 'published_at', 'variant', 'mode', 'draft_status', 'draft_payload', 'revision_counter', 'published_revision_id', 'opens_at', 'closes_at', 'leaderboard_reset_at', 'review_note'];

    protected function casts(): array
    {
        return ['draft_payload' => 'array', 'published_at' => 'datetime', 'opens_at' => 'datetime', 'closes_at' => 'datetime', 'leaderboard_reset_at' => 'datetime'];
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(ExamRevision::class, 'published_revision_id');
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class, 'exam_template_id');
    }
}
