<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\Attempts;

use Illuminate\Support\Facades\Gate;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Assessment\Attempts\AttemptFilterRequest;
use VistiqAdmin\Http\Requests\Admin\Assessment\Attempts\ModerationRequest;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Services\Assessment\Attempts\AttemptModerationService;
use VistiqCore\Services\Assessment\Attempts\AttemptQueryService;
use VistiqCore\Services\Assessment\QuestionBank\MediaService;

class AttemptController extends AdminPageController
{
    public function index(AttemptFilterRequest $request, AttemptQueryService $query): mixed
    {
        $filters = $request->validated();

        return $this->page('Lượt làm bài', 'pages.admin.assessment.attempts.workspace.index', [
            'attempts' => $query->adminListing($filters),
            'summary' => $query->adminSummary($filters),
            'filters' => $filters,
        ]);
    }

    public function detail(ExamAttempt $attempt, AttemptQueryService $query): mixed
    {
        Gate::authorize('results.view');

        return $this->page('Lượt thi #'.$attempt->id, 'pages.admin.assessment.attempts.workspace.detail', $query->detail($attempt));
    }

    public function recording(ExamAttempt $attempt, MediaAsset $asset, MediaService $media): mixed
    {
        abort_unless(Gate::allows('results.view') || Gate::allows('results.grade'), 403);
        abort_unless(str_starts_with($asset->mime_type, 'audio/'), 404);

        $answer = $attempt->answers()->get()->first(
            fn ($answer) => (int) ($answer->response['asset_id'] ?? 0) === $asset->id,
        );
        $item = $answer ? collect($attempt->snapshot['items'] ?? [])->firstWhere('key', $answer->item_key) : null;
        abort_unless($item && ($item['type'] ?? null) === 'speaking', 404);

        $disk = $media->diskFor($asset);
        abort_unless($disk->exists($asset->path), 404, 'Bản ghi âm không còn trong kho lưu trữ.');

        return $disk->response($asset->path, null, ['Content-Type' => $asset->mime_type, 'Cache-Control' => 'private, no-store']);
    }

    public function moderate(ModerationRequest $request, ExamAttempt $attempt, AttemptModerationService $service): mixed
    {
        $service->moderate($attempt, $request->validated());

        return back()->with('status', 'Đã lưu quyết định kiểm duyệt.');
    }
}
