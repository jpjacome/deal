@extends('layouts.app', ['title' => 'Demasiados intentos', 'noindex' => true])

@section('content')
    <section class="container section">
        <h1>Demasiados intentos seguidos</h1>
        <p class="muted">Espera un momento y vuelve a intentarlo.</p>
        <p><a href="{{ route('home') }}" class="btn">Ir al inicio</a></p>
    </section>
@endsection
