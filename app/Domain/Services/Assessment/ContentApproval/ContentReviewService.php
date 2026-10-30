<?php

namespace VistiqCore\Services\Assessment\ContentApproval;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;
use VistiqCore\Services\Assessment\QuestionBank\MediaService;
use VistiqCore\Services\System\AuditService;

class ContentReviewService
{
    public function __construct(private ContentRevisionService $revisions, private ExamPublishingService $exams, private AuditService $audit) {}

    public function examAuthor(ExamTemplate $exam): ?int
    {
        $actor = DB::table('audit_logs')->where('entity_type', 'exam')->where('entity_id', $exam->id)->where('action', 'exam.save')->where('after->revision', (int) $exam->revision_counter)->orderByDesc('id')->value('actor_id');

        return $actor === null ? ($exam->created_by === null ? null : (int) $exam->created_by) : (int) $actor;
    }

    public function preview(string $kind, int $id, bool $lock = false): array
    {
        abort_unless(in_array($kind, ['question', 'stimulus', 'exam'], true), 404);
        $query = match ($kind) {
            'exam' => ExamTemplate::query(),
            'stimulus' => LearningStimulus::query(),
            default => QuestionBankItem::query(),
        };
        $root = ($lock ? $query->lockForUpdate() : $query)->findOrFail($id);
        abort_if($kind === 'stimulus' && $root->stimulus_type !== 'reading', 404);
        $errors = [];
        $dependencies = [];
        $previous = null;

        if ($kind === 'question') {
            $revisionQuery = ContentRevision::where('id', $root->draft_revision_id)->where('kind', 'question')->where('entity_id', $id);
            $revision = ($lock ? $revisionQuery->lockForUpdate() : $revisionQuery)->firstOrFail();
            $payload = $revision->payload;
            $status = $root->contentStatus() === 'archived' ? 'archived' : $revision->status;
            $author = $revision->created_by;
            $number = $revision->number;
            $submittedAt = $revision->getRawOriginal('updated_at');
            $previous = ContentRevision::where('kind', 'question')->where('entity_id', $id)->where('number', '<', $number)->orderByDesc('number')->first()?->payload;
            $stimulusIds = $payload['stimulus_ids'] ?? [];
            if (! empty($payload['group_id'])) {
                $groupQuery = DB::table('question_groups')->where('id', $payload['group_id']);
                $group = ($lock ? $groupQuery->lockForUpdate() : $groupQuery)->first();
                $dependencies['group'] = $group;
                $stimulusIds = array_merge($stimulusIds, json_decode($group?->stimulus_ids ?? '[]', true) ?: []);
            }
            $stimuli = LearningStimulus::whereIn('id', array_unique($stimulusIds))->orderBy('id');
            foreach (($lock ? $stimuli->lockForUpdate() : $stimuli)->get() as $stimulus) {
                $dependencyRevision = ContentRevision::where('id', $stimulus->draft_revision_id ?? $stimulus->published_revision_id);
                if ($lock) {
                    $dependencyRevision->lockForUpdate();
                }
                $dependencies['stimulus_revisions'][] = $dependencyRevision->first()?->id;
                $resolved = $this->revisions->payload('stimulus', $stimulus);
                $dependencies['stimuli'][] = $resolved;
            }
            if (count($dependencies['stimuli'] ?? []) !== count(array_unique($stimulusIds))) {
                $errors[] = 'Tài nguyên liên kết không còn tồn tại.';
            }
            try {
                $this->revisions->validate('question', $payload);
                foreach ($dependencies['stimuli'] ?? [] as $stimulus) {
                    $this->revisions->validate('stimulus', $stimulus);
                }
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, array_merge(...array_values($exception->errors())));
            }
            $assetIds = collect([$payload['metadata']['image_id'] ?? null, $payload['metadata']['audio_id'] ?? null])
                ->merge(collect($dependencies['stimuli'] ?? [])->map(fn ($stimulus) => $stimulus['metadata']['asset_id'] ?? null))->filter()->unique()->sort();
            foreach ($assetIds as $assetId) {
                $assetQuery = MediaAsset::where('id', $assetId);
                $asset = ($lock ? $assetQuery->lockForUpdate() : $assetQuery)->first();
                $availability = $asset ? app(MediaService::class)->checkAvailability((int) $assetId) : false;
                $dependencies['media'][] = ['id' => $assetId, 'asset' => $asset?->toArray(), 'available' => $availability === true, 'availability' => $availability];
                if ($availability === null) {
                    $errors[] = 'Không thể kiểm tra media #'.$assetId.' do kết nối kho lưu trữ. Kiểm tra cấu hình chứng chỉ CA của PHP rồi thử lại.';
                } elseif (! $availability) {
                    $errors[] = 'Media #'.$assetId.' không còn tồn tại hoặc không tải được.';
                }
            }
        } elseif ($kind === 'stimulus') {
            $revisionQuery = ContentRevision::query()->where('id', $root->draft_revision_id)->where('kind', 'stimulus')->where('entity_id', $id);
            $revision = ($lock ? $revisionQuery->lockForUpdate() : $revisionQuery)->firstOrFail();
            $payload = $revision->payload;
            $status = $root->status === 'archived' ? 'archived' : $revision->status;
            $author = $revision->created_by;
            $number = $revision->number;
            $submittedAt = $revision->getRawOriginal('updated_at');
            $previous = ContentRevision::query()->where('kind', 'stimulus')->where('entity_id', $id)->where('number', '<', $number)->orderByDesc('number')->first()?->payload;
            try {
                $this->revisions->validate('stimulus', $payload);
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, array_merge(...array_values($exception->errors())));
            }
        } else {
            $status = $root->status === 'archived' ? 'archived' : $root->draft_status;
            $author = $this->examAuthor($root);
            $number = (int) $root->revision_counter;
            $submittedAt = $root->getRawOriginal('updated_at');
            $previous = $root->publishedRevision?->payload;
            // Resolve source revisions without publishing them during a read.
            $ready = true;
            $ids = collect($root->draft_payload['sections'] ?? [])->flatMap(fn ($section) => $section['question_ids'] ?? [])->unique()->sort()->values();
            $questionQuery = QuestionBankItem::whereIn('id', $ids)->orderBy('id');
            $questions = ($lock ? $questionQuery->lockForUpdate() : $questionQuery)->get();
            if ($questions->count() !== $ids->count()) {
                $ready = false;
            }
            foreach ($questions as $question) {
                $sourceRevision = $this->revisions->approvedOrPublishedRevision('question', $question);
                if (! $sourceRevision) {
                    $ready = false;

                    continue;
                }
                $stimulusIds = $sourceRevision->payload['stimulus_ids'] ?? [];
                if (! empty($sourceRevision->payload['group_id'])) {
                    $groupQuery = DB::table('question_groups')->where('id', $sourceRevision->payload['group_id']);
                    $group = ($lock ? $groupQuery->lockForUpdate() : $groupQuery)->first();
                    $stimulusIds = array_merge($stimulusIds, json_decode($group?->stimulus_ids ?? '[]', true) ?: []);
                }
                foreach (array_unique($stimulusIds) as $stimulusId) {
                    $stimulusQuery = LearningStimulus::where('id', $stimulusId);
                    $stimulus = ($lock ? $stimulusQuery->lockForUpdate() : $stimulusQuery)->first();
                    if (! $stimulus || $stimulus->status === 'archived') {
                        $ready = false;
                    }
                }
            }
            if (! $ready) {
                $errors[] = 'Câu hỏi cần được duyệt hoặc đã xuất bản trước khi duyệt đề. Mở ngân hàng câu hỏi để hoàn thiện.';
            }
            try {
                $payload = $this->exams->assemble($root, ! $ready);
                $errors = array_merge($errors, $this->exams->problems($payload));
            } catch (ValidationException $exception) {
                $payload = $root->draft_payload ?? [];
                $errors = array_merge($errors, array_merge(...array_values($exception->errors())));
            } catch (ModelNotFoundException $exception) {
                $payload = $root->draft_payload ?? [];
                $errors[] = 'Part/câu hỏi/tài nguyên tham chiếu không còn tồn tại.';
            }
        }

        $errors = array_values(array_unique($errors));
        $token = hash_hmac('sha256', json_encode([$kind, $id, $number, $author, $payload, $dependencies, $errors], JSON_THROW_ON_ERROR), (string) config('app.key'));
        $history = DB::table('audit_logs')->where('entity_type', $kind)->where('entity_id', $id)->whereIn('action', $kind === 'exam' ? ['exam.review', 'exam.approve', 'exam.reject'] : ['content.review', 'content.approve', 'content.reject'])->orderByDesc('id')->limit(50)->get();

        return compact('kind', 'root', 'payload', 'dependencies', 'previous', 'status', 'author', 'number', 'submittedAt', 'token', 'history') + ['blockers' => $errors];
    }

    public function decide(string $kind, int $id, string $action, array $data): array
    {
        return $this->decideRevision($kind, $id, $action, $data, true);
    }

    public function approveCurrentRevision(string $kind, int $id, int $expectedRevision, string $expectedSubmittedAt): array
    {
        return $this->decideRevision($kind, $id, 'approve', [], false, ['revision_counter' => $expectedRevision, 'submitted_at' => $expectedSubmittedAt]);
    }

    private function decideRevision(string $kind, int $id, string $action, array $data, bool $requireSnapshot, ?array $expectedCurrent = null): array
    {
        Gate::authorize('content.review');
        abort_unless(in_array($action, ['approve', 'reject'], true), 404);

        return DB::transaction(function () use ($kind, $id, $action, $data, $requireSnapshot, $expectedCurrent): array {
            $review = $this->preview($kind, $id, true);
            abort_unless($review['status'] === 'review', 409, 'Revision không còn chờ duyệt.');
            if ($expectedCurrent !== null) {
                abort_unless((int) $review['number'] === $expectedCurrent['revision_counter'] && hash_equals((string) $review['submittedAt'], $expectedCurrent['submitted_at']), 409, 'Revision đã thay đổi sau khi chọn. Mục này chưa được duyệt.');
            }
            if ($requireSnapshot) {
                abort_unless((int) $review['number'] === (int) $data['revision_counter'] && hash_equals($review['token'], $data['review_token']), 409, 'Revision hoặc nội dung đã thay đổi. Mở lại bản mới trước khi quyết định.');
            }
            $selfReview = $action === 'approve'
                && (int) $review['author'] === (int) auth()->id()
                && auth()->user()?->hasRole('super-admin');
            abort_if($action === 'approve' && (int) $review['author'] === (int) auth()->id() && ! $selfReview, 403, 'Tác giả cần reviewer khác duyệt revision này.');
            if ($action === 'approve' && $review['blockers']) {
                throw ValidationException::withMessages(['content' => $review['blockers']]);
            }
            $note = trim($data['note'] ?? '');
            if ($action === 'reject' && $note === '') {
                throw ValidationException::withMessages(['note' => 'Nhập lý do trả chỉnh sửa.']);
            }
            if (in_array($kind, ['question', 'stimulus'], true)) {
                $revision = $this->revisions->transition($kind, $review['root'], $action, $note ?: null);
                $status = $revision->status;
            } else {
                $status = $action === 'approve' ? 'approved' : 'draft';
                if ($action === 'approve') {
                    $this->exams->approve($review['root'], $review['payload']);
                } else {
                    $review['root']->update(['status' => 'draft', 'draft_status' => $status, 'review_note' => $note ?: null]);
                }
                if ($action === 'approve') {
                    $review['root']->update(['review_note' => $note ?: null]);
                }
                $after = ['status' => $status, 'revision' => $review['number'], 'note' => $note ?: null];
                if ($selfReview) {
                    $after['self_review'] = true;
                }
                $this->audit->record('exam.'.$action, 'exam', $id, ['status' => 'review', 'revision' => $review['number']], $after);
            }

            return ['status' => $status, 'revision_counter' => $review['number']];
        }, 3);
    }
}
