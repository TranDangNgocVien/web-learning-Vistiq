<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type', 50);
            $table->string('title', 180);
            $table->text('body');
            $table->string('action_url', 2048)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('achievement_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('title', 140);
            $table->text('description');
            $table->string('metric_code', 60);
            $table->decimal('metric_threshold', 10, 2)->nullable();
            $table->string('icon', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_definition_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->primary(['user_id', 'achievement_definition_id']);
            $table->index('awarded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievement_definitions');
        Schema::dropIfExists('user_notifications');
    }
};
