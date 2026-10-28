@extends('layouts.app', ['title' => 'Página no encontrada', 'noindex' => true])

@section('content')
    <div class="container">
        <section class="tile tile-feature gradient">
            <span class="supergraphic supergraphic-wide" aria-hidden="true"><x-logo /></span>
            <p class="eyebrow">Error 404</p>
            <h1>No encontramos esta página</h1>
            <p>Puede que el enlace esté incompleto o que la página ya no exista.</p>
            <div class="hero-actions">
                <a href="{{ route('home') }}" class="btn btn-light">Ir al inicio</a>
                <a href="{{ route('bookings.create') }}" class="btn btn-ghost-light">Reservar un espacio</a>
            </div>
            <p>¿Buscas tu reserva? <a href="{{ route('bookings.lookup') }}">Te enviamos el enlace por correo</a>.</p>
        </section>
    </div>
@endsection
