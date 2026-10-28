@extends('layouts.app')

@section('content')
    <section class="hero gradient">
        <p class="eyebrow">Meet Hub</p>
        <h1>Tu espacio de trabajo en Cuenca</h1>
        <p>Contenido provisional. El diseño final se aplica en la fase de diseño.</p>
        <a href="{{ route('bookings.create') }}" class="btn">Reservar un espacio</a>
    </section>

    @if ($freeToday->isNotEmpty())
        <section class="container section">
            <h2>Libre hoy</h2>
            <div class="free-now">
                @foreach ($freeToday as $row)
                    <a class="card" href="{{ route('bookings.create', ['espacio' => $row['space']->slug, 'fecha' => today()->toDateString(), 'hora' => $row['start']->format('H:i')]) }}">
                        <strong>{{ $row['space']->name }}</strong><br>
                        <span class="muted">Desde las {{ $row['start']->format('H:i') }} · reservar en 2 toques →</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="container section" id="espacios">
        <h2>Espacios</h2>
        <div class="space-grid">
            @foreach ($spaces as $space)
                <div class="space-card">
                    @if ($space->photoUrl())
                        <img src="{{ $space->photoUrl() }}" alt="{{ $space->name }}" loading="lazy">
                    @endif
                    <div class="space-card-body">
                        <h3>{{ $space->name }}</h3>
                        <p class="muted">{{ $space->booking_mode->getLabel() }} · hasta {{ $space->capacity }} {{ $space->capacity === 1 ? 'persona' : 'personas' }}</p>
                        @if ($space->price !== null)
                            <p>${{ rtrim(rtrim(number_format((float) $space->price, 2), '0'), '.') }} {{ $space->priceUnitLabel() }}</p>
                        @elseif ($space->price_label)
                            <p>{{ $space->price_label }}</p>
                        @endif
                        <a href="{{ route('bookings.create', ['espacio' => $space->slug]) }}" class="btn">Reservar</a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
