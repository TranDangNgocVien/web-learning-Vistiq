<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'supports_options'])]
class QuestionType extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['supports_options' => 'boolean'];
    }

    /** @return HasMany<QuestionBankItem, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuestionBankItem::class);
    }
}
