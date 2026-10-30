<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Model;

class LearningItem extends Model
{
    use \VistiqCore\Models\Concerns\StoresClassifications;

    protected $fillable = ['learning_item_type_id', 'proficiency_level_id', 'created_by', 'canonical_text', 'slug', 'status', 'published_at', 'metadata', 'draft_revision_id', 'published_revision_id', 'revision_counter'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'published_at' => 'datetime'];
    }
}
