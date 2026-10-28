<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="60">
    <title>Hoy en {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="screen gradient">
    <span class="supergraphic supergraphic-footer supergraphic-soft" aria-hidden="true"><x-logo /></span>
    <header class="screen-header">
        <span class="logo"><x-logo /></span>
        <div class="screen-clock">
            <strong>{{ now()->format('H:i') }}</strong>
            <span>{{ ucfirst(now()->translatedFormat('l j \d\e F')) }}</span>
        </div>
    </header>

    <main class="screen-grid">
        @foreach ($spaces as $row)
            <section class="screen-card">
                <p class="eyebrow">{{ $row['space']->type_label ?: 'Espacio' }}</p>
                <h2>{{ $row['space']->name }}</h2>
                @if ($row['current'])
                    <p class="screen-state is-busy">Ocupado hasta las {{ $row['current']->ends_at->format('H:i') }}</p>
                @else
                    <p class="screen-state is-free">Libre ahora</p>
                @endif

                @if ($row['upcoming']->isNotEmpty())
                    <ul>
                        @foreach ($row['upcoming']->take(4) as $booking)
                            <li><strong>{{ $booking->starts_at->format('H:i') }}–{{ $booking->ends_at->format('H:i') }}</strong> {{ $booking->customer->company ?: $booking->customer->name }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="screen-muted">Sin más reservas hoy</p>
                @endif
            </section>
        @endforeach
    </main>
</body>
</html>
