@extends('layouts.app', ['title' => 'La página expiró', 'noindex' => true])

@section('content')
    <section class="container section">
        <h1>La página estuvo inactiva demasiado tiempo</h1>
        <p class="muted">Por seguridad tienes que volver a cargarla. No se guardó ningún cambio.</p>
        <p><a href="{{ url()->previous() }}" class="btn">Volver a intentar</a></p>
    </section>
@endsection
