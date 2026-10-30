@extends('layouts.app')
@section('content')
<main class="assessment-page"><a class="back-link" href="{{ route('exams.index') }}">Kho đề thi</a><h1>{{ $result['title'] }}</h1><div class="panel"><span class="badge badge-mint">Estimated</span><h2>{{ $result['grading_status']==='pending' ? 'Đang chờ chấm Writing / Speaking' : 'Đã chấm bài' }}</h2><p>Điểm thô: {{ $result['raw_score'] }} · Điểm quy đổi: {{ $result['scaled_score'] ?? 'Chưa có' }}</p><p>Đúng: {{ $result['details']['correct']??0 }} · Sai: {{ $result['details']['wrong']??0 }} · Bỏ trống: {{ $result['details']['unanswered']??0 }}</p><p>Thời gian: {{ $result['details']['duration_seconds']??0 }} giây</p>
@foreach($result['details']['bands']??[] as $skill=>$band)<p>{{ ucfirst($skill) }}: {{ $band }}</p>@endforeach</div>
@foreach($result['feedback'] as $feedback)<article class="panel"><h3>{{ $feedback['item_key'] }}</h3><p>{{ $feedback['grade']['feedback']??'' }}</p>@foreach($feedback['grade']['criteria']??[] as $name=>$value)<p>{{ $name }}: {{ $value }}</p>@endforeach</article>@endforeach
@foreach($result['review'] as $key=>$review)<details class="panel"><summary>{{ $key }} · Đáp án và lời giải</summary><pre>{{ json_encode(array_diff_key($review,['explanation'=>true,'model_answer'=>true,'transcripts'=>true]),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>@if(isset($review['explanation']))<div>{!! $review['explanation'] !!}</div>@endif
@if(isset($review['model_answer']))<p>{{ $review['model_answer'] }}</p>@endif
@foreach($review['transcripts']??[] as $transcript)<p>{{ $transcript }}</p>@endforeach</details>@endforeach
</main>
@endsection
