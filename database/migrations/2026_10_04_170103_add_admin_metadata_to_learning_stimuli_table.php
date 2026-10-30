<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('learning_stimuli', function (Blueprint $table) {
            $table->string('accent', 40)->nullable()->after('asset_path');
            $table->unsignedInteger('duration_seconds')->nullable()->after('accent');
            $table->string('status', 24)->default('draft')->index()->after('transcript');
            $table->timestamp('published_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_stimuli', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['accent', 'duration_seconds', 'status', 'published_at']);
        });
    }
};
