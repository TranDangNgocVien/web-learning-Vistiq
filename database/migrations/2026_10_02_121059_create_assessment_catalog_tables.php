<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('practice_modes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
        });

        Schema::create('question_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->boolean('supports_options')->default(false);
        });

        Schema::create('question_difficulties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('name', 60);
            $table->unsignedTinyInteger('sort_order')->default(0);
        });

        Schema::create('exam_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 140);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_type_id', 'code']);
            $table->index(['exam_type_id', 'skill_id', 'sort_order']);
        });

        Schema::create('learning_stimuli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('stimulus_type', 24);
            $table->longText('body')->nullable();
            $table->string('asset_path', 2048)->nullable();
            $table->longText('transcript')->nullable();
            $table->timestamps();

            $table->index('stimulus_type');
        });

        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_part_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('question_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('question_difficulty_id')->constrained()->restrictOnDelete();
            $table->foreignId('learning_stimulus_id')->nullable()->constrained('learning_stimuli')->nullOnDelete();
            $table->longText('prompt');
            $table->longText('explanation')->nullable();
            $table->timestamps();

            $table->index(['exam_part_id', 'question_difficulty_id']);
            $table->index(['question_type_id', 'question_difficulty_id'], 'qbi_type_difficulty_idx');
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_item_id')->constrained()->cascadeOnDelete();
            $table->string('option_key', 12);
            $table->longText('content');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['question_bank_item_id', 'option_key']);
            $table->index(['question_bank_item_id', 'sort_order']);
        });

        Schema::create('question_accepted_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_bank_item_id')->constrained()->cascadeOnDelete();
            $table->text('answer_text');
            $table->string('normalized_answer', 255);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['question_bank_item_id', 'normalized_answer'], 'question_answer_value_unique');
        });

        Schema::create('score_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->timestamps();
        });

        Schema::create('score_scale_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_scale_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('minimum_raw_score');
            $table->unsignedSmallInteger('maximum_raw_score');
            $table->decimal('minimum_scaled_score', 8, 2)->nullable();
            $table->decimal('maximum_scaled_score', 8, 2)->nullable();
            $table->string('band_label', 40)->nullable();
            $table->timestamps();

            $table->unique(['score_scale_id', 'minimum_raw_score']);
            $table->index(['score_scale_id', 'maximum_raw_score']);
        });

        Schema::create('exam_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('practice_mode_id')->constrained()->restrictOnDelete();
            $table->foreignId('score_scale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('slug', 200)->unique();
            $table->string('provider', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['exam_type_id', 'practice_mode_id', 'status']);
        });

        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_part_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->timestamps();

            $table->unique(['exam_template_id', 'sort_order']);
            $table->index(['exam_template_id', 'exam_part_id']);
        });

        Schema::create('exam_section_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_bank_item_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('points', 7, 2)->default(1);
            $table->timestamps();

            $table->unique(['exam_section_id', 'question_bank_item_id'], 'esq_section_question_unique');
            $table->unique(['exam_section_id', 'sort_order']);
        });

        Schema::create('exam_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_template_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->boolean('is_timed')->default(true);
            $table->boolean('disable_copy_paste')->default(true);
            $table->boolean('track_focus_changes')->default(true);
            $table->boolean('require_fullscreen')->default(false);
            $table->boolean('auto_submit_on_expiry')->default(true);
            $table->unsignedSmallInteger('max_focus_loss_count')->nullable();
            $table->boolean('answers_visible_after_submit')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_policies');
        Schema::dropIfExists('exam_section_questions');
        Schema::dropIfExists('exam_sections');
        Schema::dropIfExists('exam_templates');
        Schema::dropIfExists('score_scale_ranges');
        Schema::dropIfExists('score_scales');
        Schema::dropIfExists('question_accepted_answers');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('question_bank_items');
        Schema::dropIfExists('learning_stimuli');
        Schema::dropIfExists('exam_parts');
        Schema::dropIfExists('question_difficulties');
        Schema::dropIfExists('question_types');
        Schema::dropIfExists('practice_modes');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('exam_types');
    }
};
