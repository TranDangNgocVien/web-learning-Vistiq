<?php

namespace Tests\Admin\Feature\Analytics;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;

class AnalyticsAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_admin_can_view_analytics_dashboard(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.analytics'));

        $response->assertOk()
            ->assertSee('Tổng lượt thi')
            ->assertSee('Tỷ lệ hoàn tất')
            ->assertSee('Xuất CSV');
    }

    public function test_admin_can_filter_analytics_by_mode(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.analytics', [
                'mode' => 'exam',
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString(),
            ]));

        $response->assertOk()
            ->assertSee('Xu hướng hoạt động');
    }

    public function test_admin_can_export_analytics_csv(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.analytics.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('Báo cáo thống kê Vistiq')
            ->assertSee('Tỷ lệ hoàn tất');
    }

    public function test_admin_can_view_leaderboard(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.leaderboard.index'));

        $response->assertOk()
            ->assertSee('Bảng xếp hạng');
    }

    public function test_admin_can_export_leaderboard_csv(): void
    {
        $admin = $this->admin('super-admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.leaderboard.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('Bảng xếp hạng Vistiq');
    }

    public function test_guest_cannot_access_analytics(): void
    {
        $response = $this->get(route('admin.analytics'));

        $response->assertRedirect(route('admin.login'));
    }
}
