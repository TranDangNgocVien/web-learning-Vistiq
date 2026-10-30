<?php

namespace Tests\Admin\Feature\ExamManagement;

use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Storage;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\Exams\ExamGenerationService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

class ExamGenerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdminCatalogSeeder::class);
        $this->actingAs($this->admin('content-admin'));
        Storage::fake('public');
    }

    private function question(string $partCode = 'part-5', string $difficulty = 'easy', array $overrides = []): QuestionBankItem
    {
        $part = ExamPart::with(['skill', 'examType'])->where('code', $partCode)->firstOrFail();
        $difficultyId = DB::table('question_difficulties')->where('code', $difficulty)->value('id');
        $question = QuestionBankItemFactory::new()->create(['exam_part_id' => $part->id, 'question_difficulty_id' => $difficultyId]);
        $payload = array_replace($this->item(), ['id' => $question->id, 'exam_part_id' => $part->id, 'part' => $partCode, 'exam_type' => $part->examType->code, 'skill' => $part->skill->code, 'question_difficulty_id' => $difficultyId, 'group_id' => null, 'stimulus_ids' => []], $overrides);
        $revision = ContentRevision::create(['kind' => 'question', 'entity_id' => $question->id, 'number' => 1, 'status' => 'published', 'payload' => $payload]);
        $question->forceFill(['published_revision_id' => $revision->id, 'question_group_id' => $payload['group_id'], 'group_order' => $payload['group_order'] ?? 0])->save();
        $question->learningItem->forceFill(['status' => 'published'])->save();

        return $question;
    }

    private function config(string $partCode = 'part-5', array $difficulty = ['easy' => 1, 'medium' => 0, 'hard' => 0]): array
    {
        $part = ExamPart::where('code', $partCode)->firstOrFail();

        return ['exam_type_id' => $part->exam_type_id, 'variant' => str_starts_with($partCode, 'part-') ? null : 'academic', 'generation' => ['template' => 'custom', 'sections' => [['exam_part_id' => $part->id, 'difficulty' => $difficulty]]]];
    }

    private function examData(array $config, array $generated): array
    {
        return array_replace($config, ['title' => 'Đề thử template', 'mode' => 'mini', 'sections' => $generated['sections'], 'generation' => $generated['generation'], 'policies' => ExamPublishingService::POLICIES]);
    }

    private function stimulus(): int
    {
        $stimulus = LearningStimulus::create(['title' => 'Passage', 'stimulus_type' => 'reading', 'body' => '<p>Reading passage.</p>', 'status' => 'published']);
        $revision = ContentRevision::create(['kind' => 'stimulus', 'entity_id' => $stimulus->id, 'number' => 1, 'status' => 'published', 'payload' => ['id' => $stimulus->id, 'title' => 'Passage', 'stimulus_type' => 'reading', 'body' => '<p>Reading passage.</p>', 'metadata' => []]]);
        $stimulus->forceFill(['published_revision_id' => $revision->id])->save();

        return $stimulus->id;
    }

    private function group(string $part, array $stimuli = []): int
    {
        return DB::table('question_groups')->insertGetId(['title' => 'Group', 'kind' => 'passage', 'exam_part_id' => ExamPart::where('code', $part)->value('id'), 'stimulus_ids' => json_encode($stimuli), 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_editor_displays_templates_and_unsaved_preview_for_new_exam(): void
    {
        $this->get('/admin/exams/create')->assertOk()->assertSee('IELTS Academic')->assertSee('IELTS General Training')->assertSee('TOEIC Listening')->assertSee('Tạo câu hỏi ngẫu nhiên')->assertSee('data-preview-exam', false);
    }

    public function test_thpt_english_template_has_six_fixed_parts_and_forty_questions(): void
    {
        $catalog = app(ExamGenerationService::class)->catalog();
        $template = $catalog['thpt-english'];

        $this->assertSame($catalog['thpt-english-2025']['name'], $template['name']);
        $this->assertSame(50, $template['duration_minutes']);
        $this->assertSame([6, 6, 5, 5, 8, 10], array_column($template['sections'], 'count'));
        $this->assertSame(40, array_sum(array_column($template['sections'], 'count')));
        $this->assertNotEmpty($template['score_scales'][40]);

        $sections = collect($template['sections'])->map(fn (array $section): array => [
            'exam_part_id' => $section['exam_part_id'],
            'difficulty' => ['easy' => 0, 'medium' => (int) $section['count'], 'hard' => 0],
        ])->all();
        $result = app(ExamGenerationService::class)->structure([
            'exam_type_id' => $template['exam_type_id'],
            'variant' => null,
            'generation' => ['template' => 'thpt-english', 'sections' => $sections],
        ]);

        $this->assertCount(6, $result['sections']);
        $this->assertSame([6, 6, 5, 5, 8, 10], array_column($result['sections'], 'count'));
        $this->assertFalse(collect($result['sections'])->contains(fn (array $section) => array_key_exists('duration_minutes', $section)));

        $sections[0]['difficulty']['medium']--;
        $sections[0]['difficulty']['easy']++;
        $sections[0]['exam_part_id'] = $sections[1]['exam_part_id'];
        $this->postJson('/admin/exams/generate', [
            'exam_type_id' => $template['exam_type_id'],
            'variant' => null,
            'generation' => ['template' => 'thpt-english', 'sections' => $sections],
        ])->assertUnprocessable()->assertJsonValidationErrors('generation.sections.0');
        $this->assertSame(['tieng-anh'], array_keys($catalog['thpt']['subjects']));
        $this->get('/admin/exams/create?template=thpt-english-2025')->assertOk()
            ->assertSee('data-quick-filter="short_cloze"', false)
            ->assertSee('data-quick-filter="arrangement"', false)
            ->assertSee('data-quick-filter="extended_completion"', false)
            ->assertSee('data-quick-filter="reading"', false);
    }

    public function test_thpt_custom_exam_rejects_non_english_parts(): void
    {
        $english = ExamPart::where('code', 'tieng-anh')->firstOrFail();
        $math = ExamPart::create([
            'exam_type_id' => $english->exam_type_id,
            'skill_id' => $english->skill_id,
            'code' => 'toan',
            'name' => 'Toán',
            'sort_order' => 99,
        ]);

        $this->postJson('/admin/exams/generate', [
            'exam_type_id' => $math->exam_type_id,
            'variant' => null,
            'generation' => ['template' => 'custom', 'sections' => [['exam_part_id' => $math->id, 'difficulty' => ['easy' => 1, 'medium' => 0, 'hard' => 0]]]],
        ])->assertUnprocessable()->assertJsonValidationErrors('generation.sections.0');
    }

    public function test_custom_generation_matches_exact_difficulty_and_never_repeats_questions(): void
    {
        foreach (['easy', 'medium', 'hard'] as $difficulty) {
            for ($i = 0; $i < 4; $i++) {
                $this->question(difficulty: $difficulty);
            }
        }
        $config = $this->config(difficulty: ['easy' => 2, 'medium' => 3, 'hard' => 1]);
        $response = $this->postJson('/admin/exams/generate', $config)->assertOk();
        $ids = $response->json('sections.0.question_ids');
        $this->assertCount(6, array_unique($ids));
        $this->assertSame(['easy' => 2, 'medium' => 3, 'hard' => 1], $response->json('summary.0.difficulty'));
        $this->post('/admin/exams', $this->examData($config, $response->json()))->assertRedirect();
        $this->assertSame($response->json('generation'), ExamTemplate::first()->draft_payload['generation']);
    }

    public function test_approved_draft_questions_are_visible_and_eligible_for_custom_generation(): void
    {
        $part = ExamPart::with(['skill', 'examType'])->where('code', 'part-5')->firstOrFail();
        $difficultyId = DB::table('question_difficulties')->where('code', 'easy')->value('id');
        $question = QuestionBankItemFactory::new()->create(['exam_part_id' => $part->id, 'question_difficulty_id' => $difficultyId]);
        $payload = array_replace($this->item(), [
            'id' => $question->id,
            'exam_part_id' => $part->id,
            'part' => $part->code,
            'exam_type' => $part->examType->code,
            'skill' => $part->skill->code,
            'question_difficulty_id' => $difficultyId,
            'group_id' => null,
            'stimulus_ids' => [],
        ]);
        $revision = ContentRevision::create(['kind' => 'question', 'entity_id' => $question->id, 'number' => 1, 'status' => 'approved', 'payload' => $payload]);
        $question->forceFill(['draft_revision_id' => $revision->id])->save();

        $this->get('/admin/exams/create')
            ->assertOk()
            ->assertSee('"status":"approved"', false)
            ->assertSee('"has_approved_content":true', false);

        $this->postJson('/admin/exams/generate', $this->config())->assertOk()->assertJsonPath('sections.0.question_ids', [$question->id]);
    }

    public function test_insufficient_pool_has_actionable_error_and_no_partial_exam(): void
    {
        $this->question();
        $this->postJson('/admin/exams/generate', $this->config(difficulty: ['easy' => 2, 'medium' => 1, 'hard' => 0]))->assertUnprocessable()->assertJsonValidationErrors('generation.sections.0');
        $this->assertDatabaseCount('exam_templates', 0);
    }

    public function test_drafts_archived_invalid_and_other_parts_are_excluded(): void
    {
        QuestionBankItemFactory::new()->create();
        $archived = $this->question();
        $archived->learningItem->forceFill(['status' => 'archived'])->save();
        $this->question(overrides: ['correct_options' => []]);
        $this->question('part-2', overrides: ['options' => ['A' => 'a', 'B' => 'b', 'C' => 'c']]);
        $this->postJson('/admin/exams/generate', $this->config())->assertUnprocessable();
    }

    public function test_repeated_custom_sections_use_distinct_questions(): void
    {
        $this->question();
        $this->question();
        $config = $this->config();
        $config['generation']['sections'][] = $config['generation']['sections'][0];
        $result = $this->postJson('/admin/exams/generate', $config)->assertOk()->json('sections');
        $this->assertNotSame($result[0]['question_ids'], $result[1]['question_ids']);
    }

    public function test_groups_with_mixed_difficulty_are_kept_whole_and_ordered(): void
    {
        $group = $this->group('part-5');
        $last = $this->question(difficulty: 'hard', overrides: ['group_id' => $group, 'group_order' => 3]);
        $first = $this->question(overrides: ['group_id' => $group, 'group_order' => 1]);
        $config = $this->config(difficulty: ['easy' => 1, 'medium' => 0, 'hard' => 1]);
        $result = $this->postJson('/admin/exams/generate', $config)->assertOk()->json();
        $this->assertSame([$first->id, $last->id], $result['sections'][0]['question_ids']);
        $this->postJson('/admin/exams/generate', $this->config())->assertUnprocessable();
        $result['sections'][0]['question_ids'] = [$first->id];
        $this->postJson('/admin/exams', $this->examData($config, $result))->assertUnprocessable();
    }

    public function test_group_with_unpublished_member_cannot_be_partially_selected(): void
    {
        $group = $this->group('part-5');
        $this->question(overrides: ['group_id' => $group]);
        QuestionBankItemFactory::new()->create()->forceFill(['question_group_id' => $group])->save();
        $this->postJson('/admin/exams/generate', $this->config())->assertUnprocessable();
    }

    public function test_ielts_variant_and_multiple_answer_units_are_respected(): void
    {
        $reading = $this->stimulus();
        $blanks = ['1' => ['label' => 'First', 'accepted_answers' => ['one']], '2' => ['label' => 'Second', 'accepted_answers' => ['two']]];
        $academic = $this->question('reading-part-1', overrides: ['type' => 'note_completion', 'metadata' => ['variant' => 'academic', 'blanks' => $blanks], 'stimulus_ids' => [$reading]]);
        $this->question('reading-part-1', overrides: ['metadata' => ['variant' => 'general'], 'stimulus_ids' => [$reading]]);
        $config = $this->config('reading-part-1', ['easy' => 2, 'medium' => 0, 'hard' => 0]);
        $response = $this->postJson('/admin/exams/generate', $config)->assertOk();
        $this->assertSame([$academic->id], $response->json('sections.0.question_ids'));
        $this->postJson('/admin/exams/generate', $this->config('reading-part-1'))->assertUnprocessable();
    }

    public function test_difficulty_and_preview_use_published_payload_when_question_has_new_draft(): void
    {
        $question = $this->question();
        $draft = ContentRevision::create(['kind' => 'question', 'entity_id' => $question->id, 'number' => 2, 'status' => 'draft', 'payload' => array_replace($question->publishedRevision->payload, ['prompt' => 'Unpublished edit'])]);
        $question->forceFill(['draft_revision_id' => $draft->id, 'question_difficulty_id' => DB::table('question_difficulties')->where('code', 'hard')->value('id')])->save();
        $config = $this->config();
        $generated = $this->postJson('/admin/exams/generate', $config)->assertOk()->json();
        $this->post('/admin/exams/preview-draft', $this->examData($config, $generated))->assertOk()->assertDontSee('Unpublished edit')->assertSee('Select the answer.');
        $this->assertDatabaseCount('exam_templates', 0);
        $this->assertDatabaseCount('exam_attempts', 0);
        $this->assertDatabaseCount('attempt_results', 0);
    }

    public function test_save_rejects_tampered_selection_and_cross_exam_template(): void
    {
        $this->question();
        $hard = $this->question(difficulty: 'hard');
        $config = $this->config();
        $generated = $this->postJson('/admin/exams/generate', $config)->assertOk()->json();
        $generated['sections'][0]['question_ids'] = [$hard->id];
        $this->postJson('/admin/exams', $this->examData($config, $generated))->assertUnprocessable()->assertJsonValidationErrors('sections');
        $config['generation']['template'] = 'ielts-academic';
        $this->postJson('/admin/exams/generate', $config)->assertUnprocessable();
        $this->assertDatabaseCount('exam_templates', 0);
    }

    public function test_reviewer_and_learner_cannot_generate_or_preview_unsaved_content(): void
    {
        $this->actingAs($this->admin('reviewer'))->postJson('/admin/exams/generate', $this->config())->assertForbidden();
        $this->postJson('/admin/exams/preview-draft', [])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/admin/exams/generate', $this->config())->assertForbidden();
    }

    public function test_full_toeic_generation_preserves_required_groups_and_passage_split(): void
    {
        $audio = MediaAsset::create(['title' => 'Audio', 'disk' => 'public', 'path' => 'audio.mp3', 'mime_type' => 'audio/mpeg', 'size' => 10]);
        $image = MediaAsset::create(['title' => 'Image', 'disk' => 'public', 'path' => 'image.png', 'mime_type' => 'image/png', 'size' => 10]);
        Storage::disk('public')->put('audio.mp3', 'audio');
        Storage::disk('public')->put('image.png', 'image');
        $passages = [$this->stimulus(), $this->stimulus()];
        $preset = app(ExamGenerationService::class)->catalog()['toeic'];
        foreach ($preset['sections'] as $rule) {
            $code = ExamPart::find($rule['exam_part_id'])->code;
            $sizes = match ($code) {
                'part-3' => array_fill(0, 13, 3), 'part-4' => array_fill(0, 10, 3), 'part-6' => array_fill(0, 4, 4),
                'part-7' => [...array_fill(0, 7, 3), 4, 4, ...array_fill(0, 5, 5)], default => array_fill(0, $rule['count'], 1),
            };
            foreach ($sizes as $groupIndex => $size) {
                $stimuli = in_array($code, ['part-6', 'part-7']) ? ($code === 'part-7' && $groupIndex >= 9 ? $passages : [$passages[0]]) : [];
                $group = in_array($code, ['part-3', 'part-4', 'part-6', 'part-7']) ? $this->group($code, $stimuli) : null;
                for ($i = 0; $i < $size; $i++) {
                    $metadata = in_array($code, ['part-1', 'part-2', 'part-3', 'part-4']) ? ['audio_id' => $audio->id] : [];
                    if ($code === 'part-1') {
                        $metadata['image_id'] = $image->id;
                    }
                    $this->question($code, overrides: ['group_id' => $group, 'group_order' => $i, 'metadata' => $metadata, 'options' => $code === 'part-2' ? ['A' => 'a', 'B' => 'b', 'C' => 'c'] : $this->item()['options']]);
                }
            }
        }
        $config = ['exam_type_id' => $preset['exam_type_id'], 'variant' => null, 'generation' => ['template' => 'toeic', 'sections' => array_map(fn ($rule) => [...$rule, 'difficulty' => ['easy' => $rule['count'], 'medium' => 0, 'hard' => 0]], $preset['sections'])]];
        $generated = $this->postJson('/admin/exams/generate', $config)->assertOk()->json();
        $ids = array_merge(...array_column($generated['sections'], 'question_ids'));
        $this->assertCount(200, array_unique($ids));
        $data = $this->examData($config, $generated);
        $data['mode'] = 'full';
        $data['policies']['duration_minutes'] = 120;
        $this->post('/admin/exams', $data)->assertRedirect();
        $snapshot = app(ExamPublishingService::class)->assemble(ExamTemplate::first());
        $this->assertSame(['Full Simulation cần bảng quy đổi điểm.'], app(ExamPublishingService::class)->problems($snapshot));
    }

    public function test_full_ielts_template_has_four_skills_and_exact_section_counts(): void
    {
        $preset = app(ExamGenerationService::class)->catalog()['ielts-academic'];
        $audio = MediaAsset::create(['title' => 'Audio', 'disk' => 'public', 'path' => 'listening.mp3', 'mime_type' => 'audio/mpeg', 'size' => 10]);
        Storage::disk('public')->put('listening.mp3', 'audio');
        $reading = $this->stimulus();
        foreach ($preset['sections'] as $rule) {
            $part = ExamPart::with('skill')->find($rule['exam_part_id']);
            for ($i = 0; $i < $rule['count']; $i++) {
                $metadata = ['variant' => 'academic'];
                $type = 'multiple_choice';
                if ($part->skill->code === 'listening') {
                    $metadata['audio_id'] = $audio->id;
                }
                if ($part->skill->code === 'writing') {
                    $type = 'essay';
                    $metadata['rubric'] = array_fill_keys(QuestionRegistry::WRITING_CRITERIA, 'Guidance');
                }
                if ($part->skill->code === 'speaking') {
                    $type = 'speaking';
                    $metadata['rubric'] = array_fill_keys(QuestionRegistry::SPEAKING_CRITERIA, 'Guidance');
                }
                $this->question($part->code, overrides: ['type' => $type, 'metadata' => $metadata, 'stimulus_ids' => $part->skill->code === 'reading' ? [$reading] : []]);
            }
        }
        $config = ['exam_type_id' => $preset['exam_type_id'], 'variant' => 'academic', 'generation' => ['template' => 'ielts-academic', 'sections' => array_map(fn ($rule) => [...$rule, 'difficulty' => ['easy' => $rule['count'], 'medium' => 0, 'hard' => 0]], $preset['sections'])]];
        $generated = $this->postJson('/admin/exams/generate', $config)->assertOk()->json();
        $this->assertCount(12, $generated['sections']);
        $this->assertCount(85, array_merge(...array_column($generated['sections'], 'question_ids')));
        $data = $this->examData($config, $generated);
        $data['mode'] = 'full';
        $data['policies']['duration_minutes'] = 164;
        $this->post('/admin/exams', $data)->assertRedirect();
        $snapshot = app(ExamPublishingService::class)->assemble(ExamTemplate::first());
        $this->assertSame(['Full Simulation cần bảng quy đổi điểm.'], app(ExamPublishingService::class)->problems($snapshot));
    }

    public function test_legacy_published_questions_are_eligible_without_creating_revisions(): void
    {
        $question = QuestionBankItemFactory::new()->create();
        $question->learningItem->forceFill(['status' => 'published'])->save();
        foreach (['A', 'B', 'C', 'D'] as $key) {
            $question->options()->create(['option_key' => $key, 'content' => $key, 'is_correct' => $key === 'A']);
        }
        $this->postJson('/admin/exams/generate', $this->config())->assertOk()->assertJsonPath('sections.0.question_ids', [$question->id]);
        $this->assertDatabaseCount('content_revisions', 0);
    }

    public function test_standard_template_cannot_change_counts_and_malformed_quotas_are_rejected(): void
    {
        $preset = app(ExamGenerationService::class)->catalog()['toeic'];
        $config = ['exam_type_id' => $preset['exam_type_id'], 'variant' => null, 'generation' => ['template' => 'toeic', 'sections' => array_map(fn ($rule) => [...$rule, 'difficulty' => ['easy' => $rule['count'], 'medium' => 0, 'hard' => 0]], $preset['sections'])]];
        $config['generation']['sections'][0]['difficulty']['easy'] = 5;
        $this->postJson('/admin/exams/generate', $config)->assertUnprocessable()->assertJsonValidationErrors('generation.sections.0');
        $config = $this->config();
        $config['generation']['sections'][0]['difficulty']['easy'] = -1;
        $this->postJson('/admin/exams/generate', $config)->assertUnprocessable();
        $config['generation']['sections'][0]['difficulty']['easy'] = 1.5;
        $this->postJson('/admin/exams/generate', $config)->assertUnprocessable();
    }
}
