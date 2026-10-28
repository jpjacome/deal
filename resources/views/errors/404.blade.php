@extends('layouts.app', ['title' => 'Página no encontrada', 'noindex' => true])

@section('content')
    <section class="container section">
        <h1>No encontramos esta página</h1>
        <p class="muted">Puede que el enlace esté incompleto o que la página ya no exista.</p>
        <p>
            <a href="{{ route('home') }}" class="btn">Ir al inicio</a>
            <a href="{{ route('bookings.create') }}" class="btn btn-secondary">Reservar un espacio</a>
        </p>
        <p class="muted">¿Buscas tu reserva? <a href="{{ route('bookings.lookup') }}">Te enviamos el enlace por correo</a>.</p>
    </section>
@endsection
