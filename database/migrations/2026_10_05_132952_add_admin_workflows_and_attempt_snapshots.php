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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 24)->default('active')->index();
            $table->unsignedInteger('session_version')->default(0);
            $table->timestamp('last_active_at')->nullable()->index();
        });
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
        });
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 24);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('number');
            $table->string('status', 24)->default('draft');
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'entity_id', 'number']);
            $table->index(['kind', 'status', 'updated_at']);
        });
        foreach (['learning_items', 'learning_stimuli', 'question_bank_items'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedBigInteger('draft_revision_id')->nullable();
                $table->unsignedBigInteger('published_revision_id')->nullable();
                $table->unsignedInteger('revision_counter')->default(0);
                $table->json('metadata')->nullable();
            });
        }
        Schema::create('question_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('kind', 40);
            $table->foreignId('exam_part_id')->constrained()->restrictOnDelete();
            $table->json('stimulus_ids');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::table('question_bank_items', function (Blueprint $table): void {
            $table->foreignId('question_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('group_order')->default(0);
        });
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('disk', 40);
            $table->string('path', 2048);
            $table->string('title');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('score_scales', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
            $table->json('configuration')->nullable();
        });
        Schema::table('exam_templates', function (Blueprint $table): void {
            $table->string('variant', 24)->nullable();
            $table->string('mode', 24)->default('mini');
            $table->string('draft_status', 24)->default('draft');
            $table->json('draft_payload')->nullable();
            $table->unsignedInteger('revision_counter')->default(0);
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('leaderboard_reset_at')->nullable();
            $table->text('review_note')->nullable();
        });
        Schema::create('exam_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->json('payload');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['exam_template_id', 'number']);
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->foreignId('exam_revision_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('snapshot')->nullable();
            $table->unsignedSmallInteger('active_section')->default(0);
            $table->timestamp('section_started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('paused_seconds')->default(0);
            $table->string('client_session', 80)->nullable();
        });
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->unsignedBigInteger('exam_section_question_id')->nullable()->change();
            $table->string('item_key', 80)->nullable();
            $table->json('response')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('spent_seconds')->default(0);
            $table->boolean('flagged')->default(false);
            $table->json('grade')->nullable();
            $table->unique(['exam_attempt_id', 'item_key']);
        });
        Schema::table('attempt_events', function (Blueprint $table): void {
            $table->string('event_key', 80)->nullable();
            $table->string('item_key', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->unique(['exam_attempt_id', 'event_key']);
        });
        Schema::table('attempt_results', function (Blueprint $table): void {
            $table->decimal('scaled_score', 8, 2)->nullable();
            $table->json('details')->nullable();
            $table->string('grading_status', 24)->default('completed');
        });
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 40)->unique();
            $table->longText('value');
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('entity_type', 80);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('device', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
        Schema::create('user_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action', 80);
            $table->string('entity', 100)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['occurred_at', 'user_id']);
        });
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->json('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('admin_otp_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64)->unique();
            $table->string('code_hash');
            $table->unsignedTinyInteger('tries')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ai_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('attempt_answer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('purpose', 40);
            $table->string('status', 24)->default('pending');
            $table->json('input');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['actor_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['ai_tasks', 'admin_otp_challenges', 'user_subscriptions', 'subscription_plans', 'user_activities', 'audit_logs', 'system_settings'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('attempt_results', fn (Blueprint $table) => $table->dropColumn(['scaled_score', 'details', 'grading_status']));
        Schema::table('attempt_events', function (Blueprint $table): void {
            $table->dropUnique(['exam_attempt_id', 'event_key']);
            $table->dropColumn(['event_key', 'item_key', 'metadata']);
        });
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->dropUnique(['exam_attempt_id', 'item_key']);
            $table->dropColumn(['item_key', 'response', 'version', 'spent_seconds', 'flagged', 'grade']);
        });
        Schema::table('exam_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exam_revision_id');
            $table->dropColumn(['snapshot', 'active_section', 'section_started_at', 'paused_at', 'paused_seconds', 'client_session']);
        });
        Schema::dropIfExists('exam_revisions');
        Schema::table('exam_templates', fn (Blueprint $table) => $table->dropColumn(['variant', 'mode', 'draft_status', 'draft_payload', 'revision_counter', 'published_revision_id', 'opens_at', 'closes_at', 'leaderboard_reset_at', 'review_note']));
        Schema::table('score_scales', fn (Blueprint $table) => $table->dropColumn(['version', 'configuration']));
        Schema::dropIfExists('media_assets');
        Schema::table('question_bank_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_group_id');
            $table->dropColumn('group_order');
        });
        Schema::dropIfExists('question_groups');
        foreach (['question_bank_items', 'learning_stimuli', 'learning_items'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['draft_revision_id', 'published_revision_id', 'revision_counter', 'metadata']));
        }
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['last_active_at']);
            $table->dropColumn(['status', 'session_version', 'last_active_at']);
        });
    }
};
