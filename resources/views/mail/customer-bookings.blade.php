<x-mail::message>
# Tus próximas reservas

Hola {{ $name }}, estos son los enlaces para ver, cambiar o cancelar tus reservas:

<x-mail::table>
| Reserva | Espacio | Horario |
|:--------|:--------|:--------|
@foreach ($bookings as $booking)
| [{{ $booking->code }}]({{ $booking->manageUrl() }}) | {{ $booking->space->name }} | {{ $booking->scheduleLabel() }} |
@endforeach
</x-mail::table>

Si no pediste este correo, puedes ignorarlo.
</x-mail::message>
