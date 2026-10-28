@extends('layouts.app', ['title' => $title])

@section('content')
    <div class="container">
        <div class="page-intro">
            <p class="eyebrow">Legal</p>
            <h1>{{ $title }}</h1>
        </div>
        <article class="tile prose">
            {!! $body !!}
        </article>
    </div>
@endsection
