<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['exam_templates', 'question_bank_items'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->softDeletes()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['question_bank_items', 'exam_templates'] as $tableName) {
            if (Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $table->dropIndex($tableName.'_deleted_at_index');
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
