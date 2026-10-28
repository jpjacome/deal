@extends('layouts.app')

@section('content')
    <div class="container">
        <section class="bento">
            <div class="tile gradient hero span-8">
                <span class="supergraphic supergraphic-hero" aria-hidden="true"><x-logo /></span>
                <div class="hero-copy">
                    <p class="eyebrow">Meet Hub · Cuenca</p>
                    <h1>Tu espacio para trabajar, reunirte y crear.</h1>
                    <p>Salas, oficinas y puestos flexibles. Elige el día, el horario, y listo: sin llamadas ni mensajes.</p>
                    <div class="hero-actions">
                        <a href="{{ route('bookings.create') }}" class="btn btn-light">Reservar ahora</a>
                        <a href="#espacios" class="btn btn-ghost-light">Ver espacios</a>
                    </div>
                </div>
            </div>

            <div class="side-tiles span-4">
                @if ($freeToday->isNotEmpty())
                    <div class="tile">
                        <p class="eyebrow">Libre hoy</p>
                        <ul class="free-list">
                            @foreach ($freeToday as $row)
                                <li>
                                    <a href="{{ route('bookings.create', ['espacio' => $row['space']->slug, 'fecha' => today()->toDateString(), 'hora' => $row['start']->format('H:i')]) }}">
                                        <strong>{{ $row['space']->name }}</strong>
                                        <span>desde {{ $row['start']->format('H:i') }} →</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="tile tile-dark">
                    <p class="eyebrow">Reserva en un minuto</p>
                    <ol class="steps-mini">
                        <li>Elige tu espacio</li>
                        <li>Elige el día y el horario</li>
                        <li>Recibe la confirmación por correo</li>
                    </ol>
                </div>

                <div class="tile">
                    <p class="eyebrow">Visítanos</p>
                    <p><strong>{{ \App\Models\Setting::get('venue_address') }}</strong></p>
                    <a href="{{ route('contact') }}">Horario y cómo llegar →</a>
                </div>
            </div>
        </section>

        <section class="section" id="espacios">
            <div class="section-head">
                <div>
                    <p class="eyebrow">Espacios</p>
                    <h2>Encuentra el tuyo</h2>
                </div>
                <a href="{{ route('bookings.create') }}" class="btn btn-secondary">Ver disponibilidad</a>
            </div>
            <div class="space-grid">
                @foreach ($spaces as $space)
                    <div class="space-card">
                        <x-space-card :space="$space" :link="route('bookings.create', ['espacio' => $space->slug])" />
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
