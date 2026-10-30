<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\QuestionBank;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionBankCatalog;

class SubjectiveQuestionController extends AdminPageController
{
    public function index(Request $request, QuestionBankCatalog $catalog): mixed
    {
        $skill = $request->route('skill');
        $skillLabel = ucfirst($skill);
        $bankRoute = 'admin.questions.'.$skill;
        $bankData = $catalog->catalog();
        $records = collect($bankData['records'])->filter(fn (array $record): bool => $record['question']->examPart?->skill?->code === $skill);
        $parts = ExamPart::with(['examType', 'skill'])->whereHas('skill', fn ($query) => $query->where('code', $skill))->orderBy('sort_order')->get();
        $filters = [
            'search' => trim($request->string('q')->toString()),
            'exam' => $request->string('exam')->toString(),
            'part' => $request->string('part')->toString(),
            'status' => $request->string('status')->toString(),
            'difficulty' => $request->string('difficulty')->toString(),
        ];
        $matching = $catalog->matching($filters)->filter(fn ($question): bool => $records->has($question->id));
        $sort = in_array($request->input('sort'), ['newest', 'oldest', 'id'], true) ? $request->input('sort') : 'newest';
        $matching = match ($sort) {
            'id' => $matching->sortBy('id'),
            'oldest' => $matching->sortBy(fn ($question) => $question->draftRevision?->created_at ?? $question->updated_at),
            default => $matching->sortByDesc(fn ($question) => $question->draftRevision?->created_at ?? $question->updated_at),
        };
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 10;
        $page = max(1, $request->integer('page', 1));
        $questions = new LengthAwarePaginator($matching->values()->forPage($page, $perPage), $matching->count(), $perPage, $page, ['path' => route($bankRoute), 'query' => $request->query()]);
        $selected = $request->filled('question') ? $records->get($request->integer('question')) : null;
        abort_if($request->filled('question') && ! $selected, 404);
        $draftIds = $records->filter(fn ($record) => $record['status'] === 'draft')->keys()->map(fn ($id) => (int) $id)->sort()->values();
        $draftSignature = $this->signature($records->only($draftIds->all()));

        return $this->page('Câu hỏi '.$skillLabel, 'pages.admin.assessment.question-bank.workspace.subjective-bank', compact('skill', 'skillLabel', 'bankRoute', 'records', 'parts', 'filters', 'sort', 'questions', 'selected', 'draftIds', 'draftSignature'));
    }

    public function review(Request $request, QuestionBankCatalog $catalog, ContentRevisionService $revisions): mixed
    {
        $skill = $request->route('skill');
        $data = $request->validate([
            'all_drafts' => ['sometimes', 'boolean'],
            'draft_signature' => ['required_if:all_drafts,1', 'string', 'size:64'],
            'ids' => ['required_unless:all_drafts,1', 'array', 'min:1', 'max:2000'],
            'ids.*' => ['required', 'integer', 'distinct'],
            'revision_counters' => ['required_unless:all_drafts,1', 'array'],
            'revision_counters.*' => ['integer', 'min:0'],
        ]);
        $records = collect($catalog->catalog()['records'])->filter(fn ($record) => $record['question']->examPart?->skill?->code === $skill);
        $drafts = $records->filter(fn ($record) => $record['status'] === 'draft');
        $allDrafts = $request->boolean('all_drafts');
        if ($allDrafts && ! hash_equals($data['draft_signature'], $this->signature($drafts))) {
            throw ValidationException::withMessages(['draft_signature' => 'Danh sách bản nháp đã thay đổi. Tải lại trang rồi gửi duyệt lại.']);
        }
        $ids = $allDrafts ? $drafts->keys()->all() : $data['ids'];
        DB::transaction(function () use ($ids, $records, $revisions, $allDrafts, $data): void {
            foreach ($ids as $id) {
                abort_unless($records->has($id), 422, 'Chỉ gửi duyệt câu hỏi thuộc ngân hàng đang mở.');
                $question = QuestionBankItem::with(['learningItem', 'draftRevision'])->lockForUpdate()->findOrFail($id);
                $expected = $allDrafts ? $records[$id]['question']->revision_counter : ($data['revision_counters'][$id] ?? null);
                abort_unless($expected !== null && (int) $expected === (int) $question->revision_counter, 409, "Câu hỏi Q-{$id} đã thay đổi. Tải lại trang trước khi gửi duyệt.");
                $status = $question->draftRevision?->status ?? $question->contentStatus();
                abort_unless($status === 'draft', 409, "Câu hỏi Q-{$id} không còn ở trạng thái bản nháp.");
                if (! $question->draft_revision_id) {
                    $revisions->save('question', $question, $revisions->payload('question', $question), $question->revision_counter);
                    $question->refresh();
                }
                try {
                    $revisions->transition('question', $question, 'review');
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['review' => 'Q-'.$id.': '.collect($exception->errors())->flatten()->implode(' ')]);
                }
            }
        });

        return back()->with('status', 'Đã gửi duyệt '.count($ids).' câu hỏi '.ucfirst($skill).'.');
    }

    private function signature($records): string
    {
        return hash('sha256', $records->sortKeys()->map(fn ($record, $id) => $id.':'.$record['question']->revision_counter)->implode(','));
    }
}
