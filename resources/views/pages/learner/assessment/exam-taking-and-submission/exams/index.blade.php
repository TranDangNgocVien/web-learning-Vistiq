@extends('layouts.app')

@section('content')
    @php($page = ['title' => 'Kho đề thi', 'section' => 'exams', 'view' => 'pages.learner.assessment.exam-taking-and-submission.exams.catalog-content'])
    @include('layouts.user', ['page' => $page])
@endsection
