@php
    $fmt = fn ($t) => $t ? \Illuminate\Support\Str::of($t)->substr(0, 5) : null;
    $names = fn ($day, $type) => $day->assignments->where('type', $type)->map(fn ($a) => $a->user?->name)->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rozpis výletu – {{ $trip->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #18181b; margin: 0; padding: 24px; font-size: 13px; line-height: 1.4; }
        .sheet { max-width: 900px; margin: 0 auto; }
        header { display: flex; align-items: center; gap: 16px; border-bottom: 3px solid #09afe6; padding-bottom: 12px; margin-bottom: 16px; }
        header img { height: 56px; }
        header h1 { margin: 0; font-size: 20px; }
        header .sub { color: #71717a; font-size: 13px; }
        h2 { font-size: 14px; margin: 20px 0 8px; color: #09afe6; text-transform: uppercase; letter-spacing: .03em; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; }
        .meta div { padding: 2px 0; }
        .meta .label { color: #71717a; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { border: 1px solid #d4d4d8; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f4f4f5; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #52525b; }
        .times { white-space: nowrap; }
        .muted { color: #a1a1aa; }
        .note { border-left: 3px solid #e4e4e7; padding: 2px 0 2px 10px; margin: 6px 0; }
        .note .who { color: #71717a; font-size: 11px; }
        .toolbar { text-align: right; margin-bottom: 12px; }
        .btn { background: #09afe6; color: #fff; border: 0; border-radius: 6px; padding: 8px 16px; font-size: 13px; cursor: pointer; }
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
            <div>
                <h1>{{ $trip->name }}</h1>
                <div class="sub">
                    Rozpis výletu ·
                    {{ \App\Models\Trip::TYPES[$trip->type] ?? $trip->type }} ·
                    {{ $trip->date_from?->format('d.m.Y') }}@if ($trip->date_to && $trip->date_to->ne($trip->date_from)) – {{ $trip->date_to?->format('d.m.Y') }}@endif
                </div>
            </div>
        </header>

        <h2>Klient</h2>
        <div class="meta">
            <div><span class="label">Jméno / organizace:</span> {{ $trip->client_name ?: '—' }}</div>
            <div><span class="label">Program:</span> {{ $trip->program?->name ?: '—' }}</div>
            <div><span class="label">E-mail:</span> {{ $trip->client_email ?: '—' }}</div>
            <div><span class="label">Telefon:</span> {{ $trip->client_phone ?: '—' }}</div>
            <div><span class="label">Kontakt na místě:</span> {{ $trip->onsite_phone ?: '—' }}</div>
            <div><span class="label">Platících / doprovod:</span>
                {{ $trip->planned_paying_count ?? '—' }}@if ($trip->planned_escort_count) + {{ $trip->planned_escort_count }} doprovod @endif
            </div>
            @if ($trip->children_ages)
                <div><span class="label">Věk dětí:</span> {{ $trip->children_ages }}</div>
            @endif
        </div>

        <h2>Dny výletu</h2>
        <table>
            <thead>
                <tr>
                    <th>Den</th>
                    <th>Datum</th>
                    <th>Časy</th>
                    <th>Úsek</th>
                    <th>Instruktoři</th>
                    <th>Řidiči</th>
                    <th>Vozidla</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trip->days as $day)
                    <tr>
                        <td>{{ $day->order }}</td>
                        <td class="times">{{ $day->date?->format('d.m.Y') }}<br><span class="muted">{{ \Illuminate\Support\Str::ucfirst($day->date?->translatedFormat('l')) }}</span></td>
                        <td class="times">
                            @if ($fmt($day->meeting_time)) sraz {{ $fmt($day->meeting_time) }}<br>@endif
                            @if ($fmt($day->start_time)) start {{ $fmt($day->start_time) }}<br>@endif
                            @if ($fmt($day->end_time)) konec {{ $fmt($day->end_time) }}@endif
                            @if (! $day->meeting_time && ! $day->start_time && ! $day->end_time)<span class="muted">—</span>@endif
                        </td>
                        <td>{{ $day->sectionLabel() ?: '—' }}</td>
                        <td>{{ $names($day, 'instructor') ?: '—' }}</td>
                        <td>{{ $names($day, 'driver') ?: '—' }}</td>
                        <td>{{ $day->vehicles->pluck('name')->implode(', ') ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Zatím žádné dny.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($trip->notes->isNotEmpty())
            <h2>Poznámky</h2>
            @foreach ($trip->notes as $note)
                <div class="note">
                    <div class="who">{{ $note->author?->name ?? 'systém' }} · {{ $note->created_at?->format('d.m.Y H:i') }}</div>
                    {!! nl2br(e($note->text)) !!}
                </div>
            @endforeach
        @endif
    </div>
</body>
</html>
