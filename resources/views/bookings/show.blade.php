@extends('layouts.app', ['title' => 'Mi reserva', 'noindex' => true])

@section('content')
    <section class="container section">
        @if (session('booked'))
            <div class="notice notice-success">
                @if ($booking->status === App\Enums\BookingStatus::Pending)
                    <strong>¡Recibimos tu solicitud!</strong> Te avisaremos por correo cuando confirmemos tu reserva.
                @else
                    <strong>¡Tu reserva está confirmada!</strong> Te enviamos los detalles por correo.
                @endif
                Guarda este enlace para ver o cancelar tu reserva.
            </div>
        @endif

        @if (session('cancelled'))
            <div class="notice">Tu reserva fue cancelada.</div>
        @endif

        <h1>Reserva {{ $booking->code }}</h1>

        <div class="card">
            <p><span class="badge">{{ $booking->status->getLabel() }}</span></p>
            <dl class="details">
                <dt>Espacio</dt>
                <dd>{{ $booking->space->name }}</dd>
                <dt>Fecha</dt>
                <dd>{{ ucfirst($booking->starts_at->translatedFormat('l j \d\e F \d\e Y')) }}</dd>
                <dt>Horario</dt>
                <dd>{{ $booking->starts_at->format('H:i') }} – {{ $booking->ends_at->format('H:i') }}</dd>
                @if ($booking->attendees > 1)
                    <dt>Personas</dt>
                    <dd>{{ $booking->attendees }}</dd>
                @endif
                <dt>A nombre de</dt>
                <dd>{{ $booking->customer->name }}</dd>
                @if ($booking->status_reason)
                    <dt>Nota</dt>
                    <dd>{{ $booking->status_reason }}</dd>
                @endif
            </dl>

            @if (in_array($booking->status, App\Enums\BookingStatus::holdingSlot(), true))
                <p><a href="{{ $calendarUrl }}" target="_blank" rel="noopener">Agregar a Google Calendar</a></p>
            @endif

            @if ($booking->canBeCancelledByCustomer())
                <form method="POST" action="{{ route('bookings.cancel', $booking->manage_token) }}" onsubmit="return confirm('¿Seguro que quieres cancelar esta reserva?')">
                    @csrf
                    <button type="submit" class="btn-secondary">Cancelar reserva</button>
                </form>
            @elseif (in_array($booking->status, App\Enums\BookingStatus::holdingSlot(), true))
                <p class="muted">Para cambios o cancelaciones con menos de {{ $booking->space->cancellation_hours }} horas de anticipación, escríbenos por WhatsApp.</p>
            @endif
        </div>
    </section>
@endsection
