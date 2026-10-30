<?php

use Illuminate\Database\Migrations\Migration;
use VistiqCore\Services\Shared\CompactSchemaMigration;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        app(CompactSchemaMigration::class)->up();
    }

    public function down(): void
    {
        app(CompactSchemaMigration::class)->down();
    }
};
