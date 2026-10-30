<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'is_active'])]
class ExamType extends Model
{
    /** @return HasMany<ExamPart, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(ExamPart::class);
    }
}
