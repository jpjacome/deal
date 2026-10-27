<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @if ($noindex ?? false)
        <meta name="robots" content="noindex, nofollow">
    @endif
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a href="{{ route('home') }}" class="logo">{{ config('app.name') }}</a>
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

    <footer class="site-footer">
        <div class="container">
            &copy; {{ date('Y') }} {{ config('app.name') }} · Cuenca, Ecuador
        </div>
    </footer>
</body>
</html>
