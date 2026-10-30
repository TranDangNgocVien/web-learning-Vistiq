<?php

namespace VistiqCore\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use VistiqCore\Services\Shared\CompactDatabase;

trait ReadsCompactCatalog
{
    protected static function bootReadsCompactCatalog(): void
    {
        static::addGlobalScope('compact-catalog', function (Builder $query): void {
            $query->from(CompactDatabase::source($query->getModel()->getTable()));
        });
    }
}
