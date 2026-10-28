@extends('layouts.app', ['title' => 'Mi reserva', 'noindex' => true])

@section('content')
    @php($pending = $booking->status === App\Enums\BookingStatus::Pending)
    <div class="container">
        @if (session('booked'))
            <div class="notice">
                @if ($pending)
                    <strong>¡Recibimos tu solicitud!</strong> Te avisaremos por correo cuando la confirmemos.
                @else
                    <strong>¡Tu reserva está confirmada!</strong> Te enviamos los detalles por correo.
                @endif
                Guarda esta página: desde aquí puedes ver, cambiar o cancelar tu reserva.
            </div>
        @endif
        @if (session('cancelled'))
            <div class="notice">Tu reserva fue cancelada.</div>
        @endif
        @if (session('rescheduled'))
            <div class="notice">Listo, actualizamos el horario{{ $pending ? '. Queda pendiente hasta que lo confirmemos' : '' }}. Te enviamos los nuevos datos por correo.</div>
        @endif

        <section class="bento">
            <div class="tile gradient booking-hero span-12">
                <span class="supergraphic supergraphic-wide" aria-hidden="true"><x-logo /></span>
                <span class="badge">{{ $booking->status->getLabel() }}</span>
                <p class="eyebrow">Reserva {{ $booking->code }}</p>
                <h1>{{ $booking->space->name }}</h1>
                <p class="booking-hero-when">{{ $booking->scheduleLabel() }}</p>
            </div>

            <div class="tile span-7">
                <p class="eyebrow">Detalles</p>
                <dl class="details">
                    <dt>Fecha</dt>
                    <dd>{{ ucfirst($booking->starts_at->translatedFormat('l j \d\e F \d\e Y')) }}</dd>
                    <dt>Horario</dt>
                    <dd>{{ $booking->starts_at->format('H:i') }} – {{ $booking->ends_at->format('H:i') }}</dd>
                    @if ($booking->attendees > 1)
                        <dt>Personas</dt>
                        <dd>{{ $booking->attendees }}</dd>
                    @endif
                    @if (($price = $booking->estimatedPrice()) !== null)
                        <dt>Total estimado</dt>
                        <dd>${{ number_format($price, 2) }} <span class="muted">· se paga en el local</span></dd>
                    @endif
                    <dt>A nombre de</dt>
                    <dd>{{ $booking->customer->name }}</dd>
                    @if ($booking->status_reason && ! str_starts_with($booking->status_reason, 'Cancelada por el cliente'))
                        <dt>Nota</dt>
                        <dd>{{ $booking->status_reason }}</dd>
                    @endif
                </dl>

                <div class="actions">
                    @if ($booking->canBeRescheduledByCustomer())
                        <a href="{{ route('bookings.reschedule', $booking->manage_token) }}" class="btn">Cambiar horario</a>
                    @endif
                    @if ($booking->canBeCancelledByCustomer())
                        <form method="POST" action="{{ route('bookings.cancel', $booking->manage_token) }}" onsubmit="return confirm('¿Seguro que quieres cancelar esta reserva?')">
                            @csrf
                            <button type="submit" class="btn-secondary">Cancelar reserva</button>
                        </form>
                    @endif
                    @if (! $isActive)
                        <a href="{{ route('bookings.create', ['espacio' => $booking->space->slug]) }}" class="btn">Reservar de nuevo</a>
                    @endif
                </div>

                @if ($isActive && ! $booking->canBeCancelledByCustomer())
                    <p class="muted">Para cambios o cancelaciones con menos de {{ $booking->space->cancellation_hours }} horas de anticipación, escríbenos por WhatsApp.</p>
                @endif
            </div>

            @if ($isActive)
                <div class="tile span-5">
                    <p class="eyebrow">Cómo llegar</p>
                    <p><strong>{{ $settings['venue_address'] }}</strong></p>
                    @if ($settings['maps_url'])
                        <p><a href="{{ $settings['maps_url'] }}" target="_blank" rel="noopener">Abrir en Google Maps →</a></p>
                    @endif
                    @if ($settings['arrival_info'])
                        <p class="muted" style="white-space: pre-line;">{{ $settings['arrival_info'] }}</p>
                    @endif
                    <p class="calendar-links">
                        Agregar a <a href="{{ $calendarUrl }}" target="_blank" rel="noopener">Google Calendar</a> ·
                        <a href="{{ route('bookings.calendar', $booking->manage_token) }}">Apple / Outlook</a>
                    </p>
                    @if ($whatsappUrl)
                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-secondary">Escríbenos por WhatsApp</a>
                    @endif
                </div>
            @endif

            @if ($checkInQr)
                <div class="tile tile-dark checkin span-12">
                    <p class="eyebrow">Al llegar</p>
                    <p>Muestra este código en recepción.</p>
                    <div class="qr">{!! $checkInQr !!}</div>
                    <p class="checkin-code">{{ $booking->code }}</p>
                </div>
            @endif
        </section>
    </div>
@endsection
