@extends('layouts.app', ['title' => 'Cambiar reserva', 'noindex' => true])

@section('content')
    <section class="container section">
        <p><a href="{{ route('bookings.show', $booking->manage_token) }}">← Volver a mi reserva</a></p>
        <h1>Cambiar horario</h1>
        <p class="muted">
            {{ $booking->space->name }} · reserva {{ $booking->code }}.
            Horario actual: {{ $booking->scheduleLabel() }}. Tu horario actual se mantiene hasta que confirmes el nuevo.
        </p>

        <livewire:reschedule-form :token="$booking->manage_token" />
    </section>
@endsection
