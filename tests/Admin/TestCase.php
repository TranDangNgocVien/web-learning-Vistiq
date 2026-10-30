<?php

namespace Tests\Admin;

use Database\Admin\Factories\ExamTemplateFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use VistiqAdmin\Models\User;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\Role;
use VistiqCore\Services\Assessment\ContentApproval\ContentReviewService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use \Tests\Concerns\AssertsCompactStorage;

    public function actingAs(Authenticatable $user, $guard = null)
    {
        return parent::actingAs($user, $guard ?? ($user->isAdmin() ? 'admin' : 'web'));
    }

    public function createApplication()
    {
        $_SERVER['HTTP_X_VISTIQ_SURFACE'] = 'admin';
        unset($_SERVER['REQUEST_URI']);

        $app = parent::createApplication();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests require an isolated SQLite in-memory database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function admin(string $role = 'super-admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', $role)->value('id'));

        return $user;
    }

    protected function reviewData(string $kind, int $id): array
    {
        $review = app(ContentReviewService::class)->preview($kind, $id);

        return ['revision_counter' => $review['number'], 'review_token' => $review['token']];
    }

    protected function item(array $overrides = []): array
    {
        return array_replace(['key' => 'q1', 'id' => 1, 'type' => 'multiple_choice', 'exam_type' => 'toeic', 'part' => 'part-5', 'skill' => 'reading', 'section' => 0, 'prompt' => 'Select the answer.', 'options' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'], 'correct_options' => ['B'], 'accepted_answers' => [], 'metadata' => [], 'explanation' => 'Secret explanation.', 'stimuli' => [], 'weight' => 1], $overrides);
    }

    protected function publishedExam(array $items = [], array $policies = [], array $attributes = []): ExamTemplate
    {
        $exam = ExamTemplateFactory::new()->create(array_replace(['status' => 'published'], $attributes));
        $snapshot = ['schema_version' => 1, 'exam_id' => $exam->id, 'title' => $exam->title, 'exam_type' => isset($attributes['exam_type_id']) ? 'ielts' : 'toeic', 'variant' => 'academic', 'mode' => 'mini', 'opens_at' => null, 'closes_at' => null, 'items' => $items ?: [$this->item()], 'sections' => [['index' => 0, 'title' => 'Part 5', 'part' => 'part-5', 'exam_part_id' => DB::table('exam_parts')->where('code', 'part-5')->value('id'), 'skill' => 'reading', 'item_keys' => array_column($items ?: [$this->item()], 'key'), 'duration_minutes' => 0, 'timing_group' => 'section-0']], 'policies' => array_replace(ExamPublishingService::POLICIES, $policies), 'scoring' => null, 'estimated' => true];
        $revision = ExamRevision::create(['exam_template_id' => $exam->id, 'number' => 1, 'payload' => $snapshot]);
        $exam->update(['published_revision_id' => $revision->id]);

        return $exam->fresh();
    }
}
