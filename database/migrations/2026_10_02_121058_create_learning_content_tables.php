<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proficiency_levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('label', 50);
            $table->unsignedTinyInteger('sort_order')->unique();
        });

        Schema::create('parts_of_speech', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('label', 60);
            $table->unsignedTinyInteger('sort_order')->default(0);
        });

        Schema::create('learning_item_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 80);
            $table->unsignedTinyInteger('sort_order')->default(0);
        });

        Schema::create('grammar_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('learning_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('proficiency_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('canonical_text')->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'BINARY' : 'utf8mb4_bin');
            $table->string('slug')->unique();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_item_type_id', 'canonical_text']);
            $table->index(['learning_item_type_id', 'status']);
        });

        Schema::create('dictionary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phonetic', 100)->nullable();
            $table->string('audio_url', 2048)->nullable();
            $table->timestamps();
        });

        Schema::create('learning_item_meanings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_of_speech_id')->nullable()->constrained('parts_of_speech')->nullOnDelete();
            $table->unsignedSmallInteger('sense_number');
            $table->text('translation');
            $table->text('definition')->nullable();
            $table->text('usage_note')->nullable();
            $table->timestamps();

            $table->unique(['learning_item_id', 'sense_number']);
            $table->index(['learning_item_id', 'part_of_speech_id']);
        });

        Schema::create('learning_item_meaning_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_meaning_id')->constrained()->cascadeOnDelete();
            $table->text('english');
            $table->text('vietnamese')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('collocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('grammatical_pattern')->nullable();
            $table->timestamps();
        });

        Schema::create('collocation_dictionary_entries', function (Blueprint $table) {
            $table->foreignId('collocation_id')->constrained('collocations')->cascadeOnDelete();
            $table->foreignId('dictionary_entry_id')->constrained()->cascadeOnDelete();
            $table->primary(['collocation_id', 'dictionary_entry_id']);
            $table->index('dictionary_entry_id');
        });

        Schema::create('phrasal_verbs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('base_dictionary_entry_id')->constrained('dictionary_entries')->restrictOnDelete();
            $table->string('particle', 80);
            $table->timestamps();

            $table->index(['base_dictionary_entry_id', 'particle']);
        });

        Schema::create('idioms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('register', 24)->nullable();
            $table->text('usage_note')->nullable();
            $table->timestamps();
        });

        Schema::create('learning_item_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_learning_item_id')->constrained('learning_items')->cascadeOnDelete();
            $table->foreignId('target_learning_item_id')->constrained('learning_items')->cascadeOnDelete();
            $table->string('relation_type', 24);
            $table->timestamps();

            $table->unique(['source_learning_item_id', 'target_learning_item_id', 'relation_type'], 'learning_item_relation_unique');
            $table->index(['target_learning_item_id', 'relation_type'], 'lir_target_type_idx');
        });

        Schema::create('grammar_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('grammar_category_id')->constrained()->restrictOnDelete();
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->index('grammar_category_id');
        });

        Schema::create('grammar_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grammar_topic_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('formula')->nullable();
            $table->longText('explanation');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['grammar_topic_id', 'sort_order']);
        });

        Schema::create('grammar_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grammar_rule_id')->constrained()->cascadeOnDelete();
            $table->text('english');
            $table->text('vietnamese');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('learning_item_tags', function (Blueprint $table) {
            $table->foreignId('learning_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['learning_item_id', 'tag_id']);
            $table->index('tag_id');
        });

        Schema::create('user_saved_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_item_id')->constrained()->cascadeOnDelete();
            $table->text('personal_note')->nullable();
            $table->boolean('is_starred')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'learning_item_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('saved_item_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_saved_item_id')->constrained()->cascadeOnDelete();
            $table->timestamp('reviewed_at');
            $table->string('outcome', 24);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_saved_item_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_item_reviews');
        Schema::dropIfExists('user_saved_items');
        Schema::dropIfExists('learning_item_tags');
        Schema::dropIfExists('grammar_examples');
        Schema::dropIfExists('grammar_rules');
        Schema::dropIfExists('grammar_topics');
        Schema::dropIfExists('learning_item_relations');
        Schema::dropIfExists('idioms');
        Schema::dropIfExists('phrasal_verbs');
        Schema::dropIfExists('collocation_dictionary_entries');
        Schema::dropIfExists('collocations');
        Schema::dropIfExists('learning_item_meaning_examples');
        Schema::dropIfExists('learning_item_meanings');
        Schema::dropIfExists('dictionary_entries');
        Schema::dropIfExists('learning_items');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('grammar_categories');
        Schema::dropIfExists('learning_item_types');
        Schema::dropIfExists('parts_of_speech');
        Schema::dropIfExists('proficiency_levels');
    }
};
