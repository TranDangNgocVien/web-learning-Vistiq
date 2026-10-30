@php
    $details = $result['details'] ?? [];
    $review = $result['review'] ?? [];
    $feedback = $result['feedback'] ?? [];
    $title = $result['title'] ?? 'Bài làm';
    $titleLower = strtolower($title);
    $isIelts = str_contains($titleLower, 'ielts');
    $isToeic = str_contains($titleLower, 'toeic');
    $bands = is_array($details['bands'] ?? null) ? $details['bands'] : [];
    $scoreValue = $result['scaled_score'] ?? null;
    $scoreIsRaw = false;
    if (!is_numeric($scoreValue) && ($result['grading_status'] ?? '') !== 'pending' && is_numeric($result['raw_score'] ?? null)) {
        $scoreValue = $result['raw_score'];
        $scoreIsRaw = true;
    }
    $scoreIsIeltsBand = $isIelts && !$scoreIsRaw && is_numeric($scoreValue) && (float) $scoreValue <= 9 && count($bands) > 0;
    $scoreIsIeltsPercentage = $isIelts && !$scoreIsRaw && is_numeric($scoreValue) && !$scoreIsIeltsBand;
    $scoreDisplay = is_numeric($scoreValue)
        ? number_format((float) $scoreValue, $scoreIsIeltsBand || (float) $scoreValue !== floor((float) $scoreValue) ? 1 : 0)
        : null;
    $scoreMaximum = $scoreIsRaw ? null : ($scoreIsIeltsBand ? '9.0' : ($scoreIsIeltsPercentage ? '100' : ($isToeic ? '990' : null)));
    $scoreFill = $scoreMaximum && is_numeric($scoreValue)
        ? min(100, max(0, round((float) $scoreValue / (float) $scoreMaximum * 100, 1))).'%'
        : '0%';
    $singleBandSkill = count($bands) === 1 ? array_key_first($bands) : null;
    $scoreHeading = $scoreIsRaw
        ? 'Điểm thô'
        : ($scoreIsIeltsBand
            ? ($singleBandSkill === 'listening' ? 'Band Listening ước tính' : (count($bands) === 4 ? 'Band IELTS ước tính' : 'Band kỹ năng ước tính'))
            : ($scoreIsIeltsPercentage ? 'Tỷ lệ câu đúng' : ($isIelts ? 'Kết quả IELTS ước tính' : ($isToeic ? 'Điểm số ước tính TOEIC' : 'Điểm số ước tính'))));
    if ($scoreIsIeltsBand && $singleBandSkill && isset($details['by_skill'][$singleBandSkill])) {
        $bandRaw = (int) ($details['by_skill'][$singleBandSkill]['raw'] ?? 0);
        $bandTotal = (int) ($details['by_skill'][$singleBandSkill]['total'] ?? 0);
        $scoreNote = 'Ước tính từ '.$bandRaw.'/'.$bandTotal.' câu đúng; ngưỡng quy đổi có thể thay đổi theo phiên bản đề.';
    } elseif ($scoreIsIeltsBand) {
        $scoreNote = 'Band IELTS tổng hợp ước tính từ kết quả các kỹ năng.';
    } elseif ($scoreIsIeltsPercentage) {
        $scoreNote = 'Đây là tỷ lệ câu đúng; bài này chưa có đủ dữ liệu để quy đổi thành band IELTS.';
    } else {
        $scoreNote = $scoreIsRaw ? 'Chưa có bảng quy đổi cho bài làm này.' : 'Điểm quy đổi được ước tính từ các câu đã chấm.';
    }
    $correct = (int) ($details['correct'] ?? 0);
    $wrong = (int) ($details['wrong'] ?? 0);
    $unanswered = (int) ($details['unanswered'] ?? 0);
    $total = $correct + $wrong + $unanswered;
    $correctPercent = $total > 0 ? round($correct / $total * 100) : 0;
    $durationSeconds = max(0, (int) ($details['duration_seconds'] ?? 0));
    $hours = intdiv($durationSeconds, 3600);
    $minutes = intdiv($durationSeconds % 3600, 60);
    $seconds = $durationSeconds % 60;
    $durationText = ($hours > 0 ? $hours.' giờ ' : '').($minutes > 0 ? $minutes.' phút ' : '').$seconds.' giây';
    $skillStats = [];
    foreach (($details['by_skill'] ?? []) as $skill => $score) {
        $skillTotal = (int) ($score['total'] ?? 0);
        $skillCorrect = (int) ($score['raw'] ?? 0);
        $skillStats[] = [
            'label' => match ($skill) {
                'listening' => 'Listening',
                'reading' => 'Reading',
                'writing' => 'Writing',
                'speaking' => 'Speaking',
                default => ucfirst(str_replace('_', ' ', (string) $skill)),
            },
            'correct' => $skillCorrect,
            'total' => $skillTotal,
            'percent' => $skillTotal > 0 ? round($skillCorrect / $skillTotal * 100) : 0,
            'band' => $bands[$skill] ?? null,
            'band_progress' => is_numeric($bands[$skill] ?? null) ? round((float) $bands[$skill] / 9 * 100) : null,
        ];
    }
    $weakestSkill = null;
    foreach ($skillStats as $skillStat) {
        if ($skillStat['total'] > 0 && ($weakestSkill === null || $skillStat['percent'] < $weakestSkill['percent'])) {
            $weakestSkill = $skillStat;
        }
    }
@endphp

    @include('pages.learner.assessment.exam-taking-and-submission.results.styles')
    <div class="attempt-results" aria-labelledby="result-title">
        <nav class="ar-breadcrumbs" aria-label="Đường dẫn">
            <a href="{{ route('profile.history') }}">Lượt làm của tôi</a>
            <span aria-hidden="true">›</span>
            <span>Kết quả</span>
        </nav>

        <header class="ar-heading">
            <div>
                <h1 id="result-title">Kết quả bài làm</h1>
                <p>{{ $title }}</p>
                <div class="ar-tags" aria-label="Thông tin bài làm">
                    <span class="ar-tag ar-tag--blue">{{ $isIelts ? 'IELTS' : ($isToeic ? 'TOEIC' : 'Bài luyện tập') }}</span>
                    <span class="ar-tag">Mode Exam · Điểm ước tính</span>
                    @if (!empty($result['leaderboard_available']))
                        <a class="ar-tag" href="{{ route('exams.leaderboard', $result['exam_id']) }}">Xem bảng xếp hạng</a>
                    @endif
                </div>
            </div>
            <div class="ar-actions">
                <a class="button button-quiet" href="{{ route('profile.history') }}">@include('pages.dictionary.icon', ['name' => 'clock']) Lượt làm của tôi</a>
                <a class="button button-primary" href="{{ route('exams.index') }}">@include('pages.dictionary.icon', ['name' => 'arrow-left']) Danh sách đề</a>
            </div>
        </header>

        @if (($result['grading_status'] ?? '') === 'pending')
            <div class="ar-pending-note" role="status">Writing hoặc Speaking đang chờ chấm. Điểm hiện tại là tạm tính và sẽ được cập nhật sau khi hoàn tất chấm bài.</div>
        @endif

        <div class="ar-layout">
            <div class="ar-main-column">
                <section class="ar-card ar-summary" aria-label="Tổng quan điểm số">
                    <div class="ar-ring" style="--ar-score-fill: {{ $scoreFill }}" role="img" aria-label="{{ $scoreDisplay ? 'Điểm '.$scoreDisplay.($scoreMaximum ? ' trên '.$scoreMaximum : '') : 'Chưa có điểm cuối' }}">
                        <div><strong>{{ $scoreDisplay ?? '—' }}</strong><span>{{ $scoreMaximum ? '/ '.$scoreMaximum : 'điểm' }}</span></div>
                    </div>
                    <div class="ar-score-copy">
                        <h2>{{ $scoreHeading }}</h2>
                        <div class="ar-score-line">{{ $scoreDisplay ?? 'Chờ chấm' }} @if ($scoreMaximum)<small>/ {{ $scoreMaximum }}</small>@endif</div>
                        <div class="ar-score-note">
                            <b>{{ ($result['grading_status'] ?? '') === 'pending' ? 'Đang chờ chấm một số phần' : 'Bài làm đã được nộp' }}</b>
                            {{ $scoreDisplay ? $scoreNote : 'Điểm cuối sẽ xuất hiện sau khi hoàn tất chấm bài.' }}
                        </div>
                    </div>
                    <ul class="ar-metrics" aria-label="Thống kê bài làm">
                        <li class="ar-metric"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'clock'])</span><span class="ar-metric-label">Thời gian làm</span><strong>{{ $durationText }}</strong></li>
                    <li class="ar-metric"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'grid'])</span><span class="ar-metric-label">Tổng số câu</span><strong>{{ $total }} câu</strong></li>
                        <li class="ar-metric ar-metric--good"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'check'])</span><span class="ar-metric-label">Đúng</span><strong>{{ $correct }} câu ({{ $correctPercent }}%)</strong></li>
                        <li class="ar-metric ar-metric--wrong"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'close'])</span><span class="ar-metric-label">Sai</span><strong>{{ $wrong }} câu ({{ $total > 0 ? round($wrong / $total * 100) : 0 }}%)</strong></li>
                        <li class="ar-metric ar-metric--muted"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'target'])</span><span class="ar-metric-label">Bỏ trống</span><strong>{{ $unanswered }} câu ({{ $total > 0 ? round($unanswered / $total * 100) : 0 }}%)</strong></li>
                    </ul>
                </section>

                <section class="ar-card ar-review-shell" aria-label="Phân tích bài làm">
                    <nav class="ar-tabs" aria-label="Các mục kết quả">
                        <a class="is-active" href="#ar-answers">@include('pages.dictionary.icon', ['name' => 'grid']) Xem chi tiết</a>
                        <a href="#ar-skills">@include('pages.dictionary.icon', ['name' => 'activity']) Thống kê theo kỹ năng</a>
                        <a href="#ar-weaknesses">@include('pages.dictionary.icon', ['name' => 'target']) Phân tích điểm yếu</a>
                    </nav>

                    <section id="ar-answers" aria-label="Đáp án được phép xem">
                        @forelse ($review as $itemKey => $reviewItem)
                            @php
                                $correctOptions = $reviewItem['correct_options'] ?? [];
                                $acceptedAnswers = $reviewItem['accepted_answers'] ?? [];
                                $correctOptions = is_array($correctOptions) ? $correctOptions : [$correctOptions];
                                $acceptedAnswers = is_array($acceptedAnswers) ? $acceptedAnswers : [$acceptedAnswers];
                            @endphp
                            <article class="ar-question-card" id="review-{{ $loop->index }}">
                                <header class="ar-question-head">
                                    <div class="ar-question-label">
                                        <strong>Câu {{ $itemKey }}</strong>
                                        <span class="ar-status ar-status--review">Đáp án được phép xem</span>
                                    </div>
                                    <span class="ar-part-badge">Lời giải theo quy định đề</span>
                                </header>
                                <div class="ar-question-body">
                                    <div class="ar-insights ar-insights--full">
                                        @if (count(array_filter($correctOptions)) || count(array_filter($acceptedAnswers)))
                                            <section class="ar-insight ar-insight--neutral">
                                                <h4>Đáp án chính xác</h4>
                                                <div class="ar-tags">
                                                    @foreach ($correctOptions as $answer)
                                                        @if (is_scalar($answer) && (string) $answer !== '')
                                                            <span class="ar-tag ar-tag--blue">{{ $answer }}</span>
                                                        @elseif (is_array($answer))
                                                            <span class="ar-tag ar-tag--blue">{{ json_encode($answer, JSON_UNESCAPED_UNICODE) }}</span>
                                                        @endif
                                                    @endforeach
                                                    @foreach ($acceptedAnswers as $answer)
                                                        @if (is_scalar($answer) && (string) $answer !== '')
                                                            <span class="ar-tag ar-tag--blue">{{ $answer }}</span>
                                                        @elseif (is_array($answer))
                                                            <span class="ar-tag ar-tag--blue">{{ json_encode($answer, JSON_UNESCAPED_UNICODE) }}</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endif
                                        @if (!empty($reviewItem['answer_map']) && is_array($reviewItem['answer_map']))
                                            <section class="ar-insight ar-insight--neutral">
                                                <h4>Đáp án theo từng chỗ trống</h4>
                                                <p>@foreach ($reviewItem['answer_map'] as $blank => $answer){{ $blank }}: {{ is_scalar($answer) ? $answer : json_encode($answer, JSON_UNESCAPED_UNICODE) }}@if (!$loop->last) · @endif @endforeach</p>
                                            </section>
                                        @endif
                                        @if (!empty($reviewItem['blanks']) && is_array($reviewItem['blanks']))
                                            <section class="ar-insight ar-insight--neutral">
                                                <h4>Đáp án chỗ trống</h4>
                                                <p>{{ implode(' · ', array_map(fn ($answer) => is_scalar($answer) ? (string) $answer : json_encode($answer, JSON_UNESCAPED_UNICODE), $reviewItem['blanks'])) }}</p>
                                            </section>
                                        @endif
                                        @if (!empty($reviewItem['explanation']))
                                            <section class="ar-insight">
                                                <h4>@include('pages.dictionary.icon', ['name' => 'sparkle']) Giải thích</h4>
                                                <div class="ar-review-copy">{!! app(\VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry::class)->sanitize($reviewItem['explanation']) !!}</div>
                                            </section>
                                        @endif
                                        @if (!empty($reviewItem['model_answer']))
                                            <section class="ar-insight ar-insight--vocab">
                                                <h4>Bài mẫu</h4>
                                                <p>{{ $reviewItem['model_answer'] }}</p>
                                            </section>
                                        @endif
                                        @foreach (($reviewItem['transcripts'] ?? []) as $transcript)
                                            @if ($transcript)
                                                <section class="ar-insight ar-insight--neutral"><h4>Bản chép lời</h4><p>{{ $transcript }}</p></section>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="ar-empty" style="margin:14px">
                                <b>Đáp án chưa được mở</b>
                                <p>Đề thi chưa cho xem đáp án hoặc chỉ mở lời giải sau khi kết thúc thời gian thi.</p>
                            </div>
                        @endforelse
                    </section>
                </section>

                <section class="ar-card ar-subsection" id="ar-skills">
                    <h2 class="ar-section-title">Thống kê theo kỹ năng <small>Câu đúng trên tổng câu đã chấm</small></h2>
                    @if (count($skillStats))
                        <div class="ar-skill-list">
                            @foreach ($skillStats as $skill)
                                <div class="ar-skill-row">
                                    <span>{{ $skill['label'] }}</span>
                                    <div class="ar-progress" role="img" aria-label="{{ $skill['label'] }} đạt {{ $skill['percent'] }} phần trăm"><span style="width:{{ $skill['percent'] }}%"></span></div>
                                    <strong>{{ $skill['correct'] }}/{{ $skill['total'] }} · {{ $skill['percent'] }}%</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="ar-empty">Chưa có dữ liệu theo kỹ năng cho bài làm này.</div>
                    @endif
                </section>

                <section class="ar-card ar-subsection" id="ar-weaknesses">
                    <h2 class="ar-section-title">Phân tích điểm yếu <small>Gợi ý dựa trên kết quả theo kỹ năng</small></h2>
                    @if ($weakestSkill)
                        <div class="ar-empty">
                            <b>{{ $weakestSkill['label'] }}: {{ $weakestSkill['correct'] }}/{{ $weakestSkill['total'] }} câu đúng ({{ $weakestSkill['percent'] }}%)</b>
                            <p>Ưu tiên luyện thêm kỹ năng này để cải thiện kết quả ở lượt làm tiếp theo.</p>
                        </div>
                    @else
                        <div class="ar-empty">Chưa đủ dữ liệu để xác định kỹ năng cần ôn tập.</div>
                    @endif
                </section>

                @foreach ($feedback as $itemFeedback)
                    <section class="ar-card ar-feedback">
                        <h2 class="ar-section-title">{{ $itemFeedback['item_key'] ?? 'Bài tự luận' }} · Nhận xét giám khảo</h2>
                        @if (!empty($itemFeedback['grade']['feedback']))<p>{{ $itemFeedback['grade']['feedback'] }}</p>@endif
                        @if (!empty($itemFeedback['grade']['criteria']) && is_array($itemFeedback['grade']['criteria']))
                            <div class="ar-feedback-criteria">
                                @foreach ($itemFeedback['grade']['criteria'] as $criterion => $score)
                                    <span>{{ ucfirst(str_replace('_', ' ', $criterion)) }}: {{ $score }}</span>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <aside class="ar-side-column" aria-label="Tóm tắt kết quả">
                <section class="ar-card ar-side-card">
                    <h2 class="ar-section-title">Kết quả theo kỹ năng</h2>
                    @if (count($skillStats))
                        <div class="ar-part-list">
                            @foreach ($skillStats as $skill)
                                @php($hasSkillBand = $isIelts && is_numeric($skill['band']))
                                @php($skillProgress = $hasSkillBand ? $skill['band_progress'] : $skill['percent'])
                                <div class="ar-part-row">
                                    <span class="ar-part-name">{{ $skill['label'] }}</span>
                                    <div class="ar-progress" role="img" aria-label="{{ $hasSkillBand ? $skill['label'].' band '.number_format((float) $skill['band'], 1).' trên 9.0' : $skill['percent'].' phần trăm' }}"><span style="width:{{ $skillProgress }}%"></span></div>
                                    <span class="ar-part-score">{{ $hasSkillBand ? 'Band '.number_format((float) $skill['band'], 1) : $skill['percent'].'%' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="ar-empty">Chưa có thống kê theo kỹ năng.</div>
                    @endif
                </section>

                <section class="ar-card ar-side-card">
                    <h2 class="ar-section-title">Câu có đáp án mở <small>{{ count($review) }}</small></h2>
                    @if (count($review))
                        <div class="ar-question-map" aria-label="Đi tới câu có đáp án mở">
                            @foreach ($review as $itemKey => $reviewItem)
                                <a class="ar-map-number ar-map-number--review" href="#review-{{ $loop->index }}" aria-label="Đi tới câu {{ $itemKey }}">{{ $loop->iteration }}</a>
                            @endforeach
                        </div>
                        <div class="ar-legend"><span><i class="is-review"></i>Có lời giải</span></div>
                    @else
                        <div class="ar-empty">Chưa có câu nào được mở đáp án theo quy định của đề.</div>
                    @endif
                </section>

                @if (!empty($result['leaderboard_available']))
                    <a class="button button-primary button-full" href="{{ route('exams.leaderboard', $result['exam_id']) }}">Xem bảng xếp hạng</a>
                @endif
            </aside>
        </div>
    </div>
