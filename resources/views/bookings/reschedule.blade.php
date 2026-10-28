@extends('layouts.app', ['title' => 'Cambiar reserva', 'noindex' => true])

@section('content')
    <div class="container">
        <div class="page-intro">
            <p><a href="{{ route('bookings.show', $booking->manage_token) }}">← Volver a mi reserva</a></p>
            <p class="eyebrow">Reserva {{ $booking->code }}</p>
            <h1>Cambiar horario</h1>
            <p class="muted">
                {{ $booking->space->name }} · horario actual: {{ $booking->scheduleLabel() }}.
                Se mantiene hasta que confirmes el nuevo.
            </p>
        </div>

        <livewire:reschedule-form :token="$booking->manage_token" />
    </div>
@endsection
