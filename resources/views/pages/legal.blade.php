@extends('layouts.app', ['title' => $title])

@section('content')
    <article class="container section prose">
        <h1>{{ $title }}</h1>
        {!! $body !!}
    </article>
@endsection
