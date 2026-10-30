<?php

namespace Tests\Admin\Feature\KnowledgeBase;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Admin\TestCase;
use VistiqCore\Services\Knowledge\KnowledgeItemService;

class ReviewerNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reviewer_can_read_and_approve_knowledge_without_edit_permission(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
        $item = app(KnowledgeItemService::class)->store(['type' => 'idiom', 'english' => 'Read without editing', 'meaning_vi' => 'Xem để duyệt']);
        $this->post('/admin/content/knowledge/'.$item->id.'/review')->assertRedirect();
        $this->actingAs($this->admin('reviewer'));
        $this->get('/admin/knowledge')->assertOk()->assertSee(route('admin.knowledge.preview', $item), false);
        $this->get('/admin/knowledge/'.$item->id.'/preview')->assertOk()->assertSee('Read without editing')->assertSee('Duyệt')->assertDontSee('name="english"', false);
        $this->get('/admin/knowledge/'.$item->id.'/edit')->assertForbidden();
        $this->post('/admin/content/knowledge/'.$item->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $item->fresh()->status);
    }
}
