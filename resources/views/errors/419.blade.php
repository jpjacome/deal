@extends('layouts.app', ['title' => 'La página expiró', 'noindex' => true])

@section('content')
    <div class="container">
        <section class="tile tile-feature gradient">
            <span class="supergraphic supergraphic-wide" aria-hidden="true"><x-logo /></span>
            <p class="eyebrow">Sesión expirada</p>
            <h1>La página estuvo inactiva demasiado tiempo</h1>
            <p>Por seguridad tienes que volver a cargarla. No se guardó ningún cambio.</p>
            <div class="hero-actions"><a href="{{ url()->previous() }}" class="btn btn-light">Volver a intentar</a></div>
        </section>
    </div>
@endsection
