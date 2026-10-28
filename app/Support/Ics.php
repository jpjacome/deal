<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * iCalendar (.ics) output: one booking for customers, or a feed of all bookings for staff calendars.
 */
class Ics
{
    public static function booking(Booking $booking): string
    {
        return self::calendar([self::event($booking, forStaff: false)], method: 'PUBLISH');
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    public static function feed(Collection $bookings): string
    {
        return self::calendar(
            $bookings->map(fn (Booking $booking) => self::event($booking, forStaff: true))->all(),
            name: config('app.name').' · Reservas',
        );
    }

    /**
     * @param  list<list<string>>  $events
     */
    private static function calendar(array $events, ?string $method = null, ?string $name = null): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Deal//Reservas//ES',
            'CALSCALE:GREGORIAN',
        ];

        if ($method) {
            $lines[] = "METHOD:{$method}";
        }

        if ($name) {
            $lines[] = 'X-WR-CALNAME:'.self::escape($name);
            $lines[] = 'X-WR-TIMEZONE:'.config('app.timezone');
            $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT15M';
        }

        foreach ($events as $event) {
            array_push($lines, ...$event);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    /**
     * @return list<string>
     */
    private static function event(Booking $booking, bool $forStaff): array
    {
        $summary = $forStaff
            ? "{$booking->space->name} · {$booking->customer->name}"
            : "{$booking->space->name} · ".config('app.name');

        $description = $forStaff
            ? "Reserva {$booking->code} ({$booking->status->getLabel()})\nPersonas: {$booking->attendees}\nTel: {$booking->customer->phone}"
            : "Reserva {$booking->code}\nVer o cancelar: {$booking->manageUrl()}";

        return [
            'BEGIN:VEVENT',
            "UID:booking-{$booking->id}@".parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$booking->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.self::escape($summary),
            'DESCRIPTION:'.self::escape($description),
            'LOCATION:'.self::escape((string) Setting::get('venue_address')),
            'STATUS:'.match ($booking->status) {
                BookingStatus::Pending => 'TENTATIVE',
                BookingStatus::Cancelled, BookingStatus::Rejected, BookingStatus::Expired => 'CANCELLED',
                default => 'CONFIRMED',
            },
            'END:VEVENT',
        ];
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /**
     * Fold lines longer than 75 octets (RFC 5545).
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        while (strlen($line) > 75) {
            $cut = 75;
            // Don't split a multibyte UTF-8 character.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $folded .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $folded.$line;
    }
}
