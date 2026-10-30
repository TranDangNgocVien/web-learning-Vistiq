<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $fillable = ['disk', 'path', 'title', 'mime_type', 'size', 'metadata', 'created_by'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
