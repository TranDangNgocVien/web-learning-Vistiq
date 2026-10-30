@extends('layouts.app')

@php
    $page = [
        'title' => 'Bảng xếp hạng · '.$exam->title,
        'section' => 'exams',
        'view' => 'pages.learner.assessment.exam-taking-and-submission.runtime.leaderboard-content',
    ];
@endphp

@section('content')
    @include('layouts.user', ['page' => $page])
@endsection
