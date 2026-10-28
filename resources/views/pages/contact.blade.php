@extends('layouts.app', ['title' => 'Contacto'])

@section('content')
    <div class="container">
        <div class="page-intro">
            <p class="eyebrow">Contacto</p>
            <h1>Ven a conocernos</h1>
        </div>

        <section class="bento">
            <div class="tile tile-feature gradient span-7">
                <span class="supergraphic supergraphic-wide" aria-hidden="true"><x-logo /></span>
                <p class="eyebrow">Dónde estamos</p>
                <h2>{{ $settings['venue_address'] }}</h2>
                @if ($settings['maps_url'])
                    <a href="{{ $settings['maps_url'] }}" target="_blank" rel="noopener" class="btn btn-light">Cómo llegar</a>
                @endif
            </div>

            <div class="tile span-5">
                <p class="eyebrow">Horario</p>
                @forelse ($hours as $weekday => $ranges)
                    <p style="display: flex; justify-content: space-between; gap: 12px; margin: 0 0 .5em;">
                        <span>{{ \App\Models\OpeningHour::WEEKDAYS[$weekday] }}</span>
                        <strong>{{ $ranges }}</strong>
                    </p>
                @empty
                    <p class="muted">Horario por confirmar.</p>
                @endforelse
            </div>

            <div class="tile tile-dark span-6">
                <p class="eyebrow">Escríbenos</p>
                @if ($whatsapp = \App\Models\Setting::whatsappDigits())
                    <p><a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hola, quiero información sobre Deal.') }}" target="_blank" rel="noopener" class="btn btn-light">WhatsApp</a></p>
                @endif
                @if ($settings['contact_email'])
                    <p><a href="mailto:{{ $settings['contact_email'] }}">{{ $settings['contact_email'] }}</a></p>
                @endif
                <p>¿Ya tienes una reserva? <a href="{{ route('bookings.lookup') }}">Recupera tu enlace</a>.</p>
            </div>

            @if ($settings['arrival_info'])
                <div class="tile span-6">
                    <p class="eyebrow">Al llegar</p>
                    <p style="white-space: pre-line; margin: 0;">{{ $settings['arrival_info'] }}</p>
                </div>
            @endif
        </section>
    </div>
@endsection
