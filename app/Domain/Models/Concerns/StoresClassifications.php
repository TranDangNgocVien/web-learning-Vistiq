<?php

namespace VistiqCore\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use VistiqCore\Services\Shared\CompactDatabase;

trait StoresClassifications
{
    public const CLASSIFICATIONS = [
        'learning_items' => ['learning_item_type_id' => ['learning_item_types', 'type', 'code'], 'proficiency_level_id' => ['proficiency_levels', 'proficiency_level', 'code']],
        'exam_parts' => ['skill_id' => ['skills', 'skill_code', 'code']],
        'exam_templates' => ['practice_mode_id' => ['practice_modes', 'practice_mode', 'code']],
        'question_bank_items' => ['question_difficulty_id' => ['question_difficulties', 'difficulty_code', 'code']],
    ];

    protected static function bootStoresClassifications(): void
    {
        static::saving(function ($model): void {
            foreach (self::CLASSIFICATIONS[$model->getTable()] ?? [] as $id => [$catalog, $field, $value]) {
                if ($model->isDirty($id) || ! $model->exists) {
                    if (! Schema::hasColumn($model->getTable(), $field)) {
                        continue;
                    }
                    $row = CompactDatabase::table($catalog)->where('id', $model->$id)->first();
                    $model->$field = $row?->$value;
                    if ($field === 'type') {
                        $model->type_label = $row?->label;
                    }
                }
            }
        });
    }
}
