@extends('layouts.app', ['title' => 'Reservar'])

@section('content')
    <div class="container">
        <div class="page-intro">
            <p class="eyebrow">Reservar</p>
            <h1>Reserva tu espacio</h1>
            <p class="muted">Elige el espacio, el día y el horario. Te confirmamos por correo.</p>
        </div>

        <livewire:booking-form />
    </div>
@endsection
