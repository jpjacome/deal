@extends('layouts.app', ['title' => 'Contacto'])

@section('content')
    <section class="container section">
        <h1>Contacto</h1>

        <div class="choice-grid">
            <div class="card">
                <h2>Dónde estamos</h2>
                <p>{{ $settings['venue_address'] }}</p>
                @if ($settings['maps_url'])
                    <p><a href="{{ $settings['maps_url'] }}" target="_blank" rel="noopener" class="btn">Cómo llegar</a></p>
                @endif
                @if ($settings['arrival_info'])
                    <p class="muted" style="white-space: pre-line;">{{ $settings['arrival_info'] }}</p>
                @endif
            </div>

            <div class="card">
                <h2>Horario</h2>
                @forelse ($hours as $weekday => $ranges)
                    <p><strong>{{ \App\Models\OpeningHour::WEEKDAYS[$weekday] }}:</strong> {{ $ranges }}</p>
                @empty
                    <p class="muted">Horario por confirmar.</p>
                @endforelse
            </div>

            <div class="card">
                <h2>Escríbenos</h2>
                @if ($whatsapp = \App\Models\Setting::whatsappDigits())
                    <p><a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hola, quiero información sobre Deal.') }}" target="_blank" rel="noopener" class="btn">WhatsApp</a></p>
                @endif
                @if ($settings['contact_email'])
                    <p><a href="mailto:{{ $settings['contact_email'] }}">{{ $settings['contact_email'] }}</a></p>
                @endif
                <p class="muted">¿Ya tienes una reserva? <a href="{{ route('bookings.lookup') }}">Recupera tu enlace</a>.</p>
            </div>
        </div>
    </section>
@endsection
