<?php

namespace Tests\Feature;

use Database\Admin\Factories\ExamTemplateFactory;
use Database\Admin\Factories\LearningItemFactory;
use Database\Admin\Factories\QuestionBankItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Admin\TestCase;
use VistiqAdmin\Models\User;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Shared\CompactDatabase;
use VistiqCore\Services\Shared\CompactSchemaMigration;

class DatabaseConsolidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => ! str_contains($path, 'consolidate_vistiq_tables')));
        Artisan::call('migrate:fresh', ['--path' => $paths, '--realpath' => true, '--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    public function test_transfer_and_rollback_preserve_multiple_senses_examples_profile_and_relations(): void
    {
        $user = User::factory()->create();
        DB::table('user_profiles')->insert(['user_id' => $user->id, 'bio' => 'Tiếng Việt — "giữ nguyên"', 'country_code' => 'VN', 'time_zone' => 'Asia/Bangkok']);
        $type = DB::table('learning_item_types')->where('code', 'vocabulary')->value('id');
        $first = DB::table('learning_items')->insertGetId(['learning_item_type_id' => $type, 'canonical_text' => 'preserve', 'slug' => 'preserve', 'status' => 'published']);
        $second = DB::table('learning_items')->insertGetId(['learning_item_type_id' => $type, 'canonical_text' => 'preservation', 'slug' => 'preservation', 'status' => 'published']);
        $entry = DB::table('dictionary_entries')->insertGetId(['learning_item_id' => $first, 'phonetic' => '/prɪˈzɜːv/']);
        $collocation = DB::table('collocations')->insertGetId(['learning_item_id' => $second, 'grammatical_pattern' => 'verb + noun']);
        DB::table('collocation_dictionary_entries')->insert(['collocation_id' => $collocation, 'dictionary_entry_id' => $entry]);
        foreach ([1, 2] as $sense) {
            $meaning = DB::table('learning_item_meanings')->insertGetId(['learning_item_id' => $first, 'sense_number' => $sense, 'translation' => 'Nghĩa '.$sense]);
            foreach ([1, 2] as $example) {
                DB::table('learning_item_meaning_examples')->insert(['learning_item_meaning_id' => $meaning, 'english' => 'Example '.$sense.'/'.$example, 'vietnamese' => $example === 1 ? null : 'Ví dụ', 'sort_order' => $example]);
            }
        }
        $expectedExamples = DB::table('learning_item_meaning_examples')->orderBy('id')->get()->toArray();
        app(CompactSchemaMigration::class)->up();
        $this->assertCount(43, Schema::getTableListing(schemaQualified: false));
        $this->assertFalse(Schema::hasTable('dictionary_entries'));
        $this->assertEquals($expectedExamples, CompactDatabase::table('learning_item_meaning_examples')->orderBy('id')->get()->toArray());
        $this->assertSame('Tiếng Việt — "giữ nguyên"', DB::table('users')->where('id', $user->id)->value('bio'));
        $this->assertSame(1, CompactDatabase::table('collocation_dictionary_entries')->where('collocation_id', $collocation)->count());
        $this->assertDatabaseHas('learning_item_relations', ['source_learning_item_id' => $second, 'target_learning_item_id' => $first, 'relation_type' => 'collocation_member']);
        CompactDatabase::table('user_profiles')->where('user_id', $user->id)->update(['bio' => 'Cập nhật sau chuyển đổi']);
        app(CompactSchemaMigration::class)->down();
        $this->assertCount(78, Schema::getTableListing(schemaQualified: false));
        $this->assertEquals($expectedExamples, DB::table('learning_item_meaning_examples')->orderBy('id')->get()->toArray());
        $this->assertDatabaseHas('user_profiles', ['user_id' => $user->id, 'bio' => 'Cập nhật sau chuyển đổi']);
        $this->assertDatabaseHas('collocation_dictionary_entries', ['collocation_id' => $collocation, 'dictionary_entry_id' => $entry]);
    }

    public function test_orphaned_data_stops_conversion_before_any_source_table_is_dropped(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('dictionary_entries')->insert(['learning_item_id' => 999999999, 'phonetic' => 'orphan']);
        Schema::enableForeignKeyConstraints();
        try {
            app(CompactSchemaMigration::class)->up();
            $this->fail('An orphan must fail verification.');
        } catch (\RuntimeException $error) {
            $this->assertMatchesRegularExpression('/(?:Verification failed for|Missing owner while storing) dictionary_entries/', $error->getMessage());
        }
        $this->assertTrue(Schema::hasTable('dictionary_entries'));
        $this->assertDatabaseHas('dictionary_entries', ['learning_item_id' => 999999999, 'phonetic' => 'orphan']);
    }

    public function test_historical_scores_and_selected_options_survive_transfer_and_rollback(): void
    {
        $user = User::factory()->create();
        $exam = ExamTemplateFactory::new()->create();
        $question = QuestionBankItemFactory::new()->create();
        $option = DB::table('question_options')->insertGetId(['question_bank_item_id' => $question->id, 'option_key' => 'A', 'content' => 'Đáp án cũ']);
        $attempt = DB::table('exam_attempts')->insertGetId(['user_id' => $user->id, 'exam_template_id' => $exam->id, 'started_at' => now(), 'submitted_at' => now(), 'status' => 'completed']);
        $answer = DB::table('attempt_answers')->insertGetId(['exam_attempt_id' => $attempt, 'response' => json_encode(['text' => 'Giữ nội dung cũ']), 'grade' => json_encode(['custom_feedback' => 'Nhận xét cũ'])]);
        DB::table('attempt_answer_options')->insert(['attempt_answer_id' => $answer, 'question_option_id' => $option]);
        $details = ['duration_seconds' => 135, 'by_skill' => ['reading' => ['raw' => 1, 'total' => 1]], 'custom' => ['tiếng Việt', null]];
        DB::table('attempt_results')->insert(['exam_attempt_id' => $attempt, 'graded_by' => $user->id, 'raw_score' => 1.25, 'scaled_score' => 670.5, 'grading_status' => 'completed', 'details' => json_encode($details), 'calculated_at' => now()]);
        app(CompactSchemaMigration::class)->up();
        $stored = DB::table('exam_attempts')->find($attempt);
        $this->assertEquals(1.25, $stored->raw_score);
        $this->assertEquals(670.5, $stored->scaled_score);
        $this->assertSame($details, json_decode($stored->details, true));
        $response = json_decode(DB::table('attempt_answers')->where('id', $answer)->value('response'), true);
        $this->assertSame('Giữ nội dung cũ', $response['text']);
        $this->assertSame(['A'], $response['options']);
        $this->assertSame([$option], $response['option_ids']);
        app(CompactSchemaMigration::class)->down();
        $this->assertDatabaseHas('attempt_results', ['exam_attempt_id' => $attempt, 'raw_score' => 1.25, 'scaled_score' => 670.5]);
        $this->assertDatabaseHas('attempt_answer_options', ['attempt_answer_id' => $answer, 'question_option_id' => $option]);
    }

    public function test_questions_can_be_authored_independently_and_reuse_an_optional_knowledge_link(): void
    {
        app(CompactSchemaMigration::class)->up();
        $this->actingAs($this->admin());
        $question = QuestionBankItem::create([
            'exam_part_id' => DB::table('exam_parts')->where('code', 'part-5')->value('id'),
            'question_type_id' => DB::table('question_types')->where('code', 'multiple_choice')->value('id'),
            'question_difficulty_id' => CompactDatabase::table('question_difficulties')->where('code', 'medium')->value('id'),
            'prompt' => 'Select the answer.',
        ]);
        $service = app(ContentRevisionService::class);
        $payload = $service->payload('question', $question);
        $payload['options'] = ['A' => 'One', 'B' => 'Two'];
        $payload['correct_options'] = ['B'];
        $service->save('question', $question, $payload, 0);
        $this->assertNull($question->fresh()->learning_item_id);
        $this->assertNotNull($question->fresh()->draft_revision_id);
        $this->assertSame('draft', $question->fresh()->contentStatus());
        $item = LearningItemFactory::new()->create();
        $question->update(['learning_item_id' => $item->id]);
        $duplicate = $question->replicate();
        $duplicate->draft_revision_id = null;
        $duplicate->save();
        $item->delete();
        $this->assertNull($question->fresh()->learning_item_id);
        $this->assertNull($duplicate->fresh()->learning_item_id);
    }
}
