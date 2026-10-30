@extends('layouts.app')
@php($page=['title'=>$snapshot['title'],'section'=>'exams'])
@section('content')
<main class="assessment-page exam-session-page" data-exam-runner aria-keyshortcuts="1 2 3 4 ArrowLeft ArrowRight">
<header class="exam-session-toolbar"><div class="exam-session-heading"><a href="{{ route('profile.history') }}">Lượt làm của tôi</a><h1>{{ $snapshot['title'] }}</h1><span class="badge badge-soft">Mode Exam · {{ $snapshot['mode']==='full'?'Đề đầy đủ':'Đề ngắn' }}</span></div><div class="exam-session-clock-wrap"><div class="exam-session-clock" aria-label="Thời gian còn lại"><strong data-timer>Đang đồng bộ giờ server</strong></div><button type="button" class="button button-primary exam-submit-button" data-submit>Nộp bài</button></div></header>
<section class="exam-session-progress" aria-label="Tiến độ bài làm"><div class="exam-progress-main"><div class="exam-progress-label"><span>Đã trả lời</span><strong><span data-answered-count>0</span> / <span data-question-total>{{ count($snapshot['items']) }}</span></strong></div><div class="exam-progress-track"><i data-progress-bar></i></div></div><div class="exam-progress-stat is-answered"><span class="exam-stat-dot"></span><span>Đã trả lời</span><strong data-answered-summary>0</strong></div><div class="exam-progress-stat is-unanswered"><span class="exam-stat-dot"></span><span>Chưa trả lời</span><strong data-unanswered-count>{{ count($snapshot['items']) }}</strong></div><div class="exam-progress-stat is-flagged"><span class="exam-stat-dot"></span><span>Đánh dấu</span><strong data-flagged-count>0</strong></div></section>
<p class="notice-box exam-session-warning" data-warning role="alert" hidden></p><div class="assessment-actions exam-session-actions"><span data-save-status role="status" aria-live="polite">Đã tải trạng thái từ server</span><div><small class="muted" role="note" aria-label="Phím tắt bàn phím">Phím 1–4 chọn đáp án · ←/→ chuyển câu</small> @unless($snapshot['policies']['require_fullscreen']??false)<button class="button button-quiet" type="button" data-fullscreen>Toàn màn hình</button>@endunless @if($snapshot['policies']['pause_allowed']??false)<button class="button button-quiet" type="button" data-pause>Tạm dừng</button>@endif<button class="button button-quiet" type="button" data-next-section>Phần tiếp theo</button></div></div>
<div class="assessment-layout exam-session-layout"><aside class="panel assessment-navigation exam-question-sidebar" data-navigation aria-label="Danh sách câu hỏi"></aside><section class="exam-session-items" data-items aria-live="polite"></section></div><noscript><p role="alert">Cần bật JavaScript để tải câu hỏi, lưu và nộp bài.</p></noscript>
<script type="application/json" data-exam-config>{!! json_encode(['preview'=>false,'snapshot'=>$snapshot,'state'=>$state,'endpoints'=>$endpoints,'csrf'=>csrf_token(),'watermark'=>auth()->user()?->name],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) !!}</script>
</main>
<script>
(() => {
    const root = document.querySelector('[data-exam-runner]');
    if (!root || root.dataset.keyboardShortcutsInitialized === 'true') return;
    root.dataset.keyboardShortcutsInitialized = 'true';

    document.addEventListener('keydown', event => {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;

        const active = document.activeElement;
        if (active && active !== document.body && active !== document.documentElement && !root.contains(active)) return;
        const modalOpen = [...document.querySelectorAll('dialog[open], [role="dialog"][aria-modal="true"]')]
            .some(dialog => !dialog.closest('[hidden], [aria-hidden="true"]') && dialog.getClientRects().length > 0);
        if (modalOpen) return;

        const target = event.target instanceof Element ? event.target : null;
        if (target?.isContentEditable || target?.closest('[contenteditable]:not([contenteditable="false"]), [role="textbox"], audio, video')) return;

        const field = target?.closest('input, textarea, select');
        const focusedChoice = field instanceof HTMLInputElement && field.name === 'answer' && ['radio', 'checkbox'].includes(field.type);
        if (field && !focusedChoice) return;

        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const direction = event.key === 'ArrowLeft' ? -1 : 1;
            const button = root.querySelector(`[data-items] [data-step-question="${direction}"]`);
            if (button && !button.disabled) button.click();
            return;
        }

        const answerNumber = Number(event.key);
        if (!Number.isInteger(answerNumber) || answerNumber < 1 || answerNumber > 4) return;

        const options = root.querySelectorAll('[data-items] input[name="answer"]');
        const option = options[answerNumber - 1];
        if (option && !option.disabled) {
            event.preventDefault();
            option.click();
        }
    });
})();
</script>
@endsection
