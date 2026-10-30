<?php

namespace Tests\Admin\Feature\SystemOperations;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Admin\TestCase;
use VistiqAdmin\Services\Admin\System\DatabaseBackupService;
use VistiqAdmin\Services\Admin\System\DatabaseOperationsService;

class OperationsWorkflowTest extends TestCase
{
    use DatabaseMigrations;

    private mixed $consoleKernel = null;

    protected function tearDown(): void
    {
        if ($this->consoleKernel) {
            Artisan::swap($this->consoleKernel);
        }
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        Storage::fake('local');
    }

    private function sql(): string
    {
        ob_start();
        try {
            app(DatabaseBackupService::class)->export(true, true);

            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function ready(int $actor, ?string $sql = null): array
    {
        return app(DatabaseOperationsService::class)->preflight(UploadedFile::fake()->createWithContent('backup.sql', $sql ?? $this->sql()), $actor);
    }

    private function maintenance(): void
    {
        $this->consoleKernel = Artisan::getFacadeRoot();
        Artisan::shouldReceive('call')->with('down', ['--retry' => 60])->once()->andReturn(0);
        Artisan::shouldReceive('call')->with('up')->once()->andReturn(0);
    }

    public function test_database_and_job_pages_require_permissions_and_download_rechecks_password(): void
    {
        $reader = $this->admin('reviewer');
        foreach (['/admin/settings', '/admin/settings/database', '/admin/settings/jobs'] as $url) {
            $this->actingAs($reader)->get($url)->assertForbidden();
        }
        $admin = $this->admin('admin');
        $admin->forceFill(['password' => 'ValidPassword123'])->save();
        $this->actingAs($admin)->postJson('/admin/settings/database/backup', ['password' => 'wrong', 'include_schema' => 1, 'include_data' => 1])->assertUnprocessable();
        $this->get('/admin/settings/database')->assertOk()->assertSee('1. Kiểm tra file phục hồi');
        $this->get('/admin/settings/jobs')->assertOk()->assertSee('Hàng đợi import');
        $this->post('/admin/settings/database/backup', ['password' => 'ValidPassword123', 'include_schema' => 1, 'include_data' => 1])->assertDownload();
        $this->assertDatabaseHas('audit_logs', ['action' => 'database.backup.download']);
    }

    public function test_preflight_does_not_mutate_database_and_is_actor_bound(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $operation = $this->ready($admin->id);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertSame('ready', $operation['state']);
        $this->actingAs($other)->get('/admin/settings/database?operation='.$operation['id'])->assertNotFound();
        $this->get('/admin/settings/database/operations/'.$operation['id'])->assertNotFound();
        $this->actingAs($admin)->get('/admin/settings/database/operations/'.$operation['id'])->assertOk()->assertJsonPath('state', 'ready')->assertJsonMissingPath('checksum');
        $this->get('/admin/settings/database?operation=../../.env')->assertNotFound();
    }

    public function test_invalid_preflight_is_rejected_before_writes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/admin/settings/database/restore/preflight', ['file' => UploadedFile::fake()->createWithContent('bad.sql', "-- Vistiq Database Backup v2\n-- Driver: sqlite\nDELETE FROM users; ATTACH DATABASE 'bad.db' AS bad;")])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'database.restore.preflight', 'result' => 'failure']);
    }

    public function test_restore_rejects_expired_or_tampered_preflight(): void
    {
        $admin = $this->admin();
        $service = app(DatabaseOperationsService::class);
        $ready = $this->ready($admin->id);
        Storage::disk('local')->append('database-operations/'.$ready['id'].'.sql', "\n-- changed\n");
        try {
            $service->restore($ready['id'], $admin->id);
            $this->fail('Expected rejection');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('restore', $exception->errors());
        }
        $ready = $this->ready($admin->id);
        $this->travel(21)->minutes();
        try {
            $service->restore($ready['id'], $admin->id);
            $this->fail('Expected rejection');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('restore', $exception->errors());
        }
        $this->assertSame('ready', $service->operation($ready['id'])['state']);
    }

    public function test_successful_restore_survives_database_replacement_and_blocks_replay(): void
    {
        $this->maintenance();
        $admin = $this->admin();
        $service = app(DatabaseOperationsService::class);
        $ready = $this->ready($admin->id);
        $admin->update(['name' => 'Changed after backup']);
        $result = $service->restore($ready['id'], $admin->id);
        $this->assertSame('succeeded', $result['state']);
        $this->assertNotSame('Changed after backup', $admin->fresh()->name);
        $this->assertSame(1, (int) $admin->fresh()->session_version);
        $this->assertSame('succeeded', $service->operation($ready['id'])['state']);
        $this->assertFileExists($service->verifiedPath($result['recovery_id']));
        $this->assertDatabaseHas('audit_logs', ['action' => 'database.restore.result', 'result' => 'success']);
        try {
            $service->restore($ready['id'], $admin->id);
            $this->fail('Expected replay rejection');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('restore', $exception->errors());
        }
    }

    public function test_partial_failure_executes_recovery_before_leaving_maintenance(): void
    {
        $this->maintenance();
        $admin = $this->admin();
        $original = $admin->name;
        // Valid allowlisted SQL that fails after the delete: recovery must rebuild the original rows.
        $sql = "-- Vistiq Database Backup v2\n-- Driver: sqlite\nPRAGMA foreign_keys = OFF; DELETE FROM users; INSERT INTO users (unknown_column) VALUES ('bad'); PRAGMA foreign_keys = ON;";
        $ready = $this->ready($admin->id, $sql);
        $result = app(DatabaseOperationsService::class)->restore($ready['id'], $admin->id);
        $this->assertSame('failed', $result['state']);
        $this->assertTrue($result['recovered']);
        $this->assertFalse($result['maintenance_retained']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => $original]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'database.restore.result', 'result' => 'failure']);
    }

    public function test_artifact_download_verifies_integrity_and_rejects_paths(): void
    {
        $admin = $this->admin();
        $service = app(DatabaseOperationsService::class);
        $artifact = $service->createBackup($admin->id);
        $this->assertFileExists($service->verifiedPath($artifact['id']));
        Storage::disk('local')->append('database-operations/'.$artifact['id'].'.sql', "\n-- modified\n");
        $this->expectException(ValidationException::class);
        $service->verifiedPath($artifact['id']);
    }

    public function test_failed_restore_and_recovery_retain_maintenance_and_private_result(): void
    {
        $admin = $this->admin();
        $ready = $this->ready($admin->id);
        $this->consoleKernel = Artisan::getFacadeRoot();
        Artisan::shouldReceive('call')->with('down', ['--retry' => 60])->once()->andReturn(0);
        $this->partialMock(DatabaseBackupService::class)->shouldReceive('restore')->twice()->andThrow(new \RuntimeException('Unavailable database'));
        $service = app(DatabaseOperationsService::class);
        $result = $service->restore($ready['id'], $admin->id);
        $this->assertSame('failed', $result['state']);
        $this->assertTrue($result['maintenance_retained']);
        $this->assertTrue($service->operation($ready['id'])['maintenance_retained']);
        $this->assertArrayHasKey('recovery_id', $result);
    }
}
