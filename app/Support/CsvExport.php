<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV downloads for Excel in Spanish locale: ";" separator and a UTF-8 BOM so accents display correctly.
 */
class CsvExport
{
    public static function bookings(CarbonImmutable $from, CarbonImmutable $to): StreamedResponse
    {
        $rows = Booking::with(['space', 'customer'])
            ->whereBetween('starts_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('starts_at')
            ->lazy()
            ->map(fn (Booking $b) => [
                $b->code,
                $b->starts_at->format('Y-m-d'),
                $b->starts_at->format('H:i'),
                $b->ends_at->format('H:i'),
                $b->space->name,
                $b->attendees,
                $b->status->getLabel(),
                $b->source->getLabel(),
                $b->customer->name,
                $b->customer->email,
                $b->customer->phone,
                $b->customer->company,
                $b->estimatedPrice(),
                $b->notes,
                $b->created_at->format('Y-m-d H:i'),
            ]);

        return self::download(
            "reservas-{$from->toDateString()}-{$to->toDateString()}.csv",
            ['Código', 'Fecha', 'Desde', 'Hasta', 'Espacio', 'Personas', 'Estado', 'Origen', 'Cliente', 'Correo', 'Celular', 'Empresa', 'Total estimado', 'Comentarios', 'Creada'],
            $rows,
        );
    }

    public static function customers(): StreamedResponse
    {
        $rows = Customer::withCount('bookings')
            ->orderBy('name')
            ->lazy()
            ->map(fn (Customer $c) => [
                $c->name,
                $c->email,
                $c->phone,
                $c->company,
                $c->whatsapp_opt_in ? 'Sí' : 'No',
                $c->bookings_count,
                $c->no_show_count,
                implode(', ', $c->tags ?? []),
                $c->created_at->format('Y-m-d'),
            ]);

        return self::download(
            'clientes-'.today()->toDateString().'.csv',
            ['Nombre', 'Correo', 'Celular', 'Empresa', 'WhatsApp', 'Reservas', 'Inasistencias', 'Etiquetas', 'Cliente desde'],
            $rows,
        );
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    private static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');

            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
