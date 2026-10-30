<?php

// Opt-in MySQL 8 concurrency check. Never uses the application's configured DB.
// PHAT_MYSQL_DATABASE must name a pre-created disposable local test database.
use Database\Admin\Factories\QuestionBankItemFactory;
use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use VistiqAdmin\Models\User;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\Role;
use VistiqCore\Services\Assessment\ContentApproval\ContentReviewService;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;

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
    $author = User::factory()->create();
    $author->roles()->attach(Role::where('code', 'content-admin')->value('id'));
    $reviewers = [];
    foreach (range(1, 2) as $index) {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', 'reviewer')->value('id'));
        $reviewers[] = $user->id;
    }
    auth()->login($author);
    $question = QuestionBankItemFactory::new()->create(['exam_part_id' => DB::table('exam_parts')->where('code', 'part-5')->value('id')]);
    $payload = ['exam_part_id' => $question->exam_part_id, 'type' => 'multiple_choice', 'prompt' => 'MySQL review concurrency fixture', 'options' => ['A' => 'One', 'B' => 'Two'], 'correct_options' => ['B'], 'accepted_answers' => [], 'metadata' => [], 'stimulus_ids' => []];
    $revision = app(ContentRevisionService::class)->save('question', $question, $payload, 0);
    $revision->update(['status' => 'review']);
    $review = app(ContentReviewService::class)->preview('question', $question->id);
    file_put_contents($fixturePath, json_encode(['question' => $question->id, 'revision' => $revision->id, 'reviewers' => $reviewers, 'data' => ['revision_counter' => $review['number'], 'review_token' => $review['token']]], JSON_THROW_ON_ERROR));
    echo "MySQL review fixture prepared.\n";
} elseif ($mode === 'worker') {
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    $index = (int) ($argv[3] ?? 0);
    auth()->loginUsingId($fixture['reviewers'][$index]);
    file_put_contents($fixturePath.'.ready'.$index, 'ready');
    $deadline = microtime(true) + 20;
    while (! is_file($fixturePath.'.go') && microtime(true) < $deadline) {
        usleep(100000);
    }
    if (! is_file($fixturePath.'.go')) {
        throw new RuntimeException('Concurrency barrier timed out.');
    }
    try {
        app(ContentReviewService::class)->decide('question', $fixture['question'], 'approve', $fixture['data']);
        $status = 200;
    } catch (HttpExceptionInterface $exception) {
        $status = $exception->getStatusCode();
    }
    file_put_contents($fixturePath.'.result'.$index, (string) $status);
} elseif ($mode === 'verify') {
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    $statuses = [(int) file_get_contents($fixturePath.'.result0'), (int) file_get_contents($fixturePath.'.result1')];
    sort($statuses);
    if ($statuses !== [200, 409] || ContentRevision::findOrFail($fixture['revision'])->status !== 'approved' || DB::table('audit_logs')->where('entity_type', 'question')->where('entity_id', $fixture['question'])->where('action', 'content.approve')->count() !== 1) {
        throw new RuntimeException('Concurrent review must produce one approval, one conflict and one audit.');
    }
    echo "PASS: concurrent MySQL reviewers => 200/409, one approval audit.\n";
} else {
    throw new RuntimeException('Expected prepare, worker or verify.');
}
