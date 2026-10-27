@extends('layouts.app', ['title' => 'Reservar'])

@section('content')
    <section class="container section">
        <h1>Reserva tu espacio</h1>
        <p class="muted">Elige el espacio, la fecha y el horario. Recibirás la confirmación por correo.</p>

        <livewire:booking-form />
    </section>
@endsection
