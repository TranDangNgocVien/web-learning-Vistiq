<?php

namespace Tests\Admin\Feature\SystemOperations;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Admin\TestCase;
use VistiqCore\Services\System\SettingsService;

class SettingsAndAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin());
    }

    public function test_typed_settings_forms_render_all_groups_without_secrets_or_json_editor(): void
    {
        app(SettingsService::class)->put('ai', ['api_key' => 'private-secret-123']);
        foreach (array_keys(SettingsService::DEFAULTS) as $group) {
            $this->get('/admin/settings?group='.$group)->assertOk()->assertDontSee('value_json')->assertDontSee('private-secret-123');
        }
        $this->get('/admin/settings?group=ai')->assertSee('Đã cấu hình');
        $this->get('/admin/settings?group=unknown')->assertNotFound();
    }

    public function test_allowlist_dependencies_and_truncated_forms_reject_without_updates(): void
    {
        $settings = app(SettingsService::class);
        $this->putJson('/admin/settings/security', ['value' => array_replace($settings->get('security'), ['admin_otp' => true])])->assertUnprocessable()->assertJsonValidationErrors('value.admin_otp');
        $this->putJson('/admin/settings/exam', ['value' => array_replace($settings->get('exam'), ['unowned' => true])])->assertUnprocessable();
        $this->putJson('/admin/settings/ai', ['value' => array_replace($settings->get('ai'), ['enabled' => true])])->assertUnprocessable();
        $this->putJson('/admin/settings/exam', ['typed_form' => 1, 'value' => $settings->get('exam')])->assertUnprocessable()->assertJsonValidationErrors('field_count');
        $this->putJson('/admin/settings/exam', ['typed_form' => 1, 'field_count' => 999, 'value' => $settings->get('exam')])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->assertSame(30, $settings->get('exam')['duration_minutes']);
    }

    public function test_invalid_settings_preserve_safe_fields_and_never_flash_secrets(): void
    {
        $value = array_replace(app(SettingsService::class)->get('email'), ['mailer' => 'smtp', 'host' => '', 'password' => 'secret-never-flash', 'from_name' => 'Preserve this']);
        $this->from('/admin/settings?group=email')->put('/admin/settings/email', ['value' => $value])->assertRedirect('/admin/settings?group=email')->assertSessionHasErrors('value.host');
        $this->get('/admin/settings?group=email')->assertOk()->assertSee('Preserve this')->assertDontSee('secret-never-flash');
        $this->assertNull(session()->get('_old_input.value.password'));
    }

    public function test_audit_filters_and_redacts_historical_secret_diffs_on_read(): void
    {
        $actor = auth()->id();
        DB::table('audit_logs')->insert(['actor_id' => $actor, 'action' => 'legacy.secret', 'entity_type' => 'settings', 'entity_id' => 5, 'before' => json_encode(['password' => 'historic-secret', 'nested' => ['api_key' => 'historic-api']]), 'after' => '{}', 'result' => 'failure', 'created_at' => now()]);
        $this->get('/admin/audit?action=legacy.secret&entity=settings&entity_id=5&actor='.$actor.'&result=failure')->assertOk()->assertSee('legacy.secret')->assertSee('Thất bại')->assertSee('[redacted]')->assertDontSee('historic-secret')->assertDontSee('historic-api');
        $this->get('/admin/audit?result=success')->assertOk()->assertDontSee('legacy.secret');
        $this->post('/admin/audit')->assertMethodNotAllowed();
    }
}
