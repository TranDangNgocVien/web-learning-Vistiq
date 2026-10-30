<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Model;

class ExamRevision extends Model
{
    protected $fillable = ['exam_template_id', 'number', 'payload', 'published_by'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => abort(409, 'Phiên bản đề đã xuất bản không thể thay đổi.'));
        static::deleting(fn () => abort(409, 'Giữ phiên bản đề đã xuất bản để bảo toàn lịch sử.'));
    }
}
