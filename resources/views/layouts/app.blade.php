<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @if ($noindex ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="#4a0e14">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div>
                <a href="{{ route('home') }}" class="logo" aria-label="{{ config('app.name') }} — inicio"><x-logo /></a>
                <nav>
                    <a href="{{ route('home') }}#espacios">Espacios</a>
                    <a href="{{ route('contact') }}">Contacto</a>
                    <a href="{{ route('bookings.lookup') }}">Mi reserva</a>
                    <a href="{{ route('bookings.create') }}" class="btn">Reservar</a>
                </nav>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="tile tile-dark">
                <span class="supergraphic supergraphic-footer" aria-hidden="true"><x-logo /></span>
                <div class="footer-cols">
                    <div>
                        <p class="eyebrow">Meet Hub</p>
                        <p>{{ \App\Models\Setting::get('venue_address') }}</p>
                    </div>
                    <div>
                        <p><a href="{{ route('bookings.create') }}">Reservar</a></p>
                        <p><a href="{{ route('contact') }}">Contacto</a></p>
                        <p><a href="{{ route('bookings.lookup') }}">Mi reserva</a></p>
                    </div>
                </div>
                <div class="footer-legal">
                    &copy; {{ date('Y') }} {{ config('app.name') }} ·
                    <a href="{{ route('privacy') }}">Privacidad</a> ·
                    <a href="{{ route('terms') }}">Condiciones</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
