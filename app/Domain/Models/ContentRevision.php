<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Model;

class ContentRevision extends Model
{
    protected $fillable = ['kind', 'entity_id', 'number', 'status', 'payload', 'created_by', 'reviewed_by', 'review_note'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $revision): void {
            abort_if($revision->getOriginal('status') === 'published', 409, 'Phiên bản đã xuất bản không thể thay đổi.');
        });
        static::deleting(function (self $revision): void {
            abort_if($revision->status === 'published', 409, 'Giữ phiên bản đã xuất bản để bảo toàn lịch sử.');
        });
    }
}
