@php
    $fmt = fn ($t) => $t ? \Illuminate\Support\Str::of($t)->substr(0, 5) : null;
    $names = fn ($day, $type) => $day->assignments->where('type', $type)->map(fn ($a) => $a->user?->name)->filter()->implode(', ');
    $range = $from->year === $until->year
        ? $from->format('j. n.').' – '.$until->format('j. n. Y')
        : $from->format('j. n. Y').' – '.$until->format('j. n. Y');
@endphp
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rozpis výletů {{ $range }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #18181b; margin: 0; padding: 24px; font-size: 12px; line-height: 1.35; }
        .sheet { max-width: 1200px; margin: 0 auto; }
        header { display: flex; align-items: center; gap: 16px; border-bottom: 3px solid #09afe6; padding-bottom: 10px; margin-bottom: 8px; }
        header img { height: 48px; }
        header h1 { margin: 0; font-size: 20px; }
        h2 { font-size: 14px; margin: 18px 0 6px; color: #09afe6; text-transform: uppercase; letter-spacing: .03em; break-after: avoid; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d4d4d8; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #f4f4f5; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; color: #52525b; }
        tr { break-inside: avoid; }
        .times { white-space: nowrap; width: 80px; }
        .count { white-space: nowrap; width: 60px; }
        .muted { color: #a1a1aa; }
        .empty { margin-top: 24px; color: #71717a; }
        .toolbar { text-align: right; margin-bottom: 12px; }
        .btn { background: #09afe6; color: #fff; border: 0; border-radius: 6px; padding: 8px 16px; font-size: 13px; cursor: pointer; }
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar no-print">
            <button class="btn" onclick="window.print()">Vytisknout / uložit PDF</button>
        </div>

        <header>
            <img src="{{ asset('logo-cerna.png') }}" alt="Rafting Morava">
            <h1>Rozpis výletů {{ $range }}</h1>
        </header>

        @forelse ($days as $date => $dayRows)
            @php($d = \Illuminate\Support\Carbon::parse($date))
            <h2>{{ \Illuminate\Support\Str::ucfirst($d->translatedFormat('l')) }} {{ $d->format('j. n. Y') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>Časy</th>
                        <th>Výlet</th>
                        <th>Klient</th>
                        <th>Lidí</th>
                        <th>Úsek</th>
                        <th>Instruktoři</th>
                        <th>Řidiči</th>
                        <th>Vozidla</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dayRows as $day)
                        @php($trip = $day->trip)
                        <tr>
                            <td class="times">
                                @if ($fmt($day->meeting_time)) sraz {{ $fmt($day->meeting_time) }}<br>@endif
                                @if ($fmt($day->start_time)) start {{ $fmt($day->start_time) }}<br>@endif
                                @if ($fmt($day->end_time)) konec {{ $fmt($day->end_time) }}@endif
                                @if (! $day->meeting_time && ! $day->start_time && ! $day->end_time)<span class="muted">—</span>@endif
                            </td>
                            <td>
                                {{ $trip->name }}
                                @if ($trip->days_count > 1)<br><span class="muted">den {{ $day->order }}/{{ $trip->days_count }}</span>@endif
                            </td>
                            <td>
                                {{ $trip->client_name ?: '—' }}
                                @if ($phone = $trip->onsite_phone ?: $trip->client_phone)<br>{{ $phone }}@endif
                            </td>
                            <td class="count">
                                {{ $trip->planned_paying_count ?? '—' }}@if ($trip->planned_escort_count) + {{ $trip->planned_escort_count }}@endif
                            </td>
                            <td>{{ $day->sectionLabel() ?: '—' }}</td>
                            <td>{{ $names($day, 'instructor') ?: '—' }}</td>
                            <td>{{ $names($day, 'driver') ?: '—' }}</td>
                            <td>{{ $day->vehicles->pluck('name')->implode(', ') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <p class="empty">V tomto období nejsou žádné výlety.</p>
        @endforelse
    </div>
</body>
</html>
