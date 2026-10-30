<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['created_by', 'title', 'stimulus_type', 'body', 'asset_path', 'accent', 'duration_seconds', 'transcript', 'status', 'published_at'])]
class LearningStimulus extends Model
{
    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<QuestionBankItem, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuestionBankItem::class);
    }
}
