<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @if ($noindex ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="#9b1c19">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="{{ route('home') }}" class="logo" aria-label="{{ config('app.name') }} — inicio"><x-logo /></a>
            <nav>
                <a href="{{ route('home') }}#espacios">Espacios</a>
                <a href="{{ route('contact') }}">Contacto</a>
                <a href="{{ route('bookings.lookup') }}">Mi reserva</a>
                <a href="{{ route('bookings.create') }}" class="btn">Reservar</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer gradient">
        <div class="container">
            <a href="{{ route('home') }}" class="logo" aria-label="{{ config('app.name') }}"><x-logo /></a>
            <span>
                &copy; {{ date('Y') }} {{ config('app.name') }} · {{ \App\Models\Setting::get('venue_address') }}<br>
                <a href="{{ route('privacy') }}">Privacidad</a> · <a href="{{ route('terms') }}">Condiciones</a> · <a href="{{ route('contact') }}">Contacto</a>
            </span>
        </div>
    </footer>
</body>
</html>
