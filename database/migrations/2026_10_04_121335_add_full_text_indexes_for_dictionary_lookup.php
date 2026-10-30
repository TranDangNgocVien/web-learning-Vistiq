<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('learning_items', function (Blueprint $table): void {
            $table->fullText('canonical_text', 'learning_items_canonical_text_fulltext');
        });

        Schema::table('learning_item_meanings', function (Blueprint $table): void {
            $table->fullText('translation', 'learning_item_meanings_translation_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('learning_item_meanings', function (Blueprint $table): void {
            $table->dropFullText('learning_item_meanings_translation_fulltext');
        });

        Schema::table('learning_items', function (Blueprint $table): void {
            $table->dropFullText('learning_items_canonical_text_fulltext');
        });
    }
};
