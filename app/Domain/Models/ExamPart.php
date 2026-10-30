<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['exam_type_id', 'skill_id', 'code', 'name', 'sort_order'])]
class ExamPart extends Model
{
    use \VistiqCore\Models\Concerns\StoresClassifications;

    /** @return BelongsTo<ExamType, $this> */
    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    /** @return BelongsTo<Skill, $this> */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    /** @return HasMany<QuestionBankItem, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuestionBankItem::class);
    }
}
