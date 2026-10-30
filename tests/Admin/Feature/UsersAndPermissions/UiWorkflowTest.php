<?php

namespace Tests\Admin\Feature\UsersAndPermissions;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\Permission;
use VistiqCore\Models\Role;

class UiWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
    }

    public function test_failed_role_creation_keeps_its_fields_and_reopens_the_form(): void
    {
        $this->actingAs($this->admin())->from('/admin/roles')->post('/admin/roles', [
            '_identity_form' => 'create', 'code' => 'admin',
            'name' => 'Tên vai trò chưa lưu', 'description' => 'Mô tả cần giữ',
        ])->assertSessionHasErrors('code');

        $this->app['session']->reflash();
        $this->app['session']->save();
        $response = $this->get('/admin/roles')->assertOk()->assertSee('Mô tả cần giữ');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[@id="create-code-error"]')->length);
        preg_match('/<details class="panel identity-create"[^>]*>/', $response->getContent(), $detailsTag);
        $this->assertSame(1, $xpath->query('//details[contains(@class,"identity-create")][@open]')->length, $detailsTag[0] ?? 'Missing details');
        $this->assertSame('Tên vai trò chưa lưu', $xpath->query('//input[@id="create-role-name"]')->item(0)->getAttribute('value'));
    }

    public function test_failed_role_update_preserves_the_submitted_permission_selection(): void
    {
        $role = Role::create(['code' => 'ui-editor', 'name' => 'UI Editor']);
        $old = Permission::where('code', 'content.view')->firstOrFail();
        $new = Permission::where('code', 'content.edit')->firstOrFail();
        $role->permissions()->attach($old);
        $url = '/admin/roles?selected='.$role->id;
        $this->actingAs($this->admin())->from($url)->post('/admin/roles/'.$role->id, [
            '_identity_form' => 'edit', 'permission_matrix' => '1', 'name' => '', 'permissions' => [$new->id],
        ])->assertSessionHasErrors('name');

        $this->app['session']->reflash();
        $this->app['session']->save();

        $html = $this->get($url)->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//input[@id="role-perm-'.$new->id.'"][@checked]')->length);
        $this->assertSame(0, $xpath->query('//input[@id="role-perm-'.$old->id.'"][@checked]')->length);
    }

    public function test_account_workspaces_render_with_confirmation_and_responsive_navigation(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Nguyễn Minh Anh', 'email' => 'minhanh@example.test']);
        $role = Role::create(['code' => 'teacher-lead', 'name' => 'Tổ trưởng chuyên môn', 'description' => 'Quản lý nội dung và hỗ trợ đội ngũ giảng viên.']);
        $role->permissions()->sync(Permission::whereIn('code', ['admin.access', 'content.view', 'content.edit'])->pluck('id'));
        $user->roles()->attach($role);
        // Exercise real pagination in the browser preview without touching application data.
        User::factory()->count(23)->create(['status' => 'active']);
        $this->actingAs($admin);
        $pages = ['users' => '/admin/users', 'roles' => '/admin/roles?selected='.$role->id, 'user' => '/admin/users/'.$user->id];
        foreach ($pages as $name => $url) {
            $response = $this->get($url)->assertOk();
            if ($name === 'roles') {
                $response->assertSee('data-role-select', false)->assertSee('data-confirm-label="Xóa vai trò và gỡ 1 người dùng"', false);
                $document = new \DOMDocument;
                @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
                $this->assertSame(0, (new \DOMXPath($document))->query('//code')->length);
            }
            if ($name === 'user') {
                $response->assertSee('data-confirm-title="Khóa tài khoản?"', false);
            }
            if ($directory = getenv('VISTIQ_UI_PREVIEW_DIR')) {
                if (! is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
                file_put_contents($directory.'/'.$name.'.html', str_replace('http://localhost:8000', 'http://127.0.0.1:8000', $response->getContent()));
            }
        }
    }

    public function test_user_manager_without_results_permission_does_not_see_exam_reports(): void
    {
        $role = Role::create(['code' => 'accounts-only', 'name' => 'Accounts Only']);
        $role->permissions()->sync(Permission::whereIn('code', ['admin.access', 'users.manage'])->pluck('id'));
        $manager = User::factory()->create();
        $manager->roles()->attach($role);
        $target = User::factory()->create();

        $this->actingAs($manager)->get('/admin/users/'.$target->id)->assertOk()
            ->assertSee('Thao tác tài khoản')
            ->assertDontSee('Lịch sử thi')
            ->assertDontSee('Báo cáo liên quan');
    }

    public function test_user_pagination_preserves_filters_and_shows_a_distinct_second_page(): void
    {
        User::factory()->count(25)->create(['name' => 'Pagination Learner', 'status' => 'active']);
        $this->actingAs($this->admin());
        $first = $this->get('/admin/users?q=Pagination&status=active')->assertOk();
        $first->assertSee('Phân trang người dùng')->assertSee('Trang 1 / 2');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$first->getContent());
        $xpath = new \DOMXPath($document);
        $next = $xpath->query('//a[@rel="next"]')->item(0)->getAttribute('href');
        parse_str(parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame('Pagination', $query['q']);
        $this->assertSame('active', $query['status']);
        $this->assertSame('2', $query['page']);

        $second = $this->get($next)->assertOk()->assertSee('Trang 2 / 2');
        $secondDocument = new \DOMDocument;
        @$secondDocument->loadHTML('<?xml encoding="UTF-8">'.$second->getContent());
        $secondXpath = new \DOMXPath($secondDocument);
        $this->assertSame(20, $xpath->query('//tbody/tr')->length);
        $this->assertSame(5, $secondXpath->query('//tbody/tr')->length);
        $firstLinks = array_map(fn ($node) => $node->getAttribute('href'), iterator_to_array($xpath->query('//tbody//a[contains(@class,"identity-person")]')));
        $secondLinks = array_map(fn ($node) => $node->getAttribute('href'), iterator_to_array($secondXpath->query('//tbody//a[contains(@class,"identity-person")]')));
        $this->assertSame([], array_intersect($firstLinks, $secondLinks));
    }
}
