<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\ContentApproval;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Assessment\ContentApproval\BulkReviewRequest;
use VistiqAdmin\Http\Requests\Admin\Assessment\ContentApproval\ReviewDecisionRequest;
use VistiqCore\Services\Assessment\ContentApproval\ContentReviewService;
use VistiqCore\Services\Assessment\ContentApproval\BulkReviewService;

class ReviewQueueController extends AdminPageController
{
    public function index(Request $request, ContentReviewService $service): mixed
    {
        $filters = $request->validate($this->filterRules($request));
        $query = $this->queueQuery($filters);
        $ordered = fn () => (clone $query)->orderBy('queue.submitted_at')->orderBy('queue.kind')->orderBy('queue.id');
        $selectionSnapshot = $this->queueSnapshot($ordered()->get());
        $entries = $ordered()
            ->paginate(20)
            ->withQueryString();
        $entries->through(function ($entry) use ($service) {
            $entry->blockers = $service->preview($entry->kind, $entry->id)['blockers'];

            return $entry;
        });
        $draftCount = DB::table('question_bank_items as q')->join('learning_items as item', 'item.id', '=', 'q.learning_item_id')
            ->leftJoin('content_revisions as r', 'r.id', '=', 'q.draft_revision_id')
            ->where('item.status', '!=', 'archived')
            ->where(fn ($q) => $q->where('r.status', 'draft')->orWhere(fn ($q) => $q->whereNull('r.id')->where('item.status', 'draft')))->count();

        $bulkBatch = null;
        if ($request->hasSession() && ($batch = $request->session()->get('review_bulk_batch'))) {
            try {
                $state = app(BulkReviewService::class)->state($batch);
                $bulkBatch = $this->bulkProgress($batch, $state);
            } catch (HttpExceptionInterface $exception) {
                if (! in_array($exception->getStatusCode(), [403, 410], true)) {
                    throw $exception;
                }
                $request->session()->forget('review_bulk_batch');
            }
        }

        return $this->page('Duyệt nội dung', 'pages.admin.assessment.content-approval.index', ['entries' => $entries, 'filters' => $filters, 'draftCount' => $draftCount, 'selectionSnapshot' => $selectionSnapshot, 'bulkBatch' => $bulkBatch,
            'examTypes' => DB::table('exam_types')->orderBy('name')->get(['id', 'name']),
            'parts' => DB::table('exam_parts')->join('exam_types', 'exam_types.id', '=', 'exam_parts.exam_type_id')->orderBy('exam_parts.sort_order')->get(['exam_parts.id', 'exam_parts.name', 'exam_types.name as exam_type_name']),
        ]);
    }

    public function show(Request $request, string $kind, int $id, ContentReviewService $service): mixed
    {
        $review = $service->preview($kind, $id);

        if ($request->boolean('modal')) {
            return view('pages.admin.assessment.content-approval.modal-content', $review);
        }

        return $this->page('Duyệt revision', 'pages.admin.assessment.content-approval.show', $review);
    }

    public function decide(ReviewDecisionRequest $request, string $kind, int $id, string $action, ContentReviewService $service): mixed
    {
        try {
            $result = $service->decide($kind, $id, $action, $request->validated());
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409 || $request->expectsJson()) {
                throw $exception;
            }

            return response($this->page('Revision đã thay đổi', 'pages.admin.assessment.content-approval.show', $service->preview($kind, $id) + ['conflict' => 'Revision đã thay đổi hoặc đã được xử lý. Quyết định vừa gửi chưa được áp dụng. Hãy đọc lại nội dung mới trước khi quyết định.']), 409);
        }

        $message = $kind === 'stimulus'
            ? ($action === 'approve' ? 'Đã duyệt bài Reading.' : 'Đã trả bài Reading để chỉnh sửa.')
            : ($action === 'approve' ? 'Đã duyệt revision. Chờ xuất bản tại module nguồn.' : 'Đã trả chỉnh sửa kèm lý do.');

        return $request->expectsJson() ? response()->json($result) : redirect()->route('admin.content-approval.show', [$kind, $id])->with('status', $message);
    }

    public function approveAll(BulkReviewRequest $request, BulkReviewService $service): mixed
    {
        $data = $request->validated();
        $filters = array_intersect_key($data, array_flip(['kind', 'q', 'author', 'from', 'to', 'revision', 'exam_type', 'part']));
        $matchingEntries = $this->queueQuery($filters)
            ->orderBy('queue.submitted_at')
            ->orderBy('queue.kind')
            ->orderBy('queue.id')
            ->get();
        if ($matchingEntries->count() !== (int) $data['expected_count']) {
            throw ValidationException::withMessages(['selection' => 'Hàng đợi đã thay đổi kể từ lúc chọn. Hãy tải lại danh sách rồi chọn lại.']);
        }
        if (! hash_equals($this->queueSnapshot($matchingEntries), $data['selection_snapshot'])) {
            throw ValidationException::withMessages(['selection' => 'Revision đã thay đổi kể từ lúc chọn. Hãy tải lại danh sách rồi chọn lại.']);
        }

        if ($data['selection_mode'] === 'all_filtered') {
            $excluded = collect($data['excluded'] ?? [])->flip();
            if ($excluded->isNotEmpty()) {
                $matchingEntries = $matchingEntries->reject(fn ($entry) => $excluded->has($entry->kind.':'.$entry->id))->values();
            }
            $entries = $matchingEntries;
        } else {
            $selected = collect($data['selected'])->map(fn ($key) => explode(':', $key, 2))->unique(fn ($pair) => $pair[0].':'.$pair[1])->values();
            $selectedKeys = $selected->mapWithKeys(fn ($pair) => [$pair[0].':'.$pair[1] => true]);
            $entries = $matchingEntries->filter(fn ($entry) => $selectedKeys->has($entry->kind.':'.$entry->id))->values();
            if ($entries->count() !== $selected->count()) {
                throw ValidationException::withMessages(['selection' => 'Một số revision đã rời khỏi hàng đợi. Hãy tải lại danh sách rồi chọn lại.']);
            }
        }

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages(['selection' => 'Không còn revision phù hợp để duyệt.']);
        }

        $batch = $service->start($entries, $filters);
        if ($request->hasSession()) {
            $request->session()->put('review_bulk_batch', $batch);
        }

        return $request->expectsJson()
            ? response()->json($this->bulkProgress($batch, $service->state($batch)))
            : redirect()->route('admin.content-approval.index', array_filter($filters, fn ($value) => $value !== null && $value !== ''));
    }

    public function continueApproval(Request $request, string $batch, BulkReviewService $service): mixed
    {
        $state = $service->advance($batch);
        $progress = $this->bulkProgress($batch, $state);
        if ($progress['done'] && $request->hasSession()) {
            $request->session()->forget('review_bulk_batch');
            $request->session()->flash('status', $service->summary($state));
            $request->session()->flash('review_bulk_skipped', $state['skipped']);
            $request->session()->flash('review_bulk_progress', $progress);
        }

        return response()->json($progress);
    }

    private function bulkProgress(string $batch, array $state): array
    {
        $prefix = request()->routeIs('api.*') ? 'api.admin.' : 'admin.';

        return ['total' => $state['total'], 'processed' => $state['processed'], 'approved' => $state['approved'],
            'skipped' => count($state['skipped']), 'done' => $state['processed'] === $state['total'],
            'next_url' => route($prefix.'content-approval.continue', $batch),
            'redirect_url' => route($prefix.'content-approval.index', array_filter($state['filters'], fn ($value) => $value !== null && $value !== ''))];
    }

    private function filterRules(Request $request): array
    {
        return [
            'kind' => 'nullable|in:question,stimulus,exam',
            'q' => 'nullable|string|max:255',
            'author' => 'nullable|integer|exists:users,id',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'revision' => 'nullable|integer|min:1',
            'exam_type' => 'nullable|integer|exists:exam_types,id',
            'part' => 'nullable|integer|exists:exam_parts,id',
        ];
    }

    private function queueQuery(array $filters): Builder
    {
        $questions = DB::table('content_revisions as r')->join('question_bank_items as q', 'q.draft_revision_id', '=', 'r.id')->join('learning_items as item', 'item.id', '=', 'q.learning_item_id')->where('item.status', '!=', 'archived')->where('r.kind', 'question')->whereColumn('r.entity_id', 'q.id')->where('r.status', 'review')->select('q.id')->selectRaw("'question' as kind")->addSelect('r.payload->prompt as title', 'r.number as revision', 'r.created_by as author_id', 'r.updated_at as submitted_at');
        $readings = DB::table('content_revisions as r')->join('learning_stimuli as s', 's.draft_revision_id', '=', 'r.id')
            ->where('s.stimulus_type', 'reading')->where('s.status', '!=', 'archived')->where('r.kind', 'stimulus')
            ->whereColumn('r.entity_id', 's.id')->where('r.status', 'review')->select('s.id')->selectRaw("'stimulus' as kind")
            ->addSelect('r.payload->title as title', 'r.number as revision', 'r.created_by as author_id', 'r.updated_at as submitted_at');
        $revisionAuthor = DB::table('audit_logs as authors')->select('actor_id')->where('entity_type', 'exam')->whereColumn('entity_id', 'e.id')->where('action', 'exam.save')->whereColumn('after->revision', 'e.revision_counter')->orderByDesc('id')->limit(1);
        $exams = DB::table('exam_templates as e')->where('e.draft_status', 'review')->where('e.status', '!=', 'archived')->selectRaw("e.id, 'exam' as kind, e.title, e.revision_counter as revision")
            ->selectRaw('COALESCE(('.$revisionAuthor->toSql().'), e.created_by) as author_id', $revisionAuthor->getBindings())->addSelect('e.updated_at as submitted_at');

        if (! empty($filters['exam_type'])) {
            $questions->whereIn('q.exam_part_id', DB::table('exam_parts')->select('id')->where('exam_type_id', $filters['exam_type']));
            $readings->whereIn('r.payload->metadata->exam_part_id', DB::table('exam_parts')->select('id')->where('exam_type_id', $filters['exam_type']));
            $exams->where('e.exam_type_id', $filters['exam_type']);
        }
        if (! empty($filters['part'])) {
            $questions->where('q.exam_part_id', $filters['part']);
            $readings->where('r.payload->metadata->exam_part_id', (int) $filters['part']);
            $mysql = DB::connection()->getDriverName() === 'mysql';
            $source = $mysql ? "JSON_TABLE(e.draft_payload, '$.sections[*]' COLUMNS (part_id BIGINT PATH '$.exam_part_id')) as review_part" : "json_each(e.draft_payload, '$.sections') as review_part";
            $column = $mysql ? 'review_part.part_id' : "json_extract(review_part.value, '$.exam_part_id')";
            $exams->whereExists(fn ($q) => $q->selectRaw('1')->fromRaw($source)->whereRaw($column.' = ?', [(int) $filters['part']]));
        }

        $query = DB::query()->fromSub($questions->unionAll($readings)->unionAll($exams), 'queue')->leftJoin('users as u', 'u.id', '=', 'queue.author_id')->select('queue.*', 'u.name as author_name');
        if (! empty($filters['kind'])) {
            $query->where('queue.kind', $filters['kind']);
        }
        if (! empty($filters['q'])) {
            $query->where('queue.title', 'like', '%'.$filters['q'].'%');
        }
        if (! empty($filters['author'])) {
            $query->where('queue.author_id', (int) $filters['author']);
        }
        if (! empty($filters['revision'])) {
            $query->where('queue.revision', $filters['revision']);
        }
        if (! empty($filters['from'])) {
            $query->where('queue.submitted_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('queue.submitted_at', '<', Carbon::parse($filters['to'])->addDay()->toDateString());
        }

        return $query;
    }

    private function queueSnapshot(iterable $entries): string
    {
        $snapshot = collect($entries)->map(fn ($entry) => [$entry->kind, (int) $entry->id, (int) $entry->revision, (string) $entry->submitted_at])->values()->all();

        return hash_hmac('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR), (string) config('app.key'));
    }
}
