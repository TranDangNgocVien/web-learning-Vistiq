@include('pages.learner.assessment.exam-taking-and-submission.results.styles')

@php
    $variant = $page['variant'] ?? 'toeic';
    $isIelts = $variant === 'ielts';
    $isSkill = $variant === 'skill';
    $score = $isIelts ? '7.5' : ($isSkill ? '86' : '810');
    $scoreMaximum = $isIelts ? '9.0' : ($isSkill ? '100' : '990');
    $scoreFill = $isIelts ? '83.3%' : ($isSkill ? '86%' : '81.8%');
    $examTitle = $isIelts ? 'IELTS Reading' : ($isSkill ? 'TOEIC Part 5' : 'TOEIC - Full Test 01');
    $parts = [
        ['Part 1', 'Photographs', 6, 6],
        ['Part 2', 'Question & Response', 23, 25],
        ['Part 3', 'Conversations', 34, 39],
        ['Part 4', 'Talks', 27, 30],
        ['Part 5', 'Incomplete Sentences', 28, 30],
        ['Part 6', 'Text Completion', 16, 16],
        ['Part 7', 'Reading Comprehension', 28, 54],
    ];
    $sampleQuestions = [
        [
            'number' => 1,
            'part' => 'Part 1 – Photographs',
            'correct' => true,
            'prompt' => 'What is shown in the picture?',
            'options' => [
                ['letter' => 'A', 'text' => 'A group of people are having a meeting.', 'correct' => false, 'selected' => false],
                ['letter' => 'B', 'text' => 'A table is surrounded by several chairs.', 'correct' => true, 'selected' => true],
                ['letter' => 'C', 'text' => 'A man is writing on a board.', 'correct' => false, 'selected' => false],
                ['letter' => 'D', 'text' => 'Some documents are on the floor.', 'correct' => false, 'selected' => false],
            ],
            'explanation' => 'Trong hình chỉ có một bàn họp với nhiều ghế xung quanh. Không có người đang họp, không có bảng viết và cũng không có tài liệu dưới sàn.',
            'vocabulary' => ['table (n): bàn', 'chair (n): ghế', 'surrounded (v): bao quanh', 'meeting room (n): phòng họp'],
        ],
        [
            'number' => 2,
            'part' => 'Part 1 – Photographs',
            'correct' => false,
            'prompt' => 'What is the man doing?',
            'options' => [
                ['letter' => 'A', 'text' => 'He is looking at a document.', 'correct' => false, 'selected' => true],
                ['letter' => 'B', 'text' => 'He is opening a window.', 'correct' => false, 'selected' => false],
                ['letter' => 'C', 'text' => 'He is speaking to a customer.', 'correct' => true, 'selected' => false],
                ['letter' => 'D', 'text' => 'He is cleaning a table.', 'correct' => false, 'selected' => false],
            ],
            'explanation' => 'Người đàn ông đang trao đổi với khách hàng. Hãy nghe hành động chính và đối tượng mà người nói nhắc đến trước khi chọn đáp án.',
            'vocabulary' => ['customer (n): khách hàng', 'speak to (v): nói chuyện với', 'document (n): tài liệu'],
        ],
    ];
    $weakPart = $parts[array_key_last($parts)];
@endphp

<div class="attempt-results">
    <nav class="ar-breadcrumbs" aria-label="Đường dẫn">
        <a href="{{ route('demo.page', 'exams') }}">Luyện đề</a>
        <span aria-hidden="true">›</span>
        <span>Kết quả</span>
    </nav>

    <header class="ar-heading">
        <div>
            <h1 id="result-title">Kết quả bài làm</h1>
            <p>{{ $examTitle }}</p>
            <div class="ar-tags" aria-label="Thông tin bài làm">
                <span class="ar-tag ar-tag--blue">{{ $isIelts ? 'IELTS' : 'TOEIC' }}</span>
                <span class="ar-tag">Đề thi số 01</span>
                <span class="ar-tag">{{ $isIelts ? 'Reading' : ($isSkill ? 'Part 5' : 'Full Test (Part 1–7)') }}</span>
                <span class="ar-tag">@include('pages.dictionary.icon', ['name' => 'clock']) Làm lúc: 19/10/2026 20:12</span>
            </div>
        </div>
        <div class="ar-actions">
            <a class="button button-quiet" href="{{ route('demo.page', $isIelts ? 'ielts-exam-detail' : ($isSkill ? 'skill-list' : 'exam-detail')) }}">@include('pages.dictionary.icon', ['name' => 'repeat']) Làm lại đề này</a>
            <a class="button button-primary" href="{{ route('demo.page', $isIelts ? 'ielts-exam-detail' : 'exam-detail') }}">@include('pages.dictionary.icon', ['name' => 'book']) Xem đề gốc</a>
        </div>
    </header>

    <div class="ar-layout">
        <div class="ar-main-column">
            <section class="ar-card ar-summary" aria-label="Tổng quan điểm số">
                <div class="ar-ring" style="--ar-score-fill: {{ $scoreFill }}" role="img" aria-label="Điểm {{ $score }} trên {{ $scoreMaximum }}">
                    <div><strong>{{ $score }}</strong><span>/ {{ $scoreMaximum }}</span></div>
                </div>
                <div class="ar-score-copy">
                    <h2>{{ $isIelts ? 'Band điểm ước tính' : ($isSkill ? 'Điểm luyện tập' : 'Điểm số ước tính TOEIC') }}</h2>
                    <div class="ar-score-line">{{ $score }} <small>/ {{ $scoreMaximum }}</small></div>
                    <div class="ar-score-note">
                        <b>Mức độ: Khá giỏi</b>
                        Bạn đã nắm vững kiến thức và có thể luyện thêm để đạt mục tiêu cao hơn.
                    </div>
                </div>
                <ul class="ar-metrics" aria-label="Thống kê bài làm">
                    <li class="ar-metric"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'clock'])</span><span class="ar-metric-label">Thời gian làm</span><strong>32 phút 15 giây</strong></li>
                    <li class="ar-metric"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'grid'])</span><span class="ar-metric-label">Tổng số câu</span><strong>200 câu</strong></li>
                    <li class="ar-metric ar-metric--good"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'check'])</span><span class="ar-metric-label">Đúng</span><strong>162 câu (81%)</strong></li>
                    <li class="ar-metric ar-metric--wrong"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'close'])</span><span class="ar-metric-label">Sai</span><strong>38 câu (19%)</strong></li>
                    <li class="ar-metric ar-metric--muted"><span class="ar-metric-icon">@include('pages.dictionary.icon', ['name' => 'target'])</span><span class="ar-metric-label">Bỏ trống</span><strong>0 câu (0%)</strong></li>
                </ul>
            </section>

            <section class="ar-card ar-review-shell" aria-label="Chi tiết bài làm">
                <nav class="ar-tabs" aria-label="Các mục kết quả">
                    <a class="is-active" href="#ar-answers">@include('pages.dictionary.icon', ['name' => 'grid']) Xem chi tiết</a>
                    <a href="#ar-skills">@include('pages.dictionary.icon', ['name' => 'activity']) Thống kê theo kỹ năng</a>
                    <a href="#ar-weaknesses">@include('pages.dictionary.icon', ['name' => 'target']) Phân tích điểm yếu</a>
                </nav>

                <div class="ar-part-chips" aria-label="Phân bố câu hỏi theo Part">
                    <span class="ar-part-chip is-active">Tất cả (200)</span>
                    @foreach ($parts as [$part, $name, $correct, $total])
                        <span class="ar-part-chip">{{ $part }} ({{ $total }})</span>
                    @endforeach
                </div>

                <div id="ar-answers">
                    @foreach ($sampleQuestions as $question)
                        <article class="ar-question-card" id="ar-question-{{ $question['number'] }}">
                            <header class="ar-question-head">
                                <div class="ar-question-label">
                                    <strong>Câu {{ $question['number'] }}</strong>
                                    <span class="ar-status {{ $question['correct'] ? '' : 'ar-status--wrong' }}">
                                        @include('pages.dictionary.icon', ['name' => $question['correct'] ? 'check' : 'close'])
                                        {{ $question['correct'] ? 'Đúng' : 'Sai' }}
                                    </span>
                                </div>
                                <span class="ar-part-badge">{{ $question['part'] }}</span>
                            </header>

                            <div class="ar-question-body">
                                <div class="ar-question-media">
                                    @if ($question['number'] === 1)
                                        <svg viewBox="0 0 360 220" role="img" aria-label="Phòng họp với một bàn dài và nhiều ghế">
                                            <rect width="360" height="220" fill="#e8ebee"/>
                                            <path d="M0 0h360v135H0z" fill="#f1f2f3"/>
                                            <path d="M25 28h196v90H25z" fill="#d8dde1" stroke="#9da6ad" stroke-width="3"/>
                                            <path d="M122 28v90M25 74h196" stroke="#aab2b8" stroke-width="3"/>
                                            <path d="M249 36v65m-14-43h30" stroke="#838d94" stroke-width="4"/>
                                            <path d="M0 135h360v85H0z" fill="#d5d9dc"/>
                                            <path d="M65 148l226 2-23 35H91z" fill="#aeb5ba" stroke="#77828a" stroke-width="3"/>
                                            <path d="M103 184v27m151-27v27" stroke="#737e85" stroke-width="6"/>
                                            <path d="M76 151v-16m0 0h43v24m-43-24v31m197-14v-17m0 0h42v24m-42-24v32" fill="none" stroke="#646f77" stroke-width="5"/>
                                            <path d="M305 69v82m-20-60h40m-34 2l-8 32h26l-8-32" fill="none" stroke="#67727a" stroke-width="4"/>
                                            <path d="M28 48l-13-17m14 24L9 48m21-3 4-23m12 30 15-18m-12 29 22-6" stroke="#6d8977" stroke-width="4"/>
                                            <path d="M0 215h360" stroke="#aab1b7" stroke-width="5"/>
                                        </svg>
                                    @else
                                        <svg viewBox="0 0 360 220" role="img" aria-label="Người đàn ông đang gọi điện thoại">
                                            <rect width="360" height="220" fill="#e7ebee"/>
                                            <rect x="18" y="20" width="324" height="162" rx="6" fill="#f2f3f4"/>
                                            <path d="M18 142h324v40H18z" fill="#d1d7db"/>
                                            <circle cx="184" cy="76" r="34" fill="#9fa8ae"/>
                                            <path d="M118 184c8-48 30-73 66-73s57 25 66 73" fill="#858f96"/>
                                            <path d="M208 52q23 14 4 37" fill="none" stroke="#45515a" stroke-width="7" stroke-linecap="round"/>
                                            <rect x="211" y="70" width="10" height="22" rx="4" fill="#37434b" transform="rotate(-16 211 70)"/>
                                            <path d="M42 40h76M42 54h55M285 41h35M285 54h35" stroke="#c2c9ce" stroke-width="4"/>
                                        </svg>
                                    @endif
                                </div>

                                <div>
                                    <h3 class="ar-question-prompt">{{ $question['prompt'] }}</h3>
                                    <div class="ar-options" aria-label="Các lựa chọn đáp án">
                                        @foreach ($question['options'] as $option)
                                            <div class="ar-option {{ $option['correct'] ? 'ar-option--correct' : ($option['selected'] ? 'ar-option--wrong' : '') }}">
                                                <span class="ar-option-mark">@if ($option['selected']) @include('pages.dictionary.icon', ['name' => 'check']) @endif</span>
                                                <span><b>{{ $option['letter'] }}.</b> {{ $option['text'] }}</span>
                                                <span class="ar-option-check">@if ($option['correct']) @include('pages.dictionary.icon', ['name' => 'check']) @endif</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="ar-insights">
                                    <section class="ar-insight">
                                        <h4>@include('pages.dictionary.icon', ['name' => 'sparkle']) Giải thích</h4>
                                        <p>{{ $question['explanation'] }}</p>
                                    </section>
                                    <section class="ar-insight ar-insight--vocab">
                                        <h4>@include('pages.dictionary.icon', ['name' => 'idea']) Từ vựng liên quan</h4>
                                        <ul class="ar-vocab-list">
                                            @foreach ($question['vocabulary'] as $word)
                                                <li>{{ $word }}</li>
                                            @endforeach
                                        </ul>
                                    </section>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="ar-card ar-subsection" id="ar-skills">
                <h2 class="ar-section-title">Thống kê theo kỹ năng <small>Kết quả ước tính</small></h2>
                <div class="ar-skill-list">
                    <div class="ar-skill-row"><span>Listening</span><div class="ar-progress" role="img" aria-label="Listening đạt 82 phần trăm"><span style="width:82%"></span></div><strong>82%</strong></div>
                    <div class="ar-skill-row"><span>Reading</span><div class="ar-progress" role="img" aria-label="Reading đạt 80 phần trăm"><span style="width:80%"></span></div><strong>80%</strong></div>
                </div>
            </section>

            <section class="ar-card ar-subsection" id="ar-weaknesses">
                <h2 class="ar-section-title">Phân tích điểm yếu <small>Gợi ý luyện tập tiếp theo</small></h2>
                <div class="ar-empty">
                    <b>{{ $weakPart[0] }} · {{ $weakPart[1] }}: {{ $weakPart[2] }}/{{ $weakPart[3] }} câu đúng</b>
                    <p>Luyện thêm bài đọc dài để cải thiện khả năng tìm thông tin chi tiết và suy luận theo ngữ cảnh.</p>
                </div>
            </section>

            <div class="ar-bottom-action">
                <a class="button button-primary" href="{{ route('demo.page', 'exams') }}">@include('pages.dictionary.icon', ['name' => 'arrow-left']) Quay lại danh sách đề</a>
            </div>
        </div>

        <aside class="ar-side-column" aria-label="Tóm tắt câu trả lời">
            <section class="ar-card ar-side-card">
                <h2 class="ar-section-title">Tỷ lệ đúng/sai theo Part</h2>
                <div class="ar-part-list">
                    @foreach ($parts as [$part, $name, $correct, $total])
                        @php($percent = $total > 0 ? round($correct / $total * 100) : 0)
                        <div class="ar-part-row">
                            <span class="ar-part-name">{{ $part }} – {{ $name }}</span>
                            <div class="ar-progress" role="img" aria-label="{{ $percent }} phần trăm đúng"><span style="width:{{ $percent }}%"></span></div>
                            <span class="ar-part-score">{{ $correct }}/{{ $total }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="ar-card ar-side-card">
                <h2 class="ar-section-title">Danh sách câu hỏi <small>200 câu</small></h2>
                <div class="ar-question-map" aria-label="Trạng thái các câu trong bài làm">
                    @for ($number = 1; $number <= 50; $number++)
                        @php($isWrong = in_array($number, [2, 23, 27], true))
                        @if ($number === 1 || $number === 2)
                            <a class="ar-map-number {{ $isWrong ? 'ar-map-number--wrong' : 'ar-map-number--available' }}" href="#ar-question-{{ $number }}" aria-label="Câu {{ $number }} – {{ $isWrong ? 'sai' : 'đúng' }}">{{ $number }}</a>
                        @else
                            <span class="ar-map-number {{ $isWrong ? 'ar-map-number--wrong' : 'ar-map-number--available' }}" aria-label="Câu {{ $number }} – {{ $isWrong ? 'sai' : 'đúng' }}">{{ $number }}</span>
                        @endif
                    @endfor
                </div>
                <div class="ar-legend" aria-label="Chú giải trạng thái">
                    <span><i></i>Đúng</span><span><i class="is-wrong"></i>Sai</span><span><i class="is-blank"></i>Bỏ trống</span>
                </div>
            </section>
        </aside>
    </div>
</div>
