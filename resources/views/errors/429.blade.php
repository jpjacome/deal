@extends('layouts.app', ['title' => 'Demasiados intentos', 'noindex' => true])

@section('content')
    <div class="container">
        <section class="tile tile-feature gradient">
            <span class="supergraphic supergraphic-wide" aria-hidden="true"><x-logo /></span>
            <p class="eyebrow">Un momento</p>
            <h1>Demasiados intentos seguidos</h1>
            <p>Espera un momento y vuelve a intentarlo.</p>
            <div class="hero-actions"><a href="{{ route('home') }}" class="btn btn-light">Ir al inicio</a></div>
        </section>
    </div>
@endsection
