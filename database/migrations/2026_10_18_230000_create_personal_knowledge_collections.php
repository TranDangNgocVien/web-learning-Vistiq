<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_knowledge_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
        Schema::table('user_saved_items', function (Blueprint $table): void {
            $table->foreignId('collection_id')->nullable()->constrained('user_knowledge_collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_saved_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('collection_id'));
        Schema::dropIfExists('user_knowledge_collections');
    }
};
