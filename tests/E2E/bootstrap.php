<?php

// Real HTTP/CSRF E2E fixtures. Refuse every database/storage path outside the disposable run.
use Database\Admin\Factories\ExamTemplateFactory;
use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Storage;
use VistiqAdmin\Models\User;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Models\Role;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$root = realpath(dirname(__DIR__, 2).'/storage/browser-e2e');
$storage = realpath(storage_path());
if (app()->environment() !== 'e2e' || config('database.default') !== 'sqlite' || ! $root || ! $storage || ! str_starts_with(strtolower(str_replace('\\', '/', $storage)), strtolower(str_replace('\\', '/', $root)).'/') || realpath(config('database.connections.sqlite.database')) !== $storage.DIRECTORY_SEPARATOR.'database.sqlite') {
    throw new RuntimeException('E2E requires an isolated storage/browser-e2e run directory and SQLite file.');
}
DB::statement('PRAGMA journal_mode = WAL');
DB::statement('PRAGMA synchronous = NORMAL');
DB::beginTransaction();
Artisan::call('migrate', ['--force' => true]);
(new AdminCatalogSeeder)->run();
$actors = [];
foreach (['admin' => 'super-admin', 'author' => 'content-admin', 'reviewer' => 'reviewer', 'learner' => null, 'other' => null] as $name => $role) {
    $user = User::factory()->create(['name' => 'E2E '.ucfirst($name), 'email' => $name.'@e2e.test', 'password' => 'VistiqE2e123!']);
    if ($role) {
        $user->roles()->attach(Role::where('code', $role)->value('id'));
    }
    $actors[$name] = $user->id;
}
auth()->loginUsingId($actors['author']);
$part = DB::table('exam_parts')->where('code', 'part-5')->first();
$question = QuestionBankItemFactory::new()->create(['exam_part_id' => $part->id]);
app(ContentRevisionService::class)->save('question', $question, ['exam_part_id' => $part->id, 'type' => 'multiple_choice', 'prompt' => 'E2E review: Choose Two.', 'options' => ['A' => 'One', 'B' => 'Two', 'C' => 'Three', 'D' => 'Four'], 'correct_options' => ['B'], 'accepted_answers' => [], 'metadata' => [], 'stimulus_ids' => []], 0);
$exams = [];
foreach (['reading', 'writing'] as $kind) {
    $exam = ExamTemplateFactory::new()->create(['title' => 'E2E '.ucfirst($kind).' exam', 'status' => 'published', 'published_at' => now()]);
    $item = ['key' => 'q1', 'id' => $question->id, 'type' => 'multiple_choice', 'exam_type' => 'toeic', 'part' => 'part-5', 'skill' => 'reading', 'section' => 0, 'prompt' => 'Choose Two.', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_options' => ['B'], 'accepted_answers' => [], 'metadata' => [], 'explanation' => 'Two is correct.', 'stimuli' => [], 'weight' => 1];
    if ($kind === 'writing') {
        $item = array_replace($item, ['type' => 'essay', 'skill' => 'writing', 'prompt' => 'Write about learning English.', 'metadata' => ['rubric' => array_fill_keys(QuestionRegistry::WRITING_CRITERIA, 'Use the rubric.')]]);
    }
    $snapshot = ['schema_version' => 1, 'exam_id' => $exam->id, 'title' => $exam->title, 'exam_type' => 'toeic', 'variant' => null, 'mode' => 'mini', 'opens_at' => null, 'closes_at' => null, 'items' => [$item], 'sections' => [['index' => 0, 'title' => ucfirst($kind), 'part' => 'part-5', 'exam_part_id' => $part->id, 'skill' => $item['skill'], 'item_keys' => ['q1'], 'duration_minutes' => 0, 'timing_group' => 'test']], 'policies' => array_replace(ExamPublishingService::POLICIES, ['track_focus' => false, 'anonymous' => true, 'pause_allowed' => true]), 'scoring' => null];
    $revision = ExamRevision::create(['exam_template_id' => $exam->id, 'number' => 1, 'payload' => $snapshot]);
    $exam->update(['published_revision_id' => $revision->id]);
    $exams[$kind] = $exam->id;
}
// Representative library records live only in this disposable browser-test database.
foreach ([
    ['vocabulary', 'commitment', 'sự cam kết, sự tận tâm', 'She showed great commitment to her work.'],
    ['grammar', 'be likely to', 'có khả năng, có thể', 'She is likely to succeed in the interview.'],
    ['collocation', 'take into account', 'xem xét, tính đến', 'We need to take into account all possible risks.'],
    ['phrasal_verb', 'look forward to', 'mong chờ, trông đợi', 'I am looking forward to meeting you.'],
    ['vocabulary', 'opportunity', 'cơ hội', 'This is a great opportunity for your professional growth.'],
    ['grammar', 'not only ... but also ...', 'không chỉ ... mà còn ...', 'She is not only intelligent but also very kind.'],
] as $index => [$type,$text,$meaning,$example]) {
    $itemId = DB::table('learning_items')->insertGetId(['learning_item_type_id' => DB::table('learning_item_types')->where('code', $type)->value('id'), 'canonical_text' => $text, 'slug' => 'e2e-library-'.$index, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
    $meaningId = DB::table('learning_item_meanings')->insertGetId(['learning_item_id' => $itemId, 'sense_number' => 1, 'translation' => $meaning, 'definition' => 'Example content for browser verification.']);
    DB::table('learning_item_meaning_examples')->insert(['learning_item_meaning_id' => $meaningId, 'english' => $example, 'sort_order' => 0]);
    if ($type === 'vocabulary') {
        DB::table('dictionary_entries')->insert(['learning_item_id' => $itemId, 'phonetic' => $index === 0 ? '/kəˈmɪtmənt/' : '/ˌɒpəˈtjuːnɪti/']);
    }
    DB::table('user_saved_items')->insert(['user_id' => $actors['learner'], 'learning_item_id' => $itemId, 'created_at' => now()->subDays($index + 1), 'updated_at' => now()]);
}
DB::commit();
$marker = basename(storage_path());
Storage::disk('public')->put('e2e-isolation.txt', $marker);
file_put_contents(storage_path('fixture.json'), json_encode(['actors' => $actors, 'question' => $question->id, 'exams' => $exams, 'storage_marker' => $marker], JSON_THROW_ON_ERROR));
echo "E2E fixture prepared in isolated SQLite/private storage.\n";
