@extends('layouts.app', ['title' => 'Recuperar mis reservas', 'noindex' => true])

@section('content')
    <section class="container section" style="max-width: 560px;">
        <h1>¿Perdiste el enlace de tu reserva?</h1>

        @if (session('sent'))
            <div class="notice">Si tienes reservas próximas con ese correo, te acabamos de enviar los enlaces. Revisa también la carpeta de spam.</div>
        @endif

        <p class="muted">Escribe el correo con el que reservaste y te enviaremos los enlaces para ver, cambiar o cancelar tus reservas.</p>

        <form method="POST" action="{{ route('bookings.lookup.send') }}">
            @csrf
            <label class="field">
                <span>Correo electrónico</span>
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email') <small class="error">{{ $message }}</small> @enderror
            </label>
            <button type="submit">Enviarme los enlaces</button>
        </form>
    </section>
@endsection
