<?php

namespace Tests\Admin\Feature\Integration;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\Role;
use VistiqCore\Services\System\SettingsService;

class AdminWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_from_admin_and_demo_does_not_bypass_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/demo/admin')->assertRedirect('/admin');
    }

    public function test_learner_is_forbidden_from_admin_and_content_editor_cannot_publish(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $learner = User::factory()->create();
        $this->actingAs($learner, 'admin')->get('/admin/questions')->assertForbidden();
        $editor = User::factory()->create();
        $editor->roles()->attach(Role::where('code', 'content-admin')->value('id'));
        $this->assertTrue(Gate::forUser($editor)->allows('content.edit'));
        $this->assertFalse(Gate::forUser($editor)->allows('content.publish'));
        $this->assertFalse(Gate::forUser($editor)->allows('settings.manage'));
    }

    public function test_settings_are_encrypted_at_rest(): void
    {
        $settings = app(SettingsService::class);
        $settings->put('ai', ['api_key' => 'private-test-key']);
        $this->assertSame('private-test-key', $settings->get('ai')['api_key']);
        $this->assertStringNotContainsString('private-test-key', DB::table('system_settings')->value('value'));
        $this->assertStringNotContainsString('private-test-key', DB::table('audit_logs')->value('after'));
    }
}
