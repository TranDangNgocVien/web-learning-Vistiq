<?php

namespace VistiqAdmin\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use VistiqCore\Services\Shared\AiService;

class ProcessAiTask implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 70;

    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(public int $taskId) {}

    /**
     * Execute the job.
     */
    public function handle(AiService $ai): void
    {
        $ai->process($this->taskId);
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('ai_tasks')->where('id', $this->taskId)->update(['status' => 'failed', 'error' => 'Không thể xử lý AI sau các lần thử. Kiểm tra endpoint, model, capability và quota của dịch vụ.', 'updated_at' => now()]);
    }
}
