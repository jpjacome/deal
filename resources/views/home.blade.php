@extends('layouts.app')

@section('content')
    <section class="hero gradient">
        <p class="eyebrow">Meet Hub</p>
        <h1>Tu espacio de trabajo en Cuenca</h1>
        <p>Contenido provisional. El diseño final se aplica en la fase de diseño.</p>
        <a href="{{ route('bookings.create') }}" class="btn">Reservar un espacio</a>
    </section>

    <section class="container section" id="espacios">
        <h2>Espacios</h2>
        <div class="choice-grid">
            @foreach ($spaces as $space)
                <div class="card">
                    <h3>{{ $space->name }}</h3>
                    <p class="muted">{{ $space->booking_mode->getLabel() }} · hasta {{ $space->capacity }} {{ $space->capacity === 1 ? 'persona' : 'personas' }}</p>
                    @if ($space->price_label)
                        <p>{{ $space->price_label }}</p>
                    @endif
                    <a href="{{ route('bookings.create', ['espacio' => $space->slug]) }}" class="btn">Reservar</a>
                </div>
            @endforeach
        </div>
    </section>
@endsection
