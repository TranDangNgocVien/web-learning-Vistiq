<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\QuestionBank;

use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use VistiqAdmin\Http\Requests\Admin\Assessment\QuestionBank\MediaUploadRequest;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\QuestionBank\QuestionAudioService;
use VistiqCore\Services\System\AuditService;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\Assessment\QuestionBank\MediaService;
use VistiqCore\Services\Assessment\QuestionBank\ResourceQuestionLinkService;
use VistiqCore\Services\Assessment\QuestionBank\ResourceLibraryService;

class MediaController extends AdminPageController
{
    public function index(Request $request, ResourceLibraryService $library): mixed
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:255', 'type' => 'nullable|in:audio,image',
            'category' => 'nullable|in:toeic,ielts,part-1,part-2,part-3,part-4,listening,reading',
            'format' => 'nullable|in:mp3,wav,m4a,ogg,jpg,jpeg,png,webp',
            'status' => 'nullable|in:used,unassigned', 'size' => 'nullable|in:small,medium,large',
            'creator' => 'nullable|integer', 'sort' => 'nullable|in:updated,name,size',
            'per_page' => 'nullable|in:10,20,50', 'page' => 'nullable|integer|min:1',
        ]);
        $catalog = $library->catalog();
        $filtered = $catalog->filter(function (MediaAsset $asset) use ($filters): bool {
            $search = mb_strtolower($filters['q'] ?? '');
            $category = str_replace('-', ' ', $filters['category'] ?? '');
            $labels = mb_strtolower(implode(' ', [...$asset->resource_categories, $asset->resource_category]));
            $extension = $asset->resource_format;

            return ($search === '' || str_contains(mb_strtolower($asset->title.' '.$asset->path.' '.($asset->metadata['source'] ?? '')), $search))
                && (empty($filters['type']) || $asset->resource_kind === $filters['type'])
                && ($category === '' || str_contains($labels, $category))
                && (empty($filters['format']) || $extension === ($filters['format'] === 'jpeg' ? 'jpg' : $filters['format']))
                && (empty($filters['status']) || $asset->resource_status === $filters['status'])
                && (empty($filters['creator']) || $asset->created_by === (int) $filters['creator'])
                && match ($filters['size'] ?? '') {
                    'small' => $asset->size < 1048576,
                    'medium' => $asset->size >= 1048576 && $asset->size <= 10485760,
                    'large' => $asset->size > 10485760,
                    default => true,
                };
        });
        $filtered = match ($filters['sort'] ?? 'updated') {
            'name' => $filtered->sortBy(fn ($asset) => mb_strtolower($asset->title), SORT_NATURAL),
            'size' => $filtered->sortByDesc('size'),
            default => $filtered,
        };
        $perPage = (int) ($filters['per_page'] ?? 10);
        $page = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($filtered->count() / $perPage)));
        $assets = new LengthAwarePaginator($filtered->forPage($page, $perPage)->values(), $filtered->count(), $perPage, $page, ['path' => route('admin.resources.index'), 'query' => $request->query()]);
        $stimuliByAsset = $library->stimuliForAssets($assets->getCollection());
        $assets->getCollection()->each(fn (MediaAsset $asset) => $asset->setRelation('resourceStimuli', $stimuliByAsset[$asset->id] ?? collect()));

        $data = [
            'assets' => $assets, 'filters' => $filters,
            'resourceStats' => ['total' => $catalog->count(), 'audio' => $catalog->where('resource_kind', 'audio')->count(), 'image' => $catalog->where('resource_kind', 'image')->count(), 'size' => $catalog->sum('size'), 'unassigned' => $catalog->where('resource_status', 'unassigned')->count()],
            'creators' => $catalog->unique('created_by')->filter(fn ($asset) => $asset->created_by)->mapWithKeys(fn ($asset) => [$asset->created_by => $asset->resource_creator]),
        ];

        return $request->expectsJson()
            ? response()->json(['html' => view('pages.admin.assessment.question-bank.workspace.media-library', $data)->render(), 'url' => $assets->url($page)])
            : $this->page('Tài nguyên', 'pages.admin.assessment.question-bank.workspace.media', $data);
    }

    public function editResource(Request $request, MediaAsset $resource, ResourceLibraryService $library): mixed
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:255', 'exam_type' => 'nullable|integer|exists:exam_types,id',
            'part' => 'nullable|integer|exists:exam_parts,id', 'group' => 'nullable|integer|exists:question_groups,id',
            'page' => 'nullable|integer|min:1',
        ]);
        $asset = $library->catalog()->firstWhere('id', $resource->id);
        abort_unless($asset, 404);

        $groups = DB::table('question_groups')->orderBy('title')->get(['id', 'title', 'kind', 'exam_part_id', 'metadata']);
        $questionCounts = QuestionBankItem::query()
            ->select('question_group_id')
            ->selectRaw('COUNT(*) as total')
            ->whereIn('question_group_id', $groups->pluck('id'))
            ->groupBy('question_group_id')
            ->pluck('total', 'question_group_id');
        $attachedGroups = $groups->filter(function ($group) use ($asset): bool {
            $metadata = json_decode((string) ($group->metadata ?? ''), true) ?: [];

            return (int) ($metadata['audio_id'] ?? $metadata['image_id'] ?? 0) === $asset->id;
        })->values();
        $directQuestionIds = $asset->linkedQuestions->filter(function (QuestionBankItem $question) use ($asset): bool {
            $field = $asset->resource_kind === 'audio' ? 'audio_id' : 'image_id';
            foreach ([$question->draftRevision, $question->publishedRevision] as $revision) {
                if ((int) ($revision?->payload['metadata'][$field] ?? 0) === $asset->id) {
                    return true;
                }
            }

            return false;
        })->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $questions = QuestionBankItem::query()
            ->with(['examPart.examType', 'examPart.skill', 'draftRevision', 'learningItem', 'difficulty'])
            ->when(($filters['q'] ?? '') !== '', function ($query) use ($filters): void {
                $search = '%'.$filters['q'].'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('prompt', 'like', $search)
                        ->orWhereHas('draftRevision', fn ($revision) => $revision->where('payload->prompt', 'like', $search));
                });
            })
            ->when(isset($filters['exam_type']), fn ($query) => $query->whereHas('examPart', fn ($part) => $part->where('exam_type_id', $filters['exam_type'])))
            ->when(isset($filters['part']), fn ($query) => $query->where('exam_part_id', $filters['part']))
            ->when(isset($filters['group']), fn ($query) => $query->where('question_group_id', $filters['group']))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return $this->page('Chỉnh sửa tài nguyên', 'pages.admin.assessment.question-bank.workspace.resource-editor', [
            'asset' => $asset,
            'groups' => $groups,
            'attachedGroups' => $attachedGroups,
            'questionCounts' => $questionCounts,
            'questions' => $questions,
            'directQuestionIds' => $directQuestionIds,
            'linkedQuestionIds' => $asset->linkedQuestions->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'filters' => $filters,
            'examTypes' => DB::table('exam_types')->orderBy('name')->get(['id', 'name']),
            'parts' => DB::table('exam_parts')->orderBy('sort_order')->get(['id', 'name', 'exam_type_id']),
            'formatFileSize' => static function (int $size): string {
                foreach (['GB' => 1073741824, 'MB' => 1048576, 'KB' => 1024] as $unit => $divisor) {
                    if ($size >= $divisor) return number_format($size / $divisor, $unit === 'KB' ? 0 : 1).' '.$unit;
                }
                return $size.' B';
            },
        ]);
    }

    public function update(Request $request, MediaAsset $asset, AuditService $audit): mixed
    {
        $data = $request->validate(['title' => 'required|string|max:255', 'source' => 'nullable|string|max:1000', 'category' => 'nullable|string|max:120', 'description' => 'nullable|string|max:2000', 'transcript' => 'nullable|string|max:50000', 'tags' => 'nullable|string|max:500']);
        $before = $asset->only(['title', 'metadata']);
        $asset->update(['title' => $data['title'], 'metadata' => [...($asset->metadata ?? []), 'source' => $data['source'] ?? '', 'category' => $data['category'] ?? '', 'description' => $data['description'] ?? '', 'transcript' => $data['transcript'] ?? '', 'tags' => array_values(array_filter(array_map('trim', explode(',', $data['tags'] ?? ''))))]]);
        $audit->record('media.update', 'media', $asset->id, $before, $asset->only(['title', 'metadata']));

        return $request->expectsJson()
            ? response()->json(['id' => $asset->id, 'message' => 'Đã cập nhật tài nguyên.'])
            : redirect()->route('admin.resources.edit', $asset)->with('status', 'Đã cập nhật tài nguyên.');
    }

    public function syncQuestions(Request $request, MediaAsset $resource, ResourceQuestionLinkService $links): mixed
    {
        $data = $request->validate([
            'action' => 'required|in:attach,detach', 'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'required|integer|distinct|exists:question_bank_items,id',
        ]);
        $updated = $links->apply($resource, $data['ids'], $data['action']);
        $verb = $data['action'] === 'attach' ? 'gắn vào' : 'gỡ khỏi';

        return back()->with('status', "Đã {$verb} tài nguyên cho {$updated} câu hỏi.");
    }

    public function setGroupAudio(Request $request, MediaAsset $resource, QuestionAudioService $audio): mixed
    {
        $data = $request->validate(['action' => 'required|in:attach,detach', 'group_id' => 'required|integer|exists:question_groups,id']);
        abort_unless(str_starts_with($resource->mime_type, 'audio/'), 422, 'Chỉ audio mới có thể gắn vào nhóm câu hỏi.');
        if ($data['action'] === 'attach') {
            $result = $audio->assignToGroup((int) $data['group_id'], $resource->id);
        } else {
            $result = $audio->removeFromGroup((int) $data['group_id'], $resource->id);
        }

        $verb = $data['action'] === 'attach' ? 'gắn' : 'gỡ';

        return back()->with('status', "Đã {$verb} tài nguyên audio cho nhóm câu hỏi ({$result['updated_questions']} câu được cập nhật).");
    }

    public function destroy(Request $request, ResourceLibraryService $library, MediaService $media, AuditService $audit, ContentRevisionService $revisions): mixed
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:50', 'ids.*' => 'required|integer|distinct|exists:media_assets,id']);
        $deleted = 0;
        foreach (MediaAsset::query()->whereIn('id', $data['ids'])->get() as $asset) {
            $stimuli = $library->stimuliForAsset($asset);
            if ($library->isReferenced($asset, $stimuli->modelKeys())) {
                continue;
            }
            try {
                DB::transaction(function () use ($stimuli, $asset, $revisions, $media, $audit): void {
                    foreach ($stimuli as $stimulus) {
                        $revisions->delete('stimulus', $stimulus);
                    }
                    $audit->record('media.delete', 'media', $asset->id, $asset->only(['title', 'path']), []);
                    $media->delete($asset);
                });
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) {
                    throw $exception;
                }

                continue;
            }
            $deleted++;
        }

        $skipped = count($data['ids']) - $deleted;
        $message = 'Đã xóa '.$deleted.' tài nguyên; bỏ qua '.$skipped.' tài nguyên đang được sử dụng.';

        return $request->expectsJson()
            ? response()->json(['deleted' => $deleted, 'skipped' => $skipped, 'message' => $message])
            : back()->with('status', $message);
    }

    public function store(MediaUploadRequest $request, MediaService $media, AuditService $audit, QuestionAudioService $questionAudio): mixed
    {
        $questionId = $request->integer('question_id') ?: null;
        $questionGroupId = $request->integer('question_group_id') ?: null;
        $isAudio = str_starts_with((string) $request->file('file')->getMimeType(), 'audio/');
        if ($isAudio && $questionGroupId) {
            $hasGroupQuestions = QuestionBankItem::query()->where('question_group_id', $questionGroupId)->exists()
                || ($questionId !== null && QuestionBankItem::query()->whereKey($questionId)->exists());
            abort_unless($hasGroupQuestions, 422, 'Nhóm câu hỏi chưa có câu hỏi để gắn audio.');
        }
        $asset = $media->store($request->file('file'), auth()->id(), $isAudio && $questionGroupId ? null : $questionId);
        $asset->update([
            'title' => $request->validated('title'),
            'metadata' => array_filter([
                ...$asset->metadata,
                'question_id' => $questionGroupId ? null : $questionId,
                'question_group_id' => $questionGroupId,
                ...$request->safe()->only(['source', 'transcript', 'speaker_labels']),
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
        if ($isAudio && $request->boolean('create_stimulus') && ! $questionId && ! $questionGroupId) {
            DB::transaction(function () use ($asset, $request): void {
                $title = pathinfo($asset->title, PATHINFO_FILENAME) ?: $asset->title;
                $stimulus = LearningStimulus::create(['created_by' => auth()->id(), 'title' => $title, 'stimulus_type' => 'audio', 'body' => '', 'status' => 'draft']);
                app(ContentRevisionService::class)->save('stimulus', $stimulus, [
                    'title' => $title, 'stimulus_type' => 'audio', 'body' => '',
                    'transcript' => $request->validated('transcript') ?? '', 'metadata' => ['asset_id' => $asset->id],
                ], 0);
            });
        }
        $assignment = $isAudio && $questionGroupId
            ? $questionAudio->assignToGroup($questionGroupId, $asset->id, $questionId)
            : null;
        $audit->record('media.upload', 'media', $asset->id, [], ['disk' => $asset->disk, 'path' => $asset->path]);

        return $request->expectsJson() ? response()->json(array_filter([
            'id' => $asset->id,
            'url' => route('admin.media.show', $asset),
            ...($assignment ?? []),
        ], fn (mixed $value): bool => $value !== null)) : back()->with('status', 'Đã tải lên tài nguyên.');
    }

    public function show(MediaAsset $asset, MediaService $media): mixed
    {
        return redirect()->away($media->url($asset));
    }

    public function waveform(MediaAsset $asset, MediaService $media): mixed
    {
        abort_unless(str_starts_with($asset->mime_type, 'audio/'), 404);
        $stream = $media->diskFor($asset)->readStream($asset->path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, ['Content-Type' => $asset->mime_type, 'Cache-Control' => 'private, max-age=900']);
    }
}
