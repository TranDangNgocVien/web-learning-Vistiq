@if(isset($item))
    <div class="article-layout">
        <article class="panel learning-article">
            <div class="article-meta">
                <span class="badge badge-blue">{{ $item->category_name ?? 'NGỮ PHÁP' }} · {{ $item->level_label ?? 'B1' }}</span>
                <a href="{{ route('grammar.index') }}" style="color: #617390; text-decoration: none; font-size: 11px;">← Danh sách chủ điểm</a>
            </div>

            <h2>{{ $item->canonical_text }}</h2>

            @if(!empty($item->summary))
                <p class="article-lead">{{ $item->summary }}</p>
            @endif

            @if(isset($rules) && $rules->count() > 0)
                @foreach($rules as $rule)
                    <div style="margin-top: 24px;">
                        <h3 style="margin-bottom: 8px;">{{ $rule->title }}</h3>

                        @if(!empty($rule->formula))
                            <div class="article-callout">
                                <b>Cấu trúc</b>
                                <p>{!! nl2br(e($rule->formula)) !!}</p>
                            </div>
                        @endif

                        @if(!empty($rule->explanation))
                            <p style="font-size: 13px; line-height: 1.7; color: #304769;">
                                {!! nl2br(e($rule->explanation)) !!}
                            </p>
                        @endif

                        @php
                            $examples = $examplesByRule[$rule->id] ?? collect();
                        @endphp
                        @if($examples->count() > 0)
                            <h4 style="font-size: 13px; margin: 14px 0 8px; color: #1a4f9d;">Ví dụ minh họa</h4>
                            <ol class="lesson-points">
                                @foreach($examples as $ex)
                                    <li>
                                        <b>{{ $ex->english }}</b>
                                        @if(!empty($ex->vietnamese))
                                            <span>{{ $ex->vietnamese }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="article-callout">
                    <b>Cấu trúc</b>
                    <p>Đang cập nhật nội dung chi tiết cho chủ điểm này.</p>
                </div>
            @endif

            <div style="margin-top: 28px;">
                <a class="button button-primary" href="{{ route('demo.page', 'skill-list') }}">
                    Luyện tập chủ điểm này <span>@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span>
                </a>
            </div>
        </article>

        <aside class="panel article-outline">
            <b>Trong bài này</b>
            @if(isset($rules) && $rules->count() > 0)
                @foreach($rules as $rule)
                    <a href="#rule-{{ $rule->id }}">{{ Str::limit($rule->title, 28) }}</a>
                @endforeach
            @else
                <a class="is-active" href="#">Nội dung chính</a>
            @endif

            <hr>

            @if(isset($related) && $related->count() > 0)
                <b>Mục liên quan</b>
                @foreach($related as $rel)
                    <a href="{{ route('lookup.entry', $rel->slug) }}" style="color: #245de1; font-weight: 500;">
                        {{ $rel->canonical_text }}
                    </a>
                @endforeach
                <hr>
            @endif

            <a class="save-inline" href="{{ route('grammar.index') }}">
                @include('pages.dictionary.icon', ['name' => 'arrow-left']) Về thư viện ngữ pháp
            </a>
        </aside>
    </div>
@else
    {{-- Fallback demo screen --}}
    <div class="article-layout">
        <article class="panel learning-article">
            <div class="article-meta">
                <span class="badge badge-blue">THÌ · B1</span>
                <span>12 phút đọc</span>
                <a href="{{ route('grammar.index') }}" style="color: #617390; text-decoration: none; font-size: 11px;">← Danh sách chủ điểm</a>
            </div>
            <h2>Present Perfect<br><span>Thì hiện tại hoàn thành</span></h2>
            <p class="article-lead">Thì hiện tại hoàn thành diễn tả một hành động đã xảy ra trong quá khứ nhưng vẫn còn liên quan đến hiện tại.</p>
            <div class="article-callout">
                <b>Cấu trúc</b>
                <p><strong>Khẳng định:</strong> S + have / has + V3</p>
                <p><strong>Phủ định:</strong> S + have / has + not + V3</p>
                <p><strong>Nghi vấn:</strong> Have / Has + S + V3?</p>
            </div>
            <h3>Khi nào sử dụng?</h3>
            <ol class="lesson-points">
                <li><b>Trải nghiệm đã có</b><p>I have visited Singapore twice. <span>Tôi đã đến Singapore hai lần.</span></p></li>
                <li><b>Hành động vừa hoàn thành</b><p>She has just finished her assignment. <span>Cô ấy vừa hoàn thành bài tập.</span></p></li>
                <li><b>Trạng thái bắt đầu trong quá khứ và còn tiếp tục</b><p>We have lived here since 2020. <span>Chúng tôi sống ở đây từ năm 2020.</span></p></li>
            </ol>
            <div class="article-callout soft">
                <b>Dấu hiệu thường gặp</b>
                <p>already · yet · just · ever · never · since · for · recently</p>
            </div>
            <a class="button button-primary" href="{{ route('demo.page', 'skill-list') }}">Luyện tập chủ điểm này <span>@include('pages.dictionary.icon', ['name' => 'arrow-right'])</span></a>
        </article>
        <aside class="panel article-outline">
            <b>Trong bài này</b>
            <a class="is-active" href="#">Cấu trúc</a>
            <a href="#">Cách dùng</a>
            <a href="#">Dấu hiệu nhận biết</a>
            <hr>
            <b>Tiến độ đọc</b>
            <div class="progress-track"><i style="width:30%"></i></div>
            <small>Khoảng 4 phút còn lại</small>
            <a class="save-inline" href="{{ route('grammar.index') }}">← Về thư viện ngữ pháp</a>
        </aside>
    </div>
@endif
