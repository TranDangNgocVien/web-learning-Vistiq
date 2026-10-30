<?php

// Opt-in disposable MySQL verification. This script cannot name an application database.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use VistiqAdmin\Models\User;
use VistiqAdmin\Services\Admin\System\DatabaseOperationsService;
use VistiqCore\Models\Role;
use VistiqCore\Services\Analytics\LeaderboardService;
use VistiqCore\Services\Analytics\OverviewService;
use VistiqCore\Services\Assessment\Attempts\AttemptQueryService;
use VistiqCore\Services\Knowledge\KnowledgeReaderService;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$database = getenv('PHAT_MYSQL_DATABASE');
if (! preg_match('/^vistiq_phat_test_[a-zA-Z0-9_]+$/D', $database)) {
    throw new RuntimeException('Disposable database prefix required.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port='.(getenv('PHAT_MYSQL_PORT') ?: 3306), getenv('PHAT_MYSQL_USER') ?: 'root', getenv('PHAT_MYSQL_PASSWORD') ?: '');
if (($argv[1] ?? '') === 'create') {
    $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    exit;
}
if (($argv[1] ?? '') === 'drop') {
    $pdo->exec('DROP DATABASE `'.$database.'`');
    exit;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.url' => null, 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => getenv('PHAT_MYSQL_PORT') ?: 3306, 'database.connections.mysql.database' => $database, 'database.connections.mysql.username' => getenv('PHAT_MYSQL_USER') ?: 'root', 'database.connections.mysql.password' => getenv('PHAT_MYSQL_PASSWORD') ?: '', 'cache.default' => 'array', 'session.driver' => 'array']);
DB::purge('mysql');
// Exercise the upgrade path when default roles already exist.
$migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_18_110000_add_results_export_permission.php';
$migration->down();
$migration->up();
$key = DB::table('permissions')->where('code', 'results.export')->value('id');
$learnerId = DB::table('users')->where('email', 'phat-learner@example.test')->value('id');
$summary = app(AttemptQueryService::class)->profileSummary($learnerId);
$rank = app(LeaderboardService::class)->bestRankForUser($learnerId);
if ($summary['submitted'] !== 1 || $summary['accuracy'] != 100 || ! $rank || $rank['rank'] !== 1) {
    throw new RuntimeException('MySQL profile aggregation/rank contract failed.');
}
foreach (['admin', 'super-admin'] as $role) {
    if (! DB::table('permission_role')->where('permission_id', $key)->where('role_id', Role::where('code', $role)->value('id'))->exists()) {
        throw new RuntimeException('Default export grant missing on upgrade');
    }
}
if (DB::table('permission_role')->where('permission_id', $key)->where('role_id', Role::where('code', 'reviewer')->value('id'))->exists()) {
    throw new RuntimeException('Export granted to reviewer implicitly');
}
$admin = User::factory()->create(['name' => "MySQL; O'Brien ✓"]);
$admin->roles()->attach(Role::where('code', 'super-admin')->value('id'));
auth()->login($admin);
$collection = DB::table('user_knowledge_collections')->insertGetId(['user_id' => $learnerId, 'name' => 'MySQL personal collection', 'created_at' => now(), 'updated_at' => now()]);
$word = DB::table('learning_items')->insertGetId(['learning_item_type_id' => DB::table('learning_item_types')->where('code', 'vocabulary')->value('id'), 'canonical_text' => 'MySQL commitment', 'slug' => 'mysql-commitment', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
DB::table('learning_item_meanings')->insert(['learning_item_id' => $word, 'sense_number' => 1, 'translation' => 'sự cam kết']);
DB::table('user_saved_items')->insert(['user_id' => $learnerId, 'learning_item_id' => $word, 'collection_id' => $collection, 'personal_note' => 'private MySQL note', 'created_at' => now(), 'updated_at' => now()]);
$library = app(KnowledgeReaderService::class)->library($learnerId, ['collection' => $collection]);
$overview = app(OverviewService::class)->read();
if ($library['items']->total() !== 1 || $library['items']->first()->meaning !== 'sự cam kết' || $library['collections']->first()->total != 1 || $overview['series']->count() !== 14) {
    throw new RuntimeException('MySQL personal library/overview query contract failed.');
}
$service = app(DatabaseOperationsService::class);
$artifact = $service->createBackup($admin->id);
$path = $service->verifiedPath($artifact['id']);
$admin->update(['name' => 'Changed']);
$upload = new UploadedFile($path, 'mysql.sql', 'text/plain', null, true);
$ready = $service->preflight($upload, $admin->id);
$result = $service->restore($ready['id'], $admin->id);
if ($result['state'] !== 'succeeded' || $admin->fresh()->name !== "MySQL; O'Brien ✓" || $admin->fresh()->session_version != 1) {
    throw new RuntimeException('MySQL restore roundtrip failed: '.json_encode($result));
}
$broken = "-- Vistiq Database Backup v2\n-- Driver: mysql\nSET FOREIGN_KEY_CHECKS=0; DELETE FROM `users`; INSERT INTO `users` (`missing_column`) VALUES ('bad'); SET FOREIGN_KEY_CHECKS=1;";
$tmp = tempnam(sys_get_temp_dir(), 'vistiq-mysql-');
file_put_contents($tmp, $broken);
try {
    $ready = $service->preflight(new UploadedFile($tmp, 'broken.sql', 'text/plain', null, true), $admin->id);
    $result = $service->restore($ready['id'], $admin->id);
    if ($result['state'] !== 'failed' || ! ($result['recovered'] ?? false) || $result['maintenance_retained'] || $admin->fresh()->name !== "MySQL; O'Brien ✓") {
        throw new RuntimeException('MySQL recovery failed');
    }
} finally {
    if (is_file($tmp)) {
        unlink($tmp);
    }
}
echo "PASS: MySQL migration upgrade/default grants, private backup integrity, restore/session revocation and partial-failure recovery.\n";
