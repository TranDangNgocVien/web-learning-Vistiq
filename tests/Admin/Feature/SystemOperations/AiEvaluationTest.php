<?php

namespace Tests\Admin\Feature\SystemOperations;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Admin\TestCase;
use VistiqAdmin\Jobs\ProcessAiTask;
use VistiqCore\Services\Shared\AiService;
use VistiqCore\Services\System\SettingsService;

class AiEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        Queue::fake();
        Http::preventStrayRequests();
    }

    public function test_missing_configuration_returns_validation_error_without_network_call(): void
    {
        $this->actingAs($this->admin('content-admin'))->postJson('/admin/ai', ['purpose' => 'translation', 'input' => ['text' => 'Hello']])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_quota_is_atomic_and_result_is_only_reviewer_suggestion(): void
    {
        app(SettingsService::class)->put('ai', ['enabled' => true, 'api_key' => 'test-secret', 'model' => 'test-model', 'translation' => true, 'daily_limit' => 1]);
        $actor = $this->admin('content-admin');
        $this->actingAs($actor);
        $service = app(AiService::class);
        $id = $service->enqueue('translation', ['text' => 'Hello'], $actor->id);
        $this->postJson('/admin/ai', ['purpose' => 'translation', 'input' => ['text' => 'Again']])->assertStatus(429);
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"text":"Xin chào"}']]]])]);
        $service->process($id);
        $result = json_decode(DB::table('ai_tasks')->value('result'), true);
        $this->assertSame('Xin chào', $result['text']);
        $this->assertTrue($result['requires_reviewer']);
        $this->assertTrue($result['estimated']);
        $this->assertDatabaseCount('attempt_results', 0);
    }

    public function test_http_failure_does_not_expose_api_key_and_failed_job_records_clear_error(): void
    {
        app(SettingsService::class)->put('ai', ['enabled' => true, 'api_key' => 'test-secret', 'model' => 'test-model', 'translation' => true]);
        $id = app(AiService::class)->enqueue('translation', ['text' => 'Hello'], $this->admin()->id);
        Http::fake(['*' => Http::response(['error' => 'test-secret'], 500)]);
        try {
            app(AiService::class)->process($id);
            $this->fail('Expected remote failure');
        } catch (\RuntimeException $exception) {
            $this->assertStringNotContainsString('test-secret', $exception->getMessage());
            (new ProcessAiTask($id))->failed($exception);
        }
        $this->assertDatabaseHas('ai_tasks', ['id' => $id, 'status' => 'failed']);
    }
}
