<?php

namespace VistiqCore\Services\Assessment\ContentApproval;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BulkReviewService
{
    public function start(iterable $entries, array $filters): string
    {
        Gate::authorize('content.review');
        $id = (string) Str::uuid();
        $entries = collect($entries)->map(fn ($entry) => [
            'kind' => $entry->kind, 'id' => (int) $entry->id,
            'revision' => (int) $entry->revision, 'submitted_at' => (string) $entry->submitted_at,
            'label' => match ($entry->kind) {
                'exam' => 'Đề thi',
                'stimulus' => 'Bài Reading',
                default => 'Câu hỏi',
            }.' #'.$entry->id.' · '.Str::limit(strip_tags((string) $entry->title), 100),
        ])->values()->all();
        $this->save($id, ['owner' => (int) auth()->id(), 'entries' => $entries, 'filters' => $filters,
            'total' => count($entries), 'processed' => 0, 'approved' => 0, 'skipped' => []]);

        return $id;
    }

    public function state(string $id): array
    {
        Gate::authorize('content.review');
        $state = Cache::get('content-review.bulk.'.$id);
        abort_unless(is_array($state), 410, 'Phiên duyệt đã hết hạn. Tải lại hàng đợi và chọn các mục còn chờ duyệt.');
        abort_unless($state['owner'] === (int) auth()->id(), 403);

        return $state;
    }

    public function advance(string $id): array
    {
        return Cache::lock('content-review.bulk-lock.'.$id, 120)->get(function () use ($id): array {
            $state = $this->state($id);
            $started = microtime(true);
            // Bound each HTTP request; media checks and commits must not accumulate
            // across the entire filtered queue under PHP's 30-second limit.
            for ($count = 0; $count < 5 && $state['processed'] < $state['total']; $count++) {
                $entry = $state['entries'][$state['processed']];
                try {
                    app(ContentReviewService::class)->approveCurrentRevision($entry['kind'], $entry['id'], $entry['revision'], $entry['submitted_at']);
                    $state['approved']++;
                } catch (ValidationException $exception) {
                    $state['skipped'][] = ['label' => $entry['label'], 'reason' => implode(' ', array_merge(...array_values($exception->errors())))];
                } catch (HttpExceptionInterface $exception) {
                    if (! in_array($exception->getStatusCode(), [403, 404, 409, 422], true)) {
                        throw $exception;
                    }
                    $state['skipped'][] = ['label' => $entry['label'], 'reason' => $exception->getMessage() ?: 'Revision đã thay đổi hoặc không còn chờ duyệt.'];
                } catch (ModelNotFoundException) {
                    $state['skipped'][] = ['label' => $entry['label'], 'reason' => 'Nội dung không còn tồn tại trong hàng đợi.'];
                } catch (\Throwable $exception) {
                    report($exception);
                    $state['skipped'][] = ['label' => $entry['label'], 'reason' => 'Có lỗi hệ thống khi duyệt mục này. Hãy kiểm tra nhật ký và thử lại.'];
                }
                $state['processed']++;
                $this->save($id, $state);
                if (microtime(true) - $started >= 5) {
                    break;
                }
            }

            return $state;
        }) ?: abort(409, 'Đợt duyệt đang được xử lý. Hãy chờ rồi tiếp tục.');
    }

    public function summary(array $state): string
    {
        $summary = "Đã duyệt {$state['approved']}/{$state['total']} revision đã chọn.";
        if ($state['skipped']) {
            $summary .= ' Bỏ qua '.count($state['skipped']).' mục; xem lý do bên dưới.';
        }

        return $summary;
    }

    private function save(string $id, array $state): void
    {
        Cache::put('content-review.bulk.'.$id, $state, now()->addHour());
    }
}
