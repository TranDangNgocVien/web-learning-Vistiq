<?php

namespace VistiqAdmin\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Services\Assessment\Attempts\AttemptService;

#[Signature('attempts:expire')]
#[Description('Tự nộp bài hết hạn và chuyển phần thi theo mốc server.')]
class ExpireAttempts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AttemptService $service): int
    {
        ExamAttempt::whereNull('submitted_at')->whereNotNull('snapshot')->orderBy('id')->chunkById(100, function ($attempts) use ($service): void {
            foreach ($attempts as $attempt) {
                $service->synchronize($attempt);
            }
        });

        return self::SUCCESS;
    }
}
