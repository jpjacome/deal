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
                <a href="#">Planes</a>
                <a href="#">Galería</a>
                <a href="#">Contacto</a>
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
            <span>&copy; {{ date('Y') }} {{ config('app.name') }} · Cuenca, Ecuador</span>
        </div>
    </footer>
</body>
</html>
