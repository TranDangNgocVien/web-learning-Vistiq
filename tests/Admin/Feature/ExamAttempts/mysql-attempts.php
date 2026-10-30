<?php

// Opt-in MySQL 8 submit/grading/query checks against a disposable local DB.
use Database\Admin\Factories\ExamTemplateFactory;
use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use VistiqAdmin\Http\Controllers\Admin\Assessment\Attempts\GradingController;
use VistiqAdmin\Models\User;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\Role;
use VistiqCore\Services\Assessment\Attempts\AttemptQueryService;
use VistiqCore\Services\Assessment\Attempts\AttemptService;
use VistiqCore\Services\Assessment\Attempts\GradingService;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

require dirname(__DIR__, 4).'/vendor/autoload.php';
$database = getenv('PHAT_MYSQL_DATABASE') ?: '';
if (! preg_match('/^vistiq_phat_test_[a-zA-Z0-9_]+$/D', $database)) {
    throw new RuntimeException('Set a disposable PHAT_MYSQL_DATABASE with the vistiq_phat_test_ prefix.');
}
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
});
config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => getenv('PHAT_MYSQL_PORT') ?: 13306, 'database.connections.mysql.database' => $database, 'database.connections.mysql.username' => getenv('PHAT_MYSQL_USER') ?: 'root', 'database.connections.mysql.password' => getenv('PHAT_MYSQL_PASSWORD') ?: '', 'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync']);
DB::purge('mysql');
Http::preventStrayRequests();
$fixturePath = $argv[2] ?? '';
if (! $fixturePath || ! is_dir(dirname($fixturePath))) {
    throw new RuntimeException('Supply a fixture path inside an existing scratch directory.');
}
$mode = $argv[1] ?? '';
if ($mode === 'prepare') {
    Artisan::call('migrate', ['--force' => true]);
    (new AdminCatalogSeeder)->run();
    $learner = User::factory()->create(['name' => 'Learner kiểm thử', 'email' => 'phat-learner@example.test', 'password' => bcrypt('PhatLocalTest42!')]);
    $reviewer = User::factory()->create(['name' => 'Reviewer kiểm thử', 'email' => 'phat-reviewer@example.test', 'password' => bcrypt('PhatLocalTest42!')]);
    $reviewer->roles()->attach(Role::where('code', 'super-admin')->value('id'));
    $pcm = str_repeat("\0", 16000);
    $wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
    Storage::disk('public')->put('test-audio/phat-silence.wav', $wav);
    $asset = MediaAsset::create(['title' => 'Audio kiểm thử một giây', 'disk' => 'public', 'path' => 'test-audio/phat-silence.wav', 'mime_type' => 'audio/wav', 'size' => strlen($wav), 'created_by' => $reviewer->id]);
    $item = ['key' => 'q1', 'id' => 1, 'type' => 'multiple_choice', 'exam_type' => 'toeic', 'part' => 'part-5', 'skill' => 'reading', 'section' => 0, 'prompt' => 'Chọn đáp án Two.', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_options' => ['B'], 'accepted_answers' => [], 'metadata' => [], 'explanation' => 'Two là đáp án đúng.', 'stimuli' => [['id' => 1, 'title' => 'Audio kiểm thử', 'stimulus_type' => 'audio', 'body' => '', 'metadata' => ['asset_id' => $asset->id], 'asset_path' => null]], 'weight' => 1];
    $essay = array_replace($item, ['key' => 'q2', 'type' => 'essay', 'skill' => 'writing', 'prompt' => 'Viết một đoạn trả lời để kiểm thử lưu bài.', 'stimuli' => [], 'metadata' => ['rubric' => array_fill_keys(QuestionRegistry::WRITING_CRITERIA, 'Theo rubric')]]);
    $exam = ExamTemplateFactory::new()->create(['title' => 'Đề kiểm thử Phát', 'status' => 'published']);
    $snapshot = ['schema_version' => 1, 'exam_id' => $exam->id, 'title' => $exam->title, 'exam_type' => 'toeic', 'variant' => null, 'mode' => 'mini', 'opens_at' => null, 'closes_at' => null, 'items' => [$item, $essay], 'sections' => [['index' => 0, 'title' => 'Phần kiểm thử', 'part' => 'part-5', 'exam_part_id' => DB::table('exam_parts')->where('code', 'part-5')->value('id'), 'skill' => 'reading', 'item_keys' => ['q1', 'q2'], 'duration_minutes' => 0, 'timing_group' => 'test']], 'policies' => array_replace(ExamPublishingService::POLICIES, ['pause_allowed' => true]), 'scoring' => null];
    $revision = ExamRevision::create(['exam_template_id' => $exam->id, 'number' => 1, 'payload' => $snapshot]);
    $exam->update(['published_revision_id' => $revision->id]);
    auth()->login($learner);
    $attempt = app(AttemptService::class)->start($exam, $learner);
    $answer = ['item_key' => 'q1', 'version' => 0, 'response' => ['options' => ['B']]];
    $first = app(AttemptService::class)->saveAnswers($attempt, [$answer]);
    $retry = app(AttemptService::class)->saveAnswers($attempt, [$answer]);
    if ($first !== $retry) {
        throw new RuntimeException('MySQL answer retry must return the existing acknowledgement.');
    }
    // Compare the persisted document, since MySQL canonicalizes JSON key order.
    file_put_contents($fixturePath, json_encode(['attempt' => $attempt->id, 'learner' => $learner->id, 'reviewer' => $reviewer->id, 'exam' => $exam->id, 'snapshot' => $attempt->fresh()->snapshot], JSON_THROW_ON_ERROR));
    echo "PASS: MySQL autosave retry retains one version. Submit fixture prepared.\n";
} elseif ($mode === 'worker') {
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    $index = (int) ($argv[3] ?? 0);
    auth()->loginUsingId($fixture['learner']);
    file_put_contents($fixturePath.'.ready'.$index, 'ready');
    $deadline = microtime(true) + 20;
    while (! is_file($fixturePath.'.go') && microtime(true) < $deadline) {
        usleep(100000);
    }
    if (! is_file($fixturePath.'.go')) {
        throw new RuntimeException('Concurrency barrier timed out.');
    }
    app(AttemptService::class)->submit(ExamAttempt::findOrFail($fixture['attempt']));
    file_put_contents($fixturePath.'.result'.$index, '200');
} elseif ($mode === 'verify') {
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    $attempt = ExamAttempt::findOrFail($fixture['attempt']);
    if (file_get_contents($fixturePath.'.result0') !== '200' || file_get_contents($fixturePath.'.result1') !== '200' || DB::table('attempt_results')->where('exam_attempt_id', $attempt->id)->count() !== 1 || DB::table('user_activities')->where('action', 'exam.submit')->where('user_id', $fixture['learner'])->count() !== 1) {
        throw new RuntimeException('Concurrent submit must persist one result and one submission activity.');
    }
    auth()->loginUsingId($fixture['reviewer']);
    // Mixed submitted/unsubmitted snapshots must not make ordered JSON_TABLE
    // pagination return an empty page while its COUNT reports manual answers.
    foreach (range(1, 24) as $index) {
        app(AttemptService::class)->start($attempt->exam, User::factory()->create());
    }
    $answers = app(GradingController::class)->index()->getData()['answers'];
    if ($answers->total() !== $answers->count()) {
        throw new RuntimeException('MySQL manual grading count and first page disagree.');
    }
    if (! $answers->contains(fn ($answer) => $answer->exam_attempt_id === $attempt->id && $answer->item_key === 'q2')) {
        throw new RuntimeException('MySQL JSON_TABLE grading query omitted the essay.');
    }
    $answer = $attempt->answers()->where('item_key', 'q2')->firstOrFail();
    app(GradingService::class)->grade($answer, ['criteria' => array_fill_keys(QuestionRegistry::WRITING_CRITERIA, 6), 'feedback' => 'MySQL rubric check']);
    if ($attempt->fresh()->snapshot !== $fixture['snapshot']) {
        throw new RuntimeException('Grading changed the attempt snapshot.');
    }
    $history = app(AttemptQueryService::class)->listing(['min_score' => 0], $fixture['learner']);
    if ($history->total() !== 1) {
        throw new RuntimeException('MySQL owner history/score filter failed.');
    }
    auth()->loginUsingId($fixture['learner']);
    $browserAttempt = app(AttemptService::class)->start($attempt->exam, auth()->user());
    auth()->loginUsingId($fixture['reviewer']);
    $question = QuestionBankItemFactory::new()->create(['exam_part_id' => DB::table('exam_parts')->where('code', 'part-5')->value('id')]);
    $revision = app(ContentRevisionService::class)->save('question', $question, array_replace($fixture['snapshot']['items'][0], ['prompt' => 'Revision kiểm thử giao diện duyệt', 'exam_part_id' => $question->exam_part_id, 'stimuli' => [], 'stimulus_ids' => []]), 0);
    $revision->update(['status' => 'review']);
    $fixture['browser_attempt'] = $browserAttempt->id;
    $fixture['review_question'] = $question->id;
    file_put_contents($fixturePath, json_encode($fixture, JSON_THROW_ON_ERROR));
    echo "PASS: concurrent MySQL submit => one result/activity; JSON_TABLE grading, score filter, owner history and snapshot invariance.\n";
} else {
    throw new RuntimeException('Expected prepare, worker or verify.');
}
