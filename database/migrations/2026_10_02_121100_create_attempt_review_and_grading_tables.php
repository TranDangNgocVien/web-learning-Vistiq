<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_event_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
        });

        Schema::create('attempt_report_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('grading_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['exam_type_id', 'skill_id', 'is_active']);
        });

        Schema::create('grading_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_rubric_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->decimal('maximum_score', 7, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['grading_rubric_id', 'code']);
            $table->index(['grading_rubric_id', 'sort_order']);
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_template_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 24)->default('in_progress')->index();
            $table->string('leaderboard_status', 24)->default('private')->index();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_template_id', 'attempt_number']);
            $table->index(['exam_template_id', 'status', 'leaderboard_status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_section_question_id')->constrained()->restrictOnDelete();
            $table->longText('response_text')->nullable();
            $table->string('response_asset_path', 2048)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('awarded_points', 7, 2)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'exam_section_question_id']);
        });

        Schema::create('attempt_answer_options', function (Blueprint $table) {
            $table->foreignId('attempt_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_option_id')->constrained()->restrictOnDelete();
            $table->primary(['attempt_answer_id', 'question_option_id']);
            $table->index('question_option_id');
        });

        Schema::create('attempt_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_event_type_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->text('details')->nullable();
            $table->timestamps();

            $table->index(['exam_attempt_id', 'occurred_at']);
        });

        Schema::create('attempt_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('raw_score', 9, 2);
            $table->timestamp('calculated_at');
            $table->timestamps();
        });

        Schema::create('question_rubrics', function (Blueprint $table) {
            $table->foreignId('question_bank_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_rubric_id')->constrained()->restrictOnDelete();
            $table->primary(['question_bank_item_id', 'grading_rubric_id']);
            $table->index('grading_rubric_id');
        });

        Schema::create('answer_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_answer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_criterion_id')->constrained('grading_criteria')->restrictOnDelete();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('awarded_score', 7, 2);
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['attempt_answer_id', 'grading_criterion_id']);
        });

        Schema::create('attempt_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attempt_report_reason_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('details')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['exam_attempt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_reports');
        Schema::dropIfExists('answer_grades');
        Schema::dropIfExists('question_rubrics');
        Schema::dropIfExists('attempt_results');
        Schema::dropIfExists('attempt_events');
        Schema::dropIfExists('attempt_answer_options');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('grading_criteria');
        Schema::dropIfExists('grading_rubrics');
        Schema::dropIfExists('attempt_report_reasons');
        Schema::dropIfExists('attempt_event_types');
    }
};
