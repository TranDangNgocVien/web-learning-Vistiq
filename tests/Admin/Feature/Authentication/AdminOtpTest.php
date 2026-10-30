<?php

namespace Tests\Admin\Feature\Authentication;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Admin\TestCase;
use VistiqCore\Services\System\SettingsService;

class AdminOtpTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    private function challenge(): int
    {
        $this->withCookie(config('session.cookie'), session()->getId());

        return DB::table('admin_otp_challenges')->insertGetId(['user_id' => auth()->id(), 'session_hash' => hash('sha256', session()->getId()), 'code_hash' => Hash::make('123456'), 'tries' => 0, 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_otp_is_off_by_default_and_when_enabled_protects_admin(): void
    {
        $this->assertFalse(app(SettingsService::class)->get('security')['admin_otp']);
        $this->actingAs($this->admin())->get('/admin')->assertOk();
        app(SettingsService::class)->put('security', ['admin_otp' => true]);
        $this->get('/admin')->assertRedirect('/admin/verify');
    }

    public function test_otp_can_only_be_used_once(): void
    {
        $this->actingAs($this->admin())->get('/admin/verify')->assertOk();
        $id = $this->challenge();
        $this->post('/admin/verify', ['code' => '123456'])->assertRedirect('/admin');
        $this->assertNotNull(DB::table('admin_otp_challenges')->find($id)->consumed_at);
        $this->post('/admin/verify', ['code' => '123456'])->assertSessionHasErrors('code');
    }

    public function test_five_invalid_tries_block_correct_code_and_expired_codes_fail(): void
    {
        $this->actingAs($this->admin())->get('/admin/verify');
        $id = $this->challenge();
        for ($index = 0; $index < 5; $index++) {
            $this->post('/admin/verify', ['code' => '999999'])->assertSessionHasErrors('code');
        }
        $this->assertSame(5, DB::table('admin_otp_challenges')->find($id)->tries);
        $this->travel(61)->seconds();
        $this->post('/admin/verify', ['code' => '123456'])->assertSessionHasErrors('code');
        DB::table('admin_otp_challenges')->where('id', $id)->update(['tries' => 0, 'expires_at' => now()->subMinute()]);
        $this->post('/admin/verify', ['code' => '123456'])->assertSessionHasErrors('code');
    }
}
