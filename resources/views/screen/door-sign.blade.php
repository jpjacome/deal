<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $space->name }} · Reservar</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="door-sign">
    <main>
        <span class="logo"><x-logo /></span>
        <h1>{{ $space->name }}</h1>
        <p>Escanea para reservar este espacio</p>
        <div class="qr">{!! $qr !!}</div>
        <p class="muted">{{ preg_replace('#^https?://#', '', $url) }}</p>
        <button type="button" class="print-button" onclick="window.print()">Imprimir</button>
    </main>
</body>
</html>
