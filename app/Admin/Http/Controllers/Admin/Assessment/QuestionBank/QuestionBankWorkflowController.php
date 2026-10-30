<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\QuestionBank;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank\QuestionApprovalRequest;
use VistiqAdmin\Http\Requests\Admin\Shared\AdminActionRequest;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionBankCatalog;
use VistiqCore\Services\Assessment\QuestionBank\QuestionDeletionService;
use VistiqCore\Services\System\AuditService;

class QuestionBankWorkflowController extends AdminPageController
{
    public function approveQuestions(QuestionApprovalRequest $request, ContentRevisionService $revisions): mixed
    {
        Gate::authorize('content.review');
        $data = $request->validated();

        DB::transaction(function () use ($data, $revisions): void {
            foreach ($data['ids'] as $id) {
                $question = QuestionBankItem::query()->lockForUpdate()->findOrFail($id);
                $expectedRevisionId = (int) ($data['revision_ids'][$id] ?? 0);
                abort_unless(
                    $expectedRevisionId > 0 && (int) $question->draft_revision_id === $expectedRevisionId,
                    409,
                    "Câu hỏi Q-{$id} đã thay đổi revision. Tải lại danh sách rồi thử lại."
                );

                $revisions->transition('question', $question, 'approve');
            }
        });

        return back()->with('status', 'Đã duyệt '.count($data['ids']).' câu hỏi.');
    }

    public function bulk(AdminActionRequest $request, ContentRevisionService $revisions, QuestionDeletionService $deletions): mixed
    {
        $data = $request->validated();
        if (($data['action'] ?? null) === 'review_all') {
            return $this->reviewAllDrafts($data['draft_signature'], $revisions, app(QuestionBankCatalog::class));
        }
        $ids = array_values(array_unique(array_merge($data['ids'] ?? [], $data['matching_ids'] ?? [])));
        abort_unless($ids !== [] && ! empty($data['action']), 422);

        if ($data['action'] === 'delete_unused') {
            Gate::authorize('content.edit');
            $deleted = 0;
            $disabled = 0;
            $skipped = 0;

            $operationKey = $data['operation_key'] ?? null;
            $deleteOffset = (int) ($data['delete_offset'] ?? 0);
            $selectionTotal = (int) ($data['selection_total'] ?? count($ids));
            $isFinalBatch = (bool) ($data['final_batch'] ?? false);

            if ($operationKey !== null) {
                $cacheKey = 'question-delete:'.$request->user()->getAuthIdentifier().':'.$operationKey;
                $state = Cache::get($cacheKey);

                if (($state['status'] ?? null) === 'completed') {
                    return response()->json($state);
                }

                if ($deleteOffset === 0 && $state === null) {
                    $state = ['status' => 'processing', 'processed' => 0, 'total' => $selectionTotal, 'deleted' => 0, 'disabled' => 0, 'skipped' => 0];
                    Cache::add($cacheKey, $state, now()->addMinutes(20));
                    $state = Cache::get($cacheKey, $state);
                }

                abort_unless(
                    is_array($state)
                        && ($state['status'] ?? null) === 'processing'
                        && ($state['processed'] ?? -1) === $deleteOffset
                        && ($state['total'] ?? -1) === $selectionTotal,
                    409,
                    'Lô xóa không đúng tiến trình hiện tại.'
                );
                abort_unless($deleteOffset + count($ids) <= $selectionTotal, 422, 'Số lượng câu hỏi vượt quá tiến trình đã chọn.');
                abort_unless(! $isFinalBatch || $deleteOffset + count($ids) === $selectionTotal, 422, 'Lô cuối chưa khớp tổng số câu hỏi.');
            }

            foreach ($ids as $id) {
                $result = $deletions->delete((int) $id, skipUnauthorizedPublished: true);
                if ($result === 'deleted') {
                    $deleted++;
                } elseif ($result === 'disabled') {
                    $disabled++;
                } else {
                    $skipped++;
                }
            }

            if ($operationKey !== null) {
                $state['processed'] += count($ids);
                $state['deleted'] += $deleted;
                $state['disabled'] += $disabled;
                $state['skipped'] += $skipped;
                $state['status'] = $isFinalBatch ? 'completed' : 'processing';
                $state['completed'] = $isFinalBatch;

                Cache::put($cacheKey, $state, now()->addMinutes(20));

                if ($isFinalBatch) {
                    $request->session()->flash('status', "Đã xóa mềm {$state['deleted']} câu hỏi; ngừng sử dụng {$state['disabled']} câu trong đề đã phát hành; bỏ qua {$state['skipped']} câu chưa đủ quyền thao tác.");
                }

                return response()->json($state);
            }

            return back()->with('status', "Đã xóa mềm {$deleted} câu hỏi; ngừng sử dụng {$disabled} câu trong đề đã phát hành; bỏ qua {$skipped} câu chưa đủ quyền thao tác.");
        }

        DB::transaction(function () use ($data, $ids, $revisions): void {
            foreach ($ids as $id) {
                $root = $revisions->root('question', $id);
                if ($data['action'] === 'review' && ! $root->draft_revision_id) {
                    abort_unless($root->contentStatus() === 'draft', 409, 'Chỉ gửi duyệt câu hỏi nháp.');
                    $revisions->save('question', $root, $revisions->payload('question', $root), $root->revision_counter);
                    $root->refresh();
                }
                $revisions->transition('question', $root, $data['action'], $data['note'] ?? null);
            }
        });

        return back()->with('status', $data['action'] === 'review' ? 'Đã gửi '.count($ids).' câu hỏi vào Duyệt nội dung.' : 'Đã xử lý các câu hỏi đã chọn.');
    }

    private function reviewAllDrafts(string $confirmedSignature, ContentRevisionService $revisions, QuestionBankCatalog $catalog): mixed
    {
        Gate::authorize('content.edit');
        $draftIds = collect($catalog->catalog()['records'])
            ->filter(fn (array $record): bool => ($record['status'] ?? null) === 'draft')
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values();
        $currentSignature = hash('sha256', $draftIds->implode(','));
        if (! hash_equals($confirmedSignature, $currentSignature)) {
            throw ValidationException::withMessages(['draft_signature' => 'Danh sách câu hỏi nháp đã thay đổi. Tải lại trang rồi xác nhận lại để gửi đúng số câu.']);
        }
        if ($draftIds->isEmpty()) {
            return back()->with('status', 'Không còn câu hỏi nháp cần gửi duyệt.');
        }

        DB::transaction(function () use ($draftIds, $revisions): void {
            $invalid = [];
            foreach ($draftIds as $id) {
                $question = QuestionBankItem::with(['learningItem', 'draftRevision'])->findOrFail($id);
                $revision = $question->draftRevision;
                if (! $revision && $question->contentStatus() === 'draft') {
                    $revisions->save('question', $question, $revisions->payload('question', $question), $question->revision_counter);
                    $question->refresh();
                    $revision = $question->draftRevision;
                }
                abort_unless($revision?->status === 'draft', 409, "Câu hỏi Q-{$id} vừa thay đổi trạng thái. Tải lại trang rồi thử lại.");
                try {
                    $revisions->validate('question', $revision->payload);
                } catch (ValidationException $exception) {
                    $details = collect($exception->errors())->flatten()->take(2)->implode(' ');
                    $invalid[] = "Q-{$id}: {$details}";
                }
            }

            if ($invalid !== []) {
                $shown = array_slice($invalid, 0, 10);
                $remaining = count($invalid) - count($shown);
                if ($remaining > 0) {
                    $shown[] = "Còn {$remaining} câu chưa đạt kiểm tra.";
                }
                throw ValidationException::withMessages(['review_all' => $shown]);
            }

            foreach ($draftIds as $id) {
                $revisions->transition('question', $revisions->root('question', $id), 'review');
            }
        });

        return back()->with('status', 'Đã gửi duyệt toàn bộ '.number_format($draftIds->count()).' câu hỏi nháp trong các ngân hàng câu hỏi.');
    }

    public function export(Request $request, ContentRevisionService $revisions): mixed
    {
        return response()->streamDownload(function () use ($revisions): void {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['exam_part', 'question_type', 'difficulty', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'accepted_answers', 'explanation', 'tags', 'stimulus', 'status', 'payload_json'], escape: '');
            foreach (QuestionBankItem::with(['questionType', 'difficulty'])->cursor() as $question) {
                $p = $revisions->payload('question', $question);
                $safe = fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v;
                $row = [$p['exam_part_id'], $p['type'], $question->difficulty->code, strip_tags($p['prompt'])];
                foreach (['A', 'B', 'C', 'D'] as $key) {
                    $row[] = $p['options'][$key] ?? '';
                }
                $row[] = implode('|', $p['correct_options'] ?? []);
                $row[] = implode('|', $p['accepted_answers'] ?? []);
                $row[] = strip_tags($p['explanation'] ?? '');
                $row[] = '';
                $row[] = $p['stimulus_ids'][0] ?? '';
                $row[] = 'draft';
                $row[] = json_encode($p, JSON_UNESCAPED_UNICODE);
                fputcsv($file, array_map($safe, $row), escape: '');
            } fclose($file);
        }, 'questions.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function groups(): mixed
    {
        return $this->page('Nhóm câu hỏi', 'pages.admin.assessment.question-bank.workspace.groups', [
            'parts' => ExamPart::with('examType')->orderBy('exam_type_id')->orderBy('sort_order')->get(),
            'stimuli' => LearningStimulus::orderBy('title')->get(),
            'groups' => DB::table('question_groups')->orderByDesc('id')->paginate(20),
            'questionCounts' => DB::table('question_bank_items')->whereNull('deleted_at')->selectRaw('question_group_id, COUNT(*) AS total')->groupBy('question_group_id')->pluck('total', 'question_group_id'),
        ]);
    }

    public function updateGroup(AdminActionRequest $request, int $group, AuditService $audit): mixed
    {
        Gate::authorize('content.edit');
        $data = $request->validated();
        DB::transaction(function () use ($group, $data, $audit): void {
            $row = DB::table('question_groups')->where('id', $group)->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_if(QuestionBankItem::where('question_group_id', $group)->whereNotNull('published_revision_id')->exists(), 409, 'Nhóm đã dùng trong nội dung xuất bản. Tạo nhóm mới cho phiên bản mới.');
            abort_unless(! empty($data['title']) && ! empty($data['kind']) && ! empty($data['exam_part_id']), 422);
            DB::table('question_groups')->where('id', $group)->update(['title' => $data['title'], 'kind' => $data['kind'], 'exam_part_id' => $data['exam_part_id'], 'stimulus_ids' => json_encode($data['stimulus_ids'] ?? []), 'updated_at' => now()]);
            $audit->record('group.update', 'group', $group, (array) $row, $data);
        });

        return back()->with('status', 'Đã cập nhật nhóm.');
    }

    public function destroyGroup(AdminActionRequest $request, int $group, AuditService $audit): mixed
    {
        Gate::authorize('content.edit');
        DB::transaction(function () use ($group, $audit): void {
            abort_unless(DB::table('question_groups')->where('id', $group)->lockForUpdate()->first(), 404);
            abort_if(QuestionBankItem::where('question_group_id', $group)->exists() || ContentRevision::where('kind', 'question')->where('payload->group_id', $group)->exists(), 409, 'Nhóm đang được sử dụng.');
            DB::table('question_groups')->where('id', $group)->delete();
            $audit->record('group.delete', 'group', $group);
        });

        return back()->with('status', 'Đã xóa nhóm chưa sử dụng.');
    }

    public function storeGroup(AdminActionRequest $request, AuditService $audit): mixed
    {
        Gate::authorize('content.edit');
        $data = $request->validated();
        abort_unless(! empty($data['title']) && ! empty($data['kind']) && ! empty($data['exam_part_id']), 422);
        $id = DB::table('question_groups')->insertGetId(['title' => $data['title'], 'kind' => $data['kind'], 'exam_part_id' => $data['exam_part_id'], 'stimulus_ids' => json_encode($data['stimulus_ids'] ?? []), 'metadata' => json_encode($data['metadata'] ?? []), 'created_at' => now(), 'updated_at' => now()]);
        $audit->record('group.create', 'group', $id);

        return back()->with('status', 'Đã tạo nhóm.');
    }
}
