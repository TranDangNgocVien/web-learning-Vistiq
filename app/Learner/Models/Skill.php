<?php

namespace VistiqLearner\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'sort_order'])]
class Skill extends Model
{
    use \VistiqCore\Models\Concerns\ReadsCompactCatalog;

    /** @return HasMany<ExamPart, $this> */
    public function examParts(): HasMany
    {
        return $this->hasMany(ExamPart::class);
    }
}
