<?php

namespace Tests\Admin\Feature\SystemOperations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqAdmin\Services\Admin\System\DatabaseBackupService;

class BackupRestoreTest extends TestCase
{
    use DatabaseMigrations;

    public function test_exported_data_can_be_restored_without_losing_quoted_text(): void
    {
        $user = User::factory()->create(['name' => "A; B's name"]);
        $service = app(DatabaseBackupService::class);
        ob_start();
        try {
            $service->export(false, true);
            $sql = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $user->update(['name' => 'Changed']);
        $this->assertGreaterThan(0, $service->restore($sql));
        $this->assertSame("A; B's name", $user->fresh()->name);
    }

    public function test_schema_restore_preserves_unique_indexes(): void
    {
        $user = User::factory()->create();
        $service = app(DatabaseBackupService::class);
        ob_start();
        try {
            $service->export(true, true);
            $sql = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('users_email_unique', $sql);
        $service->restore($sql);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->expectException(QueryException::class);
        User::factory()->create(['email' => $user->email]);
    }

    public function test_schema_export_is_non_destructive(): void
    {
        $service = app(DatabaseBackupService::class);
        ob_start();
        try {
            $service->export(true, false);
            $sql = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertStringNotContainsString('DROP TABLE', $sql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $sql);
    }

    public function test_all_statements_are_validated_before_any_write(): void
    {
        $user = User::factory()->create();
        $sql = "-- Vistiq Database Backup v1\n-- Driver: sqlite\nDELETE FROM \"users\"; ATTACH DATABASE 'attack.db' AS other;";
        try {
            app(DatabaseBackupService::class)->restore($sql);
            $this->fail('Expected invalid SQL rejection');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('không được hỗ trợ', $exception->getMessage());
        }
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_cross_database_and_truncated_sql_are_rejected_before_mutation(): void
    {
        $user = User::factory()->create();
        foreach (['DELETE FROM other.users;', "DELETE FROM \"users\"; INSERT INTO \"users\" (name) VALUES ('unclosed"] as $statement) {
            try {
                app(DatabaseBackupService::class)->restore("-- Vistiq Database Backup v1\n-- Driver: sqlite\n".$statement);
                $this->fail('Expected invalid SQL rejection');
            } catch (\RuntimeException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }
}
