<?php

namespace VistiqLearner\Http\Controllers\Profile;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
use VistiqCore\Services\Analytics\LeaderboardService;
use VistiqCore\Services\Assessment\Attempts\AttemptQueryService;
use VistiqCore\Services\Knowledge\KnowledgeReaderService;
use VistiqCore\Services\System\AuditService;
use VistiqLearner\Http\Controllers\Controller;
use VistiqLearner\Http\Requests\Profile\HistoryFilterRequest;
use VistiqLearner\Http\Requests\Profile\ProfileUpdateRequest;

class ProfileController extends Controller
{
    public function show(string $screen): RedirectResponse
    {
        return redirect()->route($screen === 'history' ? 'profile.history' : 'profile.show');
    }

    private function page(string $title, string $view, array $data): View
    {
        return view('shared.demo-page', ['page' => ['title' => $title, 'view' => $view, 'section' => 'profile', 'shell' => 'user', 'slug' => 'profile', 'show_heading' => false], ...$data]);
    }

    public function index(Request $request, KnowledgeReaderService $knowledge, AttemptQueryService $query, LeaderboardService $leaderboard): View
    {
        $user = $request->user();
        $profile = DB::table('user_profiles')->where('user_id', $user->id)->first();
        $avatar = $profile?->avatar_path && str_starts_with($profile->avatar_path, 'avatars/'.$user->id.'/') ? Storage::disk('public')->url($profile->avatar_path) : null;
        $summary = $query->profileSummary($user->id);
        $saved = $knowledge->savedItems($user->id);
        $rank = $leaderboard->bestRankForUser($user->id);
        $stats = ['submitted' => $summary['submitted'], 'accuracy' => $summary['accuracy'], 'saved' => $saved->total()];
        $recent = collect($summary['recent'])->map(fn ($attempt) => ['title' => ($attempt->grading_status === 'completed' ? 'Hoàn thành ' : 'Đã nộp ').($attempt->snapshot['title'] ?? 'Đề đã lưu'), 'detail' => $attempt->scaled_score !== null ? $attempt->scaled_score.' điểm' : 'Chờ chấm điểm', 'date' => $attempt->submitted_at, 'url' => route('attempts.result', $attempt), 'icon' => 'check-circle', 'tone' => 'green']);
        $recent = $recent->concat($saved->getCollection()->take(5)->map(fn ($item) => ['title' => 'Đã lưu '.$item->canonical_text, 'detail' => $item->type_label, 'date' => Carbon::parse($item->saved_at), 'url' => route($item->type_code === 'grammar' ? 'grammar.show' : 'knowledge.show', $item->slug), 'icon' => 'star', 'tone' => 'violet']))->sortByDesc(fn ($item) => $item['date']->timestamp)->take(5);
        $timeZone = $profile?->time_zone ?: config('app.timezone');

        return $this->page('Hồ sơ cá nhân', 'pages.learner.profile.show', compact('user', 'profile', 'avatar', 'stats', 'recent', 'rank', 'timeZone'));
    }

    public function update(ProfileUpdateRequest $request, AuditService $audit): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $newAvatar = null;
        if ($request->hasFile('avatar')) {
            $newAvatar = $request->file('avatar')->store('avatars/'.$user->id, 'public');
            if (! $newAvatar) {
                throw ValidationException::withMessages(['avatar' => 'Không thể lưu ảnh. Hãy thử lại.']);
            }
        }
        $oldAvatar = null;
        try {
            DB::transaction(function () use ($user, $data, $newAvatar, &$oldAvatar, $audit): void {
                $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
                $profile = DB::table('user_profiles')->where('user_id', $user->id)->lockForUpdate()->first();
                $oldAvatar = $profile?->avatar_path;
                $before = ['name' => $user->name, 'country_code' => $profile?->country_code, 'time_zone' => $profile?->time_zone];
                $user->forceFill(['name' => $data['name']])->save();
                DB::table('user_profiles')->updateOrInsert(['user_id' => $user->id], [
                    'country_code' => $data['country_code'] ?? null, 'time_zone' => $data['time_zone'] ?? null, 'bio' => $data['bio'] ?? null,
                    'avatar_path' => $newAvatar ?? (! empty($data['remove_avatar']) ? null : $oldAvatar),
                    'created_at' => $profile?->created_at ?? now(), 'updated_at' => now(),
                ]);
                $audit->record('profile.update', 'user', $user->id, $before, ['name' => $user->name, 'country_code' => $data['country_code'] ?? null, 'time_zone' => $data['time_zone'] ?? null, 'avatar_changed' => (bool) $newAvatar || ! empty($data['remove_avatar'])]);
            });
        } catch (Throwable $exception) {
            if ($newAvatar) {
                Storage::disk('public')->delete($newAvatar);
            }
            throw $exception;
        }
        if (($newAvatar || ! empty($data['remove_avatar'])) && $oldAvatar && str_starts_with($oldAvatar, 'avatars/'.$user->id.'/')) {
            Storage::disk('public')->delete($oldAvatar);
        }

        $request->user()->refresh();

        $response = redirect()->route('profile.show')->with('status', 'Đã lưu thay đổi hồ sơ.');

        return $request->expectsJson() ? response()->json(['redirect' => $response->getTargetUrl()]) : $response;
    }

    public function history(HistoryFilterRequest $request, AttemptQueryService $query): View
    {
        $filters = $request->validated();
        $timeZone = DB::table('user_profiles')->where('user_id', $request->user()->id)->value('time_zone') ?: config('app.timezone');
        $attempts = $query->listing($filters, $request->user()->id, $timeZone);
        $exams = DB::table('exam_templates')->join('exam_attempts', 'exam_attempts.exam_template_id', '=', 'exam_templates.id')->where('exam_attempts.user_id', $request->user()->id)->select('exam_templates.id', 'exam_templates.title')->distinct()->get();

        $historySummaryQuery = DB::table('exam_attempts')
            ->leftJoin(DB::source('attempt_results as result'), 'result.exam_attempt_id', '=', 'exam_attempts.id')
            ->where('exam_attempts.user_id', $request->user()->id);

        if (isset($filters['exam'])) {
            $historySummaryQuery->where('exam_attempts.exam_template_id', $filters['exam']);
        }

        if (isset($filters['status'])) {
            $historySummaryQuery->where('exam_attempts.status', $filters['status']);
        }

        if (isset($filters['from'])) {
            $historySummaryQuery->where(
                'exam_attempts.started_at',
                '>=',
                Carbon::parse($filters['from'], $timeZone)->startOfDay()->setTimezone(config('app.timezone'))->toDateTimeString(),
            );
        }

        if (isset($filters['to'])) {
            $historySummaryQuery->where(
                'exam_attempts.started_at',
                '<',
                Carbon::parse($filters['to'], $timeZone)->addDay()->startOfDay()->setTimezone(config('app.timezone'))->toDateTimeString(),
            );
        }

        $historySummary = $historySummaryQuery
            ->selectRaw('COUNT(DISTINCT exam_attempts.id) as total_attempts')
            ->selectRaw("COUNT(DISTINCT CASE WHEN exam_attempts.status = 'completed' THEN exam_attempts.id END) as completed_attempts")
            ->selectRaw("AVG(CASE WHEN result.grading_status = 'completed' THEN result.scaled_score END) as average_score")
            ->first();

        return $this->page('Lịch sử làm đề', 'pages.learner.profile.history', compact('attempts', 'filters', 'exams', 'timeZone', 'historySummary'));
    }

    public function saved(Request $request, KnowledgeReaderService $knowledge): View
    {
        return $this->page('Mục đã lưu', 'pages.learner.profile.saved', ['items' => $knowledge->savedItems($request->user()->id)]);
    }
}
