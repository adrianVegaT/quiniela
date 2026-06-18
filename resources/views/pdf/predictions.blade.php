<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1a1a1a; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #10b981; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin: 0; color: #10b981; }
        .header p { font-size: 11px; color: #666; margin: 4px 0 0; }
        .info { margin-bottom: 20px; }
        .info p { margin: 2px 0; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f3f4f6; text-align: left; padding: 8px 6px; font-size: 10px; border-bottom: 2px solid #d1d5db; }
        td { padding: 6px; font-size: 10px; border-bottom: 1px solid #e5e7eb; }
        .group-header td { background: #d1fae5; font-weight: bold; font-size: 11px; padding: 8px 6px; border-bottom: 1px solid #6ee7b7; color: #065f46; }
        .group-spacer td { padding: 4px 0; border: none; }
        .footer { margin-top: 30px; border-top: 1px solid #d1d5db; padding-top: 10px; font-size: 9px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Quiniela &mdash; Boleta de predicciones</h1>
        <p>{{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="info">
        <p><strong>Quiniela:</strong> {{ $quiniela->name }} ({{ $quiniela->code }})</p>
        <p><strong>Participante:</strong> {{ $user->name }}</p>
        <p><strong>Partidos en la quiniela:</strong> {{ $matches->count() }}</p>
        @if ($quiniela->prediction_edit_deadline)
            <p><strong>Límite de edición:</strong> {{ $quiniela->prediction_edit_deadline->format('d/m/Y H:i') }}</p>
        @endif
    </div>

    @php $currentGroup = null; $hasGroups = $matches->contains(fn ($m) => $m->match->group !== null); @endphp
    <table>
        <thead>
            <tr>
                <th>Partido</th>
                @if ($hasGroups)
                    <th>Grupo</th>
                @endif
                <th>Fecha</th>
                <th style="text-align: center;">Predicción</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($matches as $item)
                @php $groupName = $item->match->group?->name; @endphp
                @if ($hasGroups && $groupName !== $currentGroup)
                    @if ($currentGroup !== null)
                        <tr class="group-spacer"><td colspan="{{ $hasGroups ? 4 : 3 }}"></td></tr>
                    @endif
                    @php $currentGroup = $groupName; @endphp
                    <tr class="group-header">
                        <td colspan="{{ $hasGroups ? 4 : 3 }}">{{ $currentGroup ?? 'Sin grupo' }}</td>
                    </tr>
                @endif
                <tr>
                    <td>{{ $item->match->homeTeam->name }} vs {{ $item->match->awayTeam->name }}</td>
                    @if ($hasGroups)
                        <td>{{ $item->match->group?->name ?? '-' }}</td>
                    @endif
                    <td>{{ $item->match->match_date->format('d/m/Y H:i') }}</td>
                    <td style="text-align: center;">{{ $item->home_score ?? '-' }} - {{ $item->away_score ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Documento generado por Quiniela &mdash; {{ now()->format('d/m/Y H:i') }} &mdash; ID: {{ $quiniela->id }}
    </div>
</body>
</html>
