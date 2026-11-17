# Deal — platform context

The current, complete picture of what is built. `CLAUDE.md` holds the working rules; `PLAN.md` is the original spec (kept for history, partly superseded — where they differ, this file and the code win). Keep this file updated when features change.

## 1. Product

**Deal** is a coworking space in Cuenca, Ecuador. This platform is their public website plus an online reservation system with an admin panel. Built by Dr Pixel (Juan Pablo Jácome).

- **Public site** (Spanish only): Inicio, Espacios/Reservar, Contacto, Mis reservas, Privacidad, Condiciones.
- **Reservations**: visitors book a space without an account; they get email (and opt-in WhatsApp) confirmations and a private link to manage the booking.
- **Admin** (`/admin`, Filament): bookings, agenda, spaces, hours, closures, customers, waitlist, reports, settings, users.
- **Out of scope**: online payments (prices are shown as an estimate, "se paga en el local"), customer accounts/memberships, other languages, native app (the JSON API in §9 is the "base preparada" for one).

**Scope vs. the signed quote ($600):** the quote covers pages, online reservations with email confirmation, a private panel, CMS, a WhatsApp *button* and hosting/SSL. WhatsApp **notifications** (Cloud API), the waitlist, the reception screen, reports and the automation layer go beyond it — position or price them separately with the client.

## 2. Stack

| | |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Admin | Filament 5 (Schemas API; resources split into `Schemas/`, `Tables/`, `Pages/`) |
| Public interactivity | Livewire 4 + Blade + Alpine (bundled with Livewire) |
| CSS | Plain CSS, no frameworks: `resources/css/app.css` (public), `resources/css/admin.css` (Filament skin) |
| JS | `resources/js/app.js` (booking form helpers), `resources/js/home.js` (homepage animations) |
| Build | Vite; fonts self-hosted via `laravel-vite-plugin/fonts` (Inter body, Outfit headings) |
| Tests | PHPUnit (`php artisan test --compact`), Pint for formatting |
| Other | `chillerlan/php-qrcode` (QR codes) |

Timezone `America/Guayaquil`, locale `es` (`lang/es.json`, `lang/es/validation.php`).

## 3. Environments

| | Local | Production |
|---|---|---|
| Host | Laravel Herd, `http://deal.test` | Shared hosting (no daemons) |
| DB | SQLite | MySQL |
| Queue | `database` | `database`, drained by the scheduler |
| Cron | — | `* * * * * php artisan schedule:run` |

Everything is env-driven (`.env`); migrations are portable between SQLite and MySQL. Production checklist: see `CLAUDE.md`. Seed demo data with `php artisan db:seed` (venue hours, 4 spaces with stand-in photos).

## 4. Public flows

1. **Homepage** (`/`) — animated, photo-led (Unsplash stand-ins, self-hosted WebP in `public/images/home/`, credits at the bottom). Shows "libre hoy" availability and today's hours from live data. Animations in `home.js` respect `prefers-reduced-motion`.
2. **Book** (`/reservar`, Livewire `BookingForm`) — 4 steps: space → day → time/duration (or full/half day) → contact details. Query params `?espacio=&fecha=&hora=` preselect (used by "libre ahora" and waitlist links). Features: live slot availability, alternatives when a day is full (other spaces/days), "Avísame si se libera" waitlist, estimated price, international phone field (country select + number, normalized to E.164), email typo suggestions, "remember me" (localStorage), honeypot, rate limit 10/hour/IP, required unticked privacy consent (LOPDP), optional WhatsApp opt-in. Errors scroll into view.
3. **Manage booking** (`/reserva/{token}`) — status, details, add to calendar (`.ics`), reschedule (`/reserva/{token}/cambiar`, Livewire `RescheduleForm`) and cancel, both allowed until the space's `cancellation_hours` window.
4. **Mis reservas** (`/mis-reservas`) — enter email → receive a link listing your bookings (no accounts).
5. **Reception screen** (`/pantalla/{token}`) — full-screen "today" board for a TV at the venue; token set/renewed/disabled in Ajustes → Pantalla.
6. **Door sign** (`/espacios/{slug}/letrero`) — printable sign with QR code to book that space.
7. **Contact, privacy, terms** — texts editable in Ajustes (defaults in `resources/views/legal/*-default.blade.php`).
8. Spanish error pages: 404, 419 (expired page; Livewire 419 prompt is also translated), 429.

## 5. Admin (`/admin`)

Login, password reset, profile, optional 2FA (authenticator app + recovery codes), bell notifications (polling 30 s), light and dark theme. Roles: `admin` and `staff` (only admins manage users).

- **Dashboard** — stats (pending highlighted), upcoming bookings.
- **Reservas** — tabs (pending, today, upcoming…), filters, CSV export (`;` + BOM for Excel). View page: details, history (booking events), message log (emails/WhatsApp sent). Actions: confirm, reject (with reason), cancel, check-in, mark no-show, reschedule. Staff can create bookings (incl. recurring series) and override rules (`strict: false`).
- **Agenda** — day timeline per space + week view; personal calendar feed (`/calendario/{token}.ics`) to subscribe from Google/Apple Calendar.
- **Espacios** — name, slug, photo, description, amenities, capacity, booking mode, durations, buffers, lead time, horizon, approval, cancellation window, prices, half-day; per-space opening hours; door-sign action.
- **Horarios** — venue-wide opening hours (per-space hours override them).
- **Cierres** (blocked periods) — holidays/maintenance, venue-wide or per space; action to cancel affected bookings.
- **Clientes** — contact info, notes, tags, no-show count, bookings; anonymize action (LOPDP).
- **Lista de espera** — waitlist entries.
- **Reportes** — bookings received (by `created_at`), visits (by `starts_at`, up to now), upcoming next 30 days, no-show and cancellation rates, average lead time, occupancy per space (past/upcoming), busiest-hours heatmap, top customers (`App\Services\BookingReports`).
- **Ajustes** — tabs: Contacto (address, maps, WhatsApp, email), Avisos (team alert emails/phones, test email), Reglas, Seguimiento, Pantalla, Legal.
- **Usuarios** — admin only.

## 6. Booking engine

Code: `app/Actions/Bookings/*` (`CreateBooking`, `ChangeBookingStatus`, `RescheduleBooking`, `CreateRecurringBookings`) and `app/Services/Availability.php`. Bookings are created inside a transaction with a lock; a taken slot throws `App\Exceptions\BookingUnavailable`.

**Space modes** (`App\Enums\BookingMode`):
- `Hourly` — exclusive; slot grid within opening hours, min/max duration, buffer between bookings.
- `Daily` — exclusive; full day or (if `allows_half_day`) morning/afternoon.
- `Capacity` — shared (e.g. hot desk): many bookings up to `capacity` attendees at once.

**Rules**: opening hours (per space, else venue), blocked periods, lead time (min notice), horizon (max days ahead), buffers, `requires_approval` (pending vs. auto-confirm), cancellation window. Customers at the no-show threshold (setting) can still book, but their bookings always go to Pending for review. Staff bookings start Confirmed.

**Statuses** (`App\Enums\BookingStatus`): `Pending → Confirmed | Rejected | Expired | Cancelled`; `Confirmed → Completed | NoShow | Cancelled`; `Completed → NoShow` (correction). `holdingSlot()` = Pending/Confirmed. Every change is recorded in `booking_events`.

**Sources** (`BookingSource`): `web`, `admin`, `api`. Booking codes look like `DL-XXXXXX`; each booking has a 64-char `manage_token`.

**Waitlist** (`App\Services\Waitlist`): when a slot frees up (cancel/reject/expire), people waiting for that space and day get a "se liberó un espacio" message with a prefilled booking link.

## 7. Notifications

Flow: actions fire events (`BookingCreated`, `BookingStatusChanged`, `BookingRescheduled`; dispatched after commit) → `app/Listeners/SendBookingNotifications.php` → notifications on the queue. `LogMailNotifications` + `NotificationLog` record every message sent (visible on the booking).

| Customer | Team |
|---|---|
| BookingRequested, BookingConfirmed, BookingRejected, BookingCancelled, BookingExpired, BookingRescheduled, BookingReminder, VisitFeedback, MembershipNudge, WaitlistSpotAvailable, CustomerBookingsLink | StaffNewBooking, StaffBookingCancelled, StaffBookingList (daily agenda) + Filament bell notifications |

- **Email**: Markdown mail with a custom `deal` theme (`resources/views/vendor/mail/html/themes/deal.css`), views in `resources/views/mail/`. Includes `.ics` where relevant.
- **WhatsApp**: Meta Cloud API via `App\Notifications\Channels\WhatsAppChannel` + `App\Services\WhatsApp`. Only to customers who opted in. Template names/bodies to submit to Meta (Utility, `es`) live in `app/Notifications/WhatsApp/Templates.php`: `reserva_recibida`, `reserva_confirmada`, `reserva_rechazada`, `reserva_cancelada`, `reserva_expirada`, `reserva_reprogramada`, `recordatorio_reserva` (quick-reply buttons), `espacio_liberado`, `nueva_reserva_admin`, `reserva_cancelada_admin`. Inactive until `WHATSAPP_*` env vars are set.
- **WhatsApp webhook** (`/api/webhooks/whatsapp`): GET verify + POST with `X-Hub-Signature-256` check. Reminder buttons send `CONFIRM:{manage_token}` / `CANCEL:{manage_token}`; the sender's phone must match the customer's.
- **Team recipients**: `App\Support\StaffRecipients` — emails/phones from Ajustes → Avisos.

## 8. Automation (scheduler)

Defined in `routes/console.php`; logic in `app/Services/BookingAutomation.php`.

| Command | When | Does |
|---|---|---|
| `bookings:tick` | every 5 min | expire unreviewed pending requests; remind team about pending ones; remind customers before confirmed visits; close finished bookings (completed, or no-show if check-in grace is set and nobody checked in); post-visit feedback + membership nudge |
| `bookings:daily-agenda` | 07:30 | email today's bookings to the team (skipped if none) |
| `queue:work --stop-when-empty` | every minute | sends queued mail/WhatsApp on shared hosting |

## 9. Routes & API

Public: `/`, `/reservar`, `/reserva/{token}` (+ `/calendario.ics`, `/cambiar`, POST `/cancelar`), `/mis-reservas`, `/contacto`, `/privacidad`, `/condiciones`, `/pantalla/{token}`, `/espacios/{slug}/letrero`, `/calendario/{token}.ics` (staff feed). Admin: `/admin/*`.

API (`routes/api.php`, read-only, versioned): `GET /api/v1/spaces`, `/api/v1/spaces/{slug}`, `/api/v1/spaces/{slug}/availability` (`SpaceResource`). Webhooks: `/api/webhooks/whatsapp`.

## 10. Data model

| Table | Notes |
|---|---|
| `spaces` | booking rules, mode, capacity, durations, buffers, prices (`price`, `half_day_price`), `allows_half_day`, photo, amenities (json), `is_active`, `is_bookable`, `sort` |
| `opening_hours` | `weekday`, `opens_at`, `closes_at`; `space_id` null = venue-wide |
| `blocked_periods` | closures, venue-wide or per space |
| `customers` | name, email (nullable), phone (E.164), company, `whatsapp_opt_in`, `privacy_accepted_at`, `privacy_version`, notes, tags (json), `no_show_count`, `membership_nudged_at` |
| `bookings` | `code`, `manage_token`, space, customer, `starts_at`/`ends_at`, attendees, status, source, `series_id`, notes, timestamps for reminders/attendance/check-in/feedback |
| `booking_events` | status/change history |
| `waitlist_entries` | space, date, contact, `notified_at` |
| `notification_logs` | every email/WhatsApp sent and its result |
| `settings` | key/value (json), cached; see §11 |
| `users` | `role`, 2FA secret/recovery codes, `calendar_token` |
| `notifications`, `jobs`, `cache`, `sessions` | Laravel defaults |

## 11. Settings (`App\Models\Setting`, admin "Ajustes")

`venue_address`, `maps_url`, `whatsapp_number`, `contact_email`, `notify_emails[]`, `notify_whatsapp[]`, `arrival_info`, `pending_expiry_hours` (24), `pending_reminder_hours` (4), `reminder_hours_before` (24), `no_show_threshold` (3), `checkin_grace_minutes` (0 = off), `google_review_url`, `membership_nudge_after` (3), `membership_nudge_text`, `privacy_policy`, `privacy_version`, `booking_terms`, `screen_token`. Defaults in `Setting::DEFAULTS`.

## 12. Brand & UI

Source: `DEAL HUB.pdf` moodboard (private, gitignored; not a brand book yet).

- Colors (only these): `--accent #dd572f` orange, `--primary #4a0e14` dark red (text, buttons), `--light #f5eace` cream, `--surface #e4e2df` gray, `--white #fff`; `--gradient` orange → dark red `in oklch`. **No black anywhere.** Tints via `color-mix()`.
- One `--radius` (28px), fonts Outfit (headings) + Inter (body; the deck uses SF Pro Display).
- Building blocks in `app.css`: `.tile`, `.tile-dark`, `.gradient`, `.bento` + `.span-*`, `.supergraphic`, `.eyebrow`, `.bubble` (speech-bubble motif), `.chip`.
- Logo: `public/images/deal-logo.svg` (`currentColor`, `<x-logo />`), cream and PNG variants for email; `public/favicon.svg`.
- Admin: `admin.css` over Filament `fi-*` classes; custom oklch palettes (gray scale white → gray → dark red, so dark mode is wine; primary cream → orange → dark red).
- Phone fields everywhere (public + admin `App\Filament\Forms\PhoneField`) show/store full international format `+593…` (`App\Support\PhoneNumber`, `App\Rules\ValidPhoneNumber`).

## 13. Key code map

```
app/Actions/Bookings/        booking writes (create, status, reschedule, recurring)
app/Services/                Availability, BookingAutomation, BookingReports, Waitlist, WhatsApp
app/Events, app/Listeners/   booking events → notifications, mail logging
app/Notifications/           mail/WhatsApp notifications, WhatsApp templates + channel
app/Livewire/                BookingForm, RescheduleForm, Concerns/PicksSchedule
app/Http/Controllers/        pages, manage booking, lookup, calendar feed, screen/door sign, API
app/Filament/                resources, pages (Agenda, Reports, Settings), widgets, PhoneField
app/Support/                 PhoneNumber, Ics, QrCode, CsvExport, StaffRecipients
resources/views/             layouts/app, home, bookings/*, pages/*, legal/*, mail/*, screen/*, livewire/*, filament/pages/*
resources/css, resources/js  app.css, admin.css, app.js, home.js
database/seeders/            demo venue hours + 4 spaces
tests/Feature/               Bookings, Notifications, Admin, Api, HomePage
```

## 14. Testing

`php artisan test --compact` — feature tests cover availability rules, booking actions, the public flow (Livewire), notifications and automation, admin pages/tools, API and webhook, and the homepage. Run `vendor/bin/pint --dirty` after PHP changes. Visual checks have been done with Playwright screenshots (not part of the suite).

## 15. Pending / needs the client

- Answers to `PLAN.md` §11 (final rules, prices, hours, approval policy).
- Real photos and copy (homepage uses Unsplash stand-ins; credits shown).
- Legal entity details and a legal review of the privacy policy and terms (LOPDP).
- Meta Business / WhatsApp Cloud API account, phone number and template approval → `WHATSAPP_*` env.
- Mail provider + SPF/DKIM/DMARC for the domain; hosting and domain.
- Agreement on items beyond the quote (see §1).
