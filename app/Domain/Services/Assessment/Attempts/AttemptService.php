<?php

namespace VistiqCore\Services\Assessment\Attempts;

use Illuminate\Support\Carbon;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\AttemptAnswer;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\MediaAsset;
use VistiqCore\Models\User;
use VistiqCore\Services\Assessment\QuestionBank\MediaService;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;
use VistiqCore\Services\Assessment\Exams\ExamPublishingService;

class AttemptService
{
    public const EVENTS = ['FULLSCREEN_EXIT', 'TAB_HIDDEN', 'WINDOW_BLUR', 'COPY_ATTEMPT', 'PASTE_ATTEMPT', 'CONTEXT_MENU', 'RELOAD', 'NETWORK_DISCONNECT', 'MULTIPLE_SESSION', 'AUDIO_PLAY'];

    public function __construct(private ScoringService $scoring, private QuestionRegistry $registry, private MediaService $media, private ExamPublishingService $exams) {}

    public function start(ExamTemplate $exam, User $user): ExamAttempt
    {
        return DB::transaction(function () use ($exam, $user): ExamAttempt {
            $user = User::lockForUpdate()->findOrFail($user->id);
            abort_unless($user->status === 'active', 403);
            $exam = ExamTemplate::with('publishedRevision')->lockForUpdate()->findOrFail($exam->id);
            abort_unless($exam->status !== 'archived' && ($exam->draft_status === 'approved' || ($exam->status === 'published' && $exam->publishedRevision)), 404);
            $revision = $this->exams->approvedRevision($exam);
            $snapshot = $revision->payload;
            $snapshot['policies'] = ExamPublishingService::normalizePolicies((string) ($snapshot['mode'] ?? ''), $snapshot['policies'] ?? []);
            $opens = isset($snapshot['opens_at']) ? Carbon::parse($snapshot['opens_at']) : null;
            $closes = isset($snapshot['closes_at']) ? Carbon::parse($snapshot['closes_at']) : null;
            abort_if($opens?->isFuture() || $closes?->lte(now()), 409, 'Đề chưa mở hoặc đã đóng.');
            $existing = ExamAttempt::where('user_id', $user->id)->where('exam_template_id', $exam->id)->whereIn('status', ['in_progress', 'paused', 'expired'])->orderByDesc('id')->first();
            if ($existing) {
                $existing = $this->synchronize($existing);
                if (! $existing->submitted_at) {
                    return $existing;
                }
            }
            if ($snapshot['policies']['randomize_options'] ?? false) {
                foreach ($snapshot['items'] as &$item) {
                    if (in_array($item['type'], ['multiple_choice', 'multiple_response'], true)) {
                        $keys = array_keys($item['options']);
                        shuffle($keys);
                        // JSON object key order is not preserved by MySQL.
                        $item['option_order'] = $keys;
                    }
                }
                unset($item);
            }
            $expires = now()->addMinutes((int) $snapshot['policies']['duration_minutes']);
            if ($closes && $closes->lt($expires)) {
                $expires = $closes;
            }
            $attempt = ExamAttempt::create([
                'user_id' => $user->id, 'exam_template_id' => $exam->id, 'exam_revision_id' => $revision->id, 'snapshot' => $snapshot,
                'attempt_number' => (int) ExamAttempt::where('user_id', $user->id)->where('exam_template_id', $exam->id)->max('attempt_number') + 1,
                'status' => 'in_progress', 'leaderboard_status' => ($snapshot['policies']['leaderboard'] ?? false) ? 'approved' : 'private',
                'started_at' => now(), 'section_started_at' => now(), 'active_section' => 0, 'paused_seconds' => 0, 'expires_at' => $expires, 'client_session' => (string) Str::uuid(),
            ]);
            DB::table('user_activities')->insert(['user_id' => $user->id, 'action' => 'exam.start', 'entity' => (string) $exam->id, 'occurred_at' => now()]);

            return $attempt;
        });
    }

    public function authorize(ExamAttempt $attempt, int $userId): void
    {
        abort_unless((int) $attempt->user_id === $userId, 404);
    }

    public function deadline(ExamAttempt $attempt): ?Carbon
    {
        $deadline = $attempt->expires_at?->copy();
        $section = $attempt->snapshot['sections'][$attempt->active_section] ?? null;
        if ($section && ($section['duration_minutes'] ?? 0) > 0) {
            $sectionDeadline = $attempt->section_started_at->copy()->addMinutes($section['duration_minutes']);
            if (! $deadline || $sectionDeadline->lt($deadline)) {
                $deadline = $sectionDeadline;
            }
        }

        return $deadline;
    }

    public function synchronize(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt): ExamAttempt {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->submitted_at || $attempt->status === 'expired') {
                return $attempt;
            }
            $closes = $attempt->snapshot['closes_at'] ?? null;
            $closed = $closes && Carbon::parse($closes)->lte(now());
            if ($closed || (! $attempt->paused_at && $attempt->expires_at?->lte(now()))) {
                $timestamp = $closed ? Carbon::parse($closes)->timestamp : $attempt->expires_at->timestamp;
                // A running pause suspends the duration deadline, but cannot
                // extend the published closing time of the exam.
                if (! $attempt->paused_at && $attempt->expires_at) {
                    $timestamp = min($timestamp, $attempt->expires_at->timestamp);
                }
                $this->expire($attempt, $timestamp);

                return $attempt->refresh();
            }
            if ($attempt->paused_at) {
                return $attempt;
            }
            while ($this->deadline($attempt)?->lte(now())) {
                $previous = $attempt->snapshot['sections'][$attempt->active_section];
                $sections = $attempt->snapshot['sections'];
                $next = $attempt->active_section + 1;
                while (isset($sections[$next]) && ($sections[$next]['timing_group'] ?? $next) === ($previous['timing_group'] ?? $attempt->active_section)) {
                    $next++;
                }
                if (! isset($sections[$next])) {
                    $this->expire($attempt, $this->deadline($attempt)->timestamp);
                    break;
                }
                $attempt->update(['active_section' => $next, 'section_started_at' => $this->deadline($attempt), 'current_item_index' => $this->firstItemInSection($attempt, $next)]);
            }

            return $attempt->refresh();
        });
    }

    public function saveAnswers(ExamAttempt $attempt, array $answers): array
    {
        $attempt = $this->synchronize($attempt);

        return DB::transaction(function () use ($attempt, $answers): array {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            $this->writable($attempt);
            $items = collect($attempt->snapshot['items'])->keyBy('key');
            $saved = [];
            foreach ($answers as $data) {
                $item = $items->get($data['item_key']);
                if (! $item) {
                    throw ValidationException::withMessages(['answers' => 'Câu hỏi nằm ngoài snapshot của lượt thi.']);
                }
                $this->accessibleItem($attempt, $item);
                if (! ($attempt->snapshot['policies']['review_later'] ?? true) && ($data['flagged'] ?? false)) {
                    throw ValidationException::withMessages(['answers' => 'Đề không cho phép đánh dấu xem lại.']);
                }
                $response = $this->validateResponse($attempt, $item, $data['response'] ?? []);
                $answer = $attempt->answers()->where('item_key', $item['key'])->first();
                $sameRetry = $answer && (int) $answer->version === (int) ($data['version'] ?? 0) + 1
                    && $this->canonicalResponse($answer->response) === $this->canonicalResponse($response)
                    && (bool) $answer->flagged === (bool) ($data['flagged'] ?? false)
                    && (int) $answer->spent_seconds === (int) ($data['spent_seconds'] ?? $answer->spent_seconds ?? 0);
                abort_if(! $sameRetry && ($answer?->version ?? 0) !== ($data['version'] ?? 0), 409, 'Đáp án đã được cập nhật ở phiên khác.');
                if (! $sameRetry) {
                    $answer = AttemptAnswer::updateOrCreate(['exam_attempt_id' => $attempt->id, 'item_key' => $item['key']], [
                        'response' => $response, 'response_text' => $response['text'] ?? null, 'version' => ($answer?->version ?? 0) + 1,
                        'answered_at' => now(), 'spent_seconds' => $data['spent_seconds'] ?? $answer?->spent_seconds ?? 0, 'flagged' => $data['flagged'] ?? false,
                    ]);
                }
                $row = ['item_key' => $answer->item_key, 'version' => $answer->version];
                if (($attempt->snapshot['mode'] ?? null) !== 'full' && ($attempt->snapshot['policies']['answer_review'] ?? '') === 'after_each') {
                    $row['review'] = $this->reviewItem($item, ($attempt->snapshot['policies']['explanation_review'] ?? '') === 'after_each');
                    $row['grade'] = $this->scoring->gradeObjective($item, $response);
                }
                $saved[] = $row;
            }

            return $saved;
        });
    }

    private function writable(ExamAttempt $attempt): void
    {
        abort_if($attempt->submitted_at || $attempt->status !== 'in_progress' || $attempt->paused_at, 409, 'Lượt thi đã khóa hoặc đang tạm dừng.');
        abort_if($this->deadline($attempt)?->lte(now()), 409, 'Đã hết thời gian làm bài.');
    }

    private function accessibleItem(ExamAttempt $attempt, array $item, bool $checkCursor = true): void
    {
        $sections = $attempt->snapshot['sections'];
        $current = $sections[$attempt->active_section];
        $section = $sections[$item['section']];
        if (($current['duration_minutes'] ?? 0) > 0) {
            abort_unless(($current['timing_group'] ?? $attempt->active_section) === ($section['timing_group'] ?? $item['section']), 409, 'Phần thi này chưa mở hoặc đã kết thúc.');
        }
        if (($attempt->snapshot['policies']['navigation'] ?? 'free') === 'linear' || ($attempt->snapshot['policies']['lock_previous'] ?? false)) {
            abort_unless((int) $item['section'] === (int) $attempt->active_section, 409, 'Chỉ được sửa đáp án trong phần hiện tại.');
            if ($checkCursor) {
                abort_unless($item['key'] === ($attempt->snapshot['items'][$attempt->current_item_index]['key'] ?? null), 409, 'Câu hỏi đã khóa hoặc chưa được mở.');
            }
        }
    }

    private function firstItemInSection(ExamAttempt $attempt, int $section): int
    {
        foreach ($attempt->snapshot['items'] as $index => $item) {
            if ((int) $item['section'] === $section) {
                return $index;
            }
        }

        return 0;
    }

    public function navigate(ExamAttempt $attempt, string $itemKey): ExamAttempt
    {
        $attempt = $this->synchronize($attempt);

        return DB::transaction(function () use ($attempt, $itemKey): ExamAttempt {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            $this->writable($attempt);
            $items = collect($attempt->snapshot['items']);
            $index = $items->search(fn ($item) => $item['key'] === $itemKey);
            abort_if($index === false, 422, 'Câu hỏi không thuộc lượt thi.');
            $this->accessibleItem($attempt, $items[$index], false);
            $policies = $attempt->snapshot['policies'];
            abort_if(($policies['lock_previous'] ?? false) && $index < $attempt->current_item_index, 409, 'Không thể quay lại câu đã khóa.');
            abort_if(($policies['navigation'] ?? 'free') === 'linear' && abs($index - $attempt->current_item_index) > 1, 409, 'Chuyển lần lượt từng câu.');
            $attempt->update(['current_item_index' => $index]);

            return $attempt;
        });
    }

    private function canonicalResponse(array $response): array
    {
        if (! array_is_list($response)) {
            ksort($response);
        }
        foreach ($response as $key => $value) {
            if (is_array($value)) {
                $response[$key] = $this->canonicalResponse($value);
            }
        }

        return $response;
    }

    private function validateResponse(ExamAttempt $attempt, array $item, array $response): array
    {
        $family = QuestionRegistry::TYPES[$item['type']];
        $response = Validator::make($response, [
            'options' => 'sometimes|array|max:30', 'options.*' => 'string|max:30', 'text' => 'sometimes|nullable|string|max:100000',
            'matching' => 'sometimes|array|max:100', 'matching.*' => 'nullable|string|max:30', 'blanks' => 'sometimes|array|max:100',
            'blanks.*' => 'nullable|string|max:1000', 'asset_id' => 'sometimes|integer|exists:media_assets,id',
        ])->validate();
        if ($family === 'choice') {
            if (array_diff($response['options'] ?? [], array_keys($item['options'])) || count($response['options'] ?? []) > ($item['type'] === 'multiple_response' ? count($item['correct_options']) : 1)) {
                throw ValidationException::withMessages(['response' => 'Phương án không hợp lệ.']);
            }

            return ['options' => array_values(array_unique($response['options'] ?? []))];
        }
        if ($family === 'matching') {
            if (array_diff(array_keys($response['matching'] ?? []), array_keys($item['metadata']['answer_map'])) || array_diff(array_filter($response['matching'] ?? []), array_keys($item['options']))) {
                throw ValidationException::withMessages(['response' => 'Mapping không hợp lệ.']);
            }

            return ['matching' => $response['matching'] ?? []];
        }
        if ($family === 'speaking') {
            if (isset($response['asset_id'])) {
                $asset = MediaAsset::findOrFail($response['asset_id']);
                abort_unless((int) $asset->created_by === (int) auth()->id() && str_starts_with($asset->mime_type, 'audio/'), 422, 'Recording không hợp lệ.');
                $bound = (int) ($asset->metadata['recording_attempt_id'] ?? 0) === (int) $attempt->id && ($asset->metadata['recording_item_key'] ?? null) === $item['key'];
                $legacyAttached = empty($asset->metadata['recording_attempt_id']) && $attempt->answers()->where('item_key', $item['key'])->where('response->asset_id', $asset->id)->exists();
                abort_unless($bound || $legacyAttached, 422, 'Recording không thuộc câu hỏi của lượt làm này.');
            }

            return array_intersect_key($response, array_flip(['asset_id', 'text']));
        }
        if (isset($item['metadata']['blanks'])) {
            if (array_diff(array_keys($response['blanks'] ?? []), array_keys($item['metadata']['blanks']))) {
                throw ValidationException::withMessages(['response' => 'Ô đáp án không hợp lệ.']);
            }

            return ['blanks' => $response['blanks'] ?? []];
        }

        return ['text' => $response['text'] ?? ''];
    }

    public function submit(ExamAttempt $attempt): ExamAttempt
    {
        $attempt = $this->synchronize($attempt);

        return DB::transaction(function () use ($attempt): ExamAttempt {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            if (! $attempt->submitted_at) {
                $this->finish($attempt, $attempt->status === 'expired' ? 'TIMEOUT' : null, $attempt->status === 'expired' ? $attempt->expires_at->timestamp : null);
            }

            return $attempt->refresh();
        });
    }

    private function expire(ExamAttempt $attempt, int $timestamp): void
    {
        if (($attempt->snapshot['mode'] ?? null) === 'full' || ($attempt->snapshot['policies']['auto_submit'] ?? true)) {
            $this->finish($attempt, 'TIMEOUT', $timestamp);
        } else {
            if ($attempt->paused_at) {
                $attempt->paused_seconds += max(0, $timestamp - $attempt->paused_at->timestamp);
            }
            $attempt->update(['status' => 'expired', 'paused_at' => null, 'paused_seconds' => $attempt->paused_seconds, 'expires_at' => Carbon::createFromTimestamp($timestamp)]);
        }
    }

    private function finish(ExamAttempt $attempt, ?string $reason = null, ?int $timestamp = null): void
    {
        if ($attempt->paused_at) {
            $attempt->paused_seconds += max(0, ($timestamp ?? now()->timestamp) - $attempt->paused_at->timestamp);
        }
        $attempt->update(['submitted_at' => $timestamp ? Carbon::createFromTimestamp($timestamp) : now(), 'paused_at' => null, 'paused_seconds' => $attempt->paused_seconds, 'status' => 'submitted']);
        foreach ($attempt->snapshot['items'] as $item) {
            if (in_array(QuestionRegistry::TYPES[$item['type']], ['writing', 'speaking'], true)) {
                AttemptAnswer::firstOrCreate(['exam_attempt_id' => $attempt->id, 'item_key' => $item['key']], ['response' => []]);
            }
        }
        $this->scoring->calculate($attempt);
        if ($reason) {
            $this->recordEvent($attempt, $reason, (string) Str::uuid());
        }
        DB::table('user_activities')->insert(['user_id' => $attempt->user_id, 'action' => 'exam.submit', 'entity' => (string) $attempt->exam_template_id, 'occurred_at' => now()]);
    }

    public function pause(ExamAttempt $attempt, bool $resume = false): ExamAttempt
    {
        $attempt = $this->synchronize($attempt);

        return DB::transaction(function () use ($attempt, $resume): ExamAttempt {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_unless(($attempt->snapshot['mode'] ?? null) !== 'full' && ($attempt->snapshot['policies']['pause_allowed'] ?? false), 403, 'Đề không cho phép tạm dừng.');
            abort_if($attempt->submitted_at || $attempt->status === 'expired', 409);
            if ($resume && $attempt->paused_at) {
                $seconds = max(0, now()->timestamp - $attempt->paused_at->timestamp);
                $expires = $attempt->expires_at->copy()->addSeconds($seconds);
                if ($attempt->snapshot['closes_at'] ?? null) {
                    $expires = $expires->min(Carbon::parse($attempt->snapshot['closes_at']));
                }
                $attempt->update(['status' => 'in_progress', 'expires_at' => $expires, 'section_started_at' => $attempt->section_started_at->copy()->addSeconds($seconds), 'paused_seconds' => $attempt->paused_seconds + $seconds, 'paused_at' => null]);
            } elseif (! $resume && ! $attempt->paused_at) {
                $attempt->update(['status' => 'paused', 'paused_at' => now()]);
            }

            return $attempt;
        });
    }

    public function nextSection(ExamAttempt $attempt): ExamAttempt
    {
        $attempt = $this->synchronize($attempt);

        return DB::transaction(function () use ($attempt): ExamAttempt {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            $this->writable($attempt);
            $next = $attempt->active_section + 1;
            $sections = $attempt->snapshot['sections'];
            abort_unless(isset($sections[$next]), 422, 'Đã đến phần cuối.');
            if (($attempt->snapshot['policies']['navigation'] ?? 'free') === 'linear') {
                foreach ($attempt->snapshot['items'] as $index => $item) {
                    abort_if((int) $item['section'] === (int) $attempt->active_section && $index > $attempt->current_item_index, 409, 'Chuyển lần lượt tới câu cuối của phần trước khi sang phần tiếp theo.');
                }
            }
            $sameGroup = ($sections[$next]['timing_group'] ?? $next) === ($sections[$attempt->active_section]['timing_group'] ?? $attempt->active_section);
            $attempt->update(['active_section' => $next, 'section_started_at' => $sameGroup ? $attempt->section_started_at : now(), 'current_item_index' => $this->firstItemInSection($attempt, $next)]);

            return $attempt;
        });
    }

    public function event(ExamAttempt $attempt, array $data): array
    {
        $attempt = $this->synchronize($attempt);

        return DB::transaction(function () use ($attempt, $data): array {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            abort_if($attempt->submitted_at || $attempt->status === 'expired', 409);
            abort_unless(in_array($data['type'], self::EVENTS, true), 422);
            if (! empty($data['item_key'])) {
                abort_unless(collect($attempt->snapshot['items'])->contains('key', $data['item_key']), 422);
            }
            if ($attempt->snapshot['policies']['event_logging'] ?? true) {
                $this->recordEvent($attempt, $data['type'], $data['event_key'], $data['item_key'] ?? null);
            }
            $warnings = DB::table('attempt_events')->join(DB::source('attempt_event_types'), 'attempt_event_types.id', '=', 'attempt_events.attempt_event_type_id')->where('exam_attempt_id', $attempt->id)->whereIn('code', ['FULLSCREEN_EXIT', 'TAB_HIDDEN', 'WINDOW_BLUR', 'COPY_ATTEMPT', 'PASTE_ATTEMPT', 'CONTEXT_MENU'])->count();
            $threshold = $attempt->snapshot['policies']['auto_submit_threshold'] ?? 0;
            $policies = $attempt->snapshot['policies'] ?? [];
            $fullscreenViolation = $data['type'] === 'FULLSCREEN_EXIT' && ($policies['require_fullscreen'] ?? false);
            $focusViolation = in_array($data['type'], ['TAB_HIDDEN', 'WINDOW_BLUR'], true)
                && ($policies['require_fullscreen'] ?? false)
                && ($policies['track_focus'] ?? false);
            if ($fullscreenViolation || $focusViolation) {
                $this->finish($attempt, 'AUTO_SUBMIT');
            } elseif ($threshold > 0 && $warnings >= $threshold) {
                $this->finish($attempt, 'AUTO_SUBMIT');
            }

            return [
                'warnings' => $warnings,
                'submitted' => (bool) $attempt->submitted_at,
                'message' => $fullscreenViolation
                    ? 'Đã thoát toàn màn hình. Bài thi được nộp tự động.'
                    : ($focusViolation ? 'Đã rời khỏi bài thi. Bài thi được nộp tự động.'
                    : 'Tín hiệu được ghi để Reviewer xem xét.'),
            ];
        });
    }

    private function recordEvent(ExamAttempt $attempt, string $type, string $key, ?string $itemKey = null): void
    {
        $typeId = DB::table('attempt_event_types')->where('code', $type)->value('id');
        if (! $typeId) {
            $typeId = DB::table('attempt_event_types')->insertGetId(['code' => $type, 'name' => $type]);
        }
        DB::table('attempt_events')->insertOrIgnore(['exam_attempt_id' => $attempt->id, 'attempt_event_type_id' => $typeId, 'event_type' => $type, 'event_key' => $key, 'item_key' => $itemKey, 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function publicSnapshot(array $snapshot, ?ExamAttempt $attempt = null): array
    {
        unset($snapshot['scoring']);
        if (($snapshot['mode'] ?? null) === 'full') {
            $snapshot['policies'] = ExamPublishingService::normalizePolicies('full', $snapshot['policies'] ?? []);
        }
        $snapshot['items'] = array_map(fn (array $item): array => $this->registry->publicItem($item), $snapshot['items']);
        foreach ($snapshot['items'] as &$item) {
            foreach (['image_id', 'audio_id'] as $field) {
                if (! empty($item['metadata'][$field]) && ($asset = MediaAsset::find($item['metadata'][$field]))) {
                    if ($field !== 'audio_id' || ! ($snapshot['policies']['strict_audio'] ?? false)) {
                        $item['metadata'][str_replace('_id', '_url', $field)] = $this->assetUrl($asset, $attempt);
                    }
                }
            }
            foreach ($item['stimuli'] ?? [] as $index => $stimulus) {
                if (! empty($stimulus['metadata']['asset_id']) && ($asset = MediaAsset::find($stimulus['metadata']['asset_id']))) {
                    $item['stimuli'][$index]['asset_path'] = $this->assetUrl($asset, $attempt);
                } elseif (! empty($stimulus['asset_path']) && ! filter_var($stimulus['asset_path'], FILTER_VALIDATE_URL)) {
                    $item['stimuli'][$index]['asset_path'] = Storage::disk('public')->url($stimulus['asset_path']);
                }
                if ($stimulus['stimulus_type'] === 'audio' && ($snapshot['policies']['strict_audio'] ?? false)) {
                    $item['stimuli'][$index]['asset_path'] = null;
                }
            }
        }
        unset($item);

        return $snapshot;
    }

    public function state(ExamAttempt $attempt): array
    {
        $attempt = $this->synchronize($attempt);

        return ['id' => $attempt->id, 'status' => $attempt->status, 'snapshot' => $this->publicSnapshot($attempt->snapshot, $attempt), 'active_section' => $attempt->active_section, 'current_item_index' => (int) $attempt->current_item_index, 'paused' => (bool) $attempt->paused_at, 'deadline' => $this->deadline($attempt)?->toIso8601String(), 'server_time' => now()->toIso8601String(), 'answers' => $attempt->answers()->get(['item_key', 'response', 'version', 'flagged', 'spent_seconds'])->map(function ($answer) use ($attempt): array {
            $row = $answer->toArray();
            if (! empty($answer->response['asset_id']) && ($asset = MediaAsset::find($answer->response['asset_id']))) {
                $row['recording_url'] = $this->assetUrl($asset, $attempt);
            }

            return $row;
        })->all()];
    }

    public function assetUrl(MediaAsset $asset, ?ExamAttempt $attempt = null): string
    {
        return $attempt && Route::has('attempts.media') ? route('attempts.media', [$attempt, $asset]) : $this->media->url($asset);
    }

    public function assetAllowed(ExamAttempt $attempt, MediaAsset $asset): void
    {
        $related = false;
        $audio = false;
        $assetId = (int) $asset->getKey();
        foreach ($attempt->snapshot['items'] as $item) {
            if ((int) ($item['metadata']['image_id'] ?? 0) === $assetId) {
                $related = true;
            }
            if ((int) ($item['metadata']['audio_id'] ?? 0) === $assetId) {
                $related = true;
                $audio = true;
            }
            foreach ($item['stimuli'] ?? [] as $stimulus) {
                if ((int) ($stimulus['metadata']['asset_id'] ?? 0) === $assetId) {
                    $related = true;
                    $audio = $audio || $stimulus['stimulus_type'] === 'audio';
                }
            }
        }
        $recording = $attempt->answers()->where('response->asset_id', $asset->id)->exists();
        abort_unless($related || $recording, 404);
        if ($audio && ($attempt->snapshot['policies']['strict_audio'] ?? false)) {
            abort_unless(DB::table('attempt_events')->where('exam_attempt_id', $attempt->id)->where('event_key', 'audio-'.$asset->id)->exists(), 403);
        }
    }

    public function recordingAllowed(ExamAttempt $attempt, string $itemKey): void
    {
        $attempt = $this->synchronize($attempt);
        $this->writable($attempt);
        $item = collect($attempt->snapshot['items'])->firstWhere('key', $itemKey);
        abort_unless($item && $item['type'] === 'speaking', 422);
        $this->accessibleItem($attempt, $item);
    }

    public function audio(ExamAttempt $attempt, string $itemKey, ?int $stimulusId = null): string
    {
        $attempt = $this->synchronize($attempt);

        return DB::transaction(function () use ($attempt, $itemKey, $stimulusId): string {
            $attempt = ExamAttempt::lockForUpdate()->findOrFail($attempt->id);
            $this->writable($attempt);
            $item = collect($attempt->snapshot['items'])->firstWhere('key', $itemKey);
            abort_unless($item, 404);
            $this->accessibleItem($attempt, $item);
            $stimulus = $stimulusId ? collect($item['stimuli'] ?? [])->firstWhere('id', $stimulusId) : null;
            abort_if($stimulusId && (! $stimulus || $stimulus['stimulus_type'] !== 'audio'), 404);
            $assetId = $stimulus ? ($stimulus['metadata']['asset_id'] ?? null) : ($item['metadata']['audio_id'] ?? null);
            $audioKey = 'audio-'.($assetId ?: 'stimulus-'.$stimulusId);
            if ($attempt->snapshot['policies']['strict_audio'] ?? false) {
                abort_if(DB::table('attempt_events')->where('exam_attempt_id', $attempt->id)->where('event_key', $audioKey)->exists(), 409, 'Audio chỉ được phát một lần.');
                $this->recordEvent($attempt, 'AUDIO_PLAY', $audioKey, $itemKey);
            }
            if ($assetId) {
                return $this->assetUrl(MediaAsset::findOrFail($assetId), $attempt);
            }
            abort_unless($stimulus && ! empty($stimulus['asset_path']), 404);

            return filter_var($stimulus['asset_path'], FILTER_VALIDATE_URL) ? $stimulus['asset_path'] : Storage::disk('public')->url($stimulus['asset_path']);
        });
    }

    private function allowedReview(string $policy, ExamAttempt $attempt): bool
    {
        return match ($policy) {
            'after_each' => true, 'after_submit' => (bool) $attempt->submitted_at, 'after_close' => $attempt->submitted_at && ! empty($attempt->snapshot['closes_at']) && Carbon::parse($attempt->snapshot['closes_at'])->lte(now()), default => false
        };
    }

    private function reviewItem(array $item, bool $explanations): array
    {
        $review = array_intersect_key($item, array_flip(['correct_options', 'accepted_answers']));
        $review['answer_map'] = $item['metadata']['answer_map'] ?? null;
        $review['blanks'] = $item['metadata']['blanks'] ?? null;
        if ($explanations) {
            $review['explanation'] = $item['explanation'] ?? null;
            $review['model_answer'] = $item['metadata']['model_answer'] ?? null;
            $review['transcripts'] = array_column($item['stimuli'] ?? [], 'transcript');
        }

        return $review;
    }

    public function result(ExamAttempt $attempt): array
    {
        $attempt = $this->synchronize($attempt);
        abort_unless($attempt->submitted_at, 409, 'Bài chưa nộp.');
        $result = DB::table('attempt_results')->where('exam_attempt_id', $attempt->id)->first();
        $storedDetails = json_decode($result?->details ?? '{}', true) ?: [];
        if ($result && ($attempt->snapshot['exam_type'] ?? null) === 'ielts' && ($storedDetails['scoring_version'] ?? 0) < ScoringService::RESULT_VERSION) {
            $this->scoring->calculate($attempt);
            $result = DB::table('attempt_results')->where('exam_attempt_id', $attempt->id)->first();
        }
        $policies = $attempt->snapshot['policies'];
        $review = [];
        if ($this->allowedReview($policies['answer_review'] ?? 'never', $attempt)) {
            foreach ($attempt->snapshot['items'] as $item) {
                $review[$item['key']] = $this->reviewItem($item, $this->allowedReview($policies['explanation_review'] ?? 'never', $attempt));
            }
        }

        return ['attempt_id' => $attempt->id, 'exam_id' => $attempt->exam_template_id, 'leaderboard_available' => (bool) ($policies['leaderboard'] ?? false), 'title' => $attempt->snapshot['title'], 'raw_score' => $result?->raw_score, 'scaled_score' => $result?->scaled_score, 'grading_status' => $result?->grading_status, 'details' => json_decode($result?->details ?? '{}', true), 'estimated' => true, 'review' => $review, 'feedback' => $attempt->answers()->whereNotNull('grade')->get(['item_key', 'grade'])->toArray()];
    }
}
