<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('attempts:expire')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('vistiq:compact-database', function () {
    if (config('cache.default') === 'database') {
        $this->error('Set CACHE_STORE=file and run php artisan config:clear first.');

        return 1;
    }
    $alreadyDown = app()->isDownForMaintenance();
    if (! $alreadyDown) {
        $this->call('down');
    }
    try {
        $code = $this->call('migrate', ['--force' => true]);
        if ($code !== 0) {
            throw new RuntimeException('Migration failed. Maintenance remains enabled; recover from the private backup before reopening traffic.');
        }
    } catch (Throwable $error) {
        $this->error($error->getMessage());

        return 1;
    }
    if (! $alreadyDown) {
        $this->call('up');
    }
    $this->info('Database consolidation completed and verified. Backup: storage/app/private/database-consolidation/');

    return 0;
})->purpose('Back up, verify and consolidate Vistiq to 43 physical tables');
