<?php

namespace VistiqLearner\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'sort_order'])]
class QuestionDifficulty extends Model
{
    use \VistiqCore\Models\Concerns\ReadsCompactCatalog;

    public $timestamps = false;

    /** @return HasMany<QuestionBankItem, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuestionBankItem::class);
    }
}
