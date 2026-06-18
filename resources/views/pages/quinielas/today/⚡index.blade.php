<?php

use App\Models\Quiniela;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Partidos del día')] class extends Component
{
    public Quiniela $quiniela;

    public string $date;

    public function mount(Quiniela $quiniela): void
    {
        $isOwner = auth()->id() === $quiniela->owner_id;
        $isParticipant = $quiniela->participants()
            ->where('user_id', auth()->id())
            ->whereNull('quiniela_user.deleted_at')
            ->exists();

        if (! $isOwner && ! $isParticipant) {
            abort(403);
        }

        $this->quiniela = $quiniela;
        $this->date = now()->format('Y-m-d');
    }

    public function updatedDate(): void
    {
        unset($this->grid);
    }

    #[Computed]
    public function grid(): array
    {
        $date = Carbon::parse($this->date)->startOfDay();

        $matches = $this->quiniela->matches()
            ->whereDate('match_date', $date)
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy('match_date')
            ->get();

        $participants = $this->quiniela->participants()
            ->whereNull('quiniela_user.deleted_at')
            ->orderBy('name')
            ->get();

        $rows = $participants->map(function ($user) use ($matches) {
            $total = 0;
            $cells = $matches->map(function ($fixture) use ($user, &$total) {
                $prediction = $fixture->predictions()
                    ->where('user_id', $user->id)
                    ->first();

                $points = ($prediction?->exact_score_points ?? 0)
                    + ($prediction?->winner_draw_points ?? 0)
                    + ($prediction?->one_team_goals_points ?? 0);
                $total += $points;

                return [
                    'prediction_id' => $prediction?->id,
                    'home_score' => $prediction?->home_score,
                    'away_score' => $prediction?->away_score,
                    'has_prediction' => $prediction !== null,
                    'points' => $points,
                    'exact' => ($prediction?->exact_score_points ?? 0) > 0,
                    'winner' => ($prediction?->winner_draw_points ?? 0) > 0,
                    'goals' => ($prediction?->one_team_goals_points ?? 0) > 0,
                ];
            });

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'is_me' => $user->id === auth()->id(),
                'cells' => $cells,
                'total' => $total,
            ];
        });

        $matchesData = $matches->map(function ($fixture) {
            return [
                'id' => $fixture->id,
                'home_abbr' => mb_substr($fixture->homeTeam->name, 0, 3),
                'away_abbr' => mb_substr($fixture->awayTeam->name, 0, 3),
                'home_name' => $fixture->homeTeam->name,
                'away_name' => $fixture->awayTeam->name,
                'time' => $fixture->match_date->format('H:i'),
                'is_completed' => $fixture->is_completed,
                'home_result' => $fixture->home_score,
                'away_result' => $fixture->away_score,
            ];
        });

        return [
            'matches' => $matchesData,
            'rows' => $rows,
        ];
    }
}; ?>

<div>
    <div class="flex items-center gap-3 mb-6">
        <flux:button x-on:click="window.history.back()" variant="subtle" icon="chevron-left" size="sm" />
        <flux:heading size="xl">{{ __('Partidos del día') }}</flux:heading>
    </div>

    <flux:card class="mb-6 max-w-xs">
        <flux:input wire:model.live="date" label="Fecha" type="date" />
    </flux:card>

    @if ($this->grid['matches']->isEmpty())
        <flux:card>
            <flux:text class="text-zinc-400">{{ __('No hay partidos en esta fecha.') }}</flux:text>
        </flux:card>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-zinc-950 px-3 py-3 text-left text-zinc-400 font-medium border-b border-zinc-700 min-w-[120px]">
                            {{ __('Participante') }}
                        </th>
                        @foreach ($this->grid['matches'] as $match)
                            <th class="px-3 py-3 text-center border-b border-zinc-700 min-w-[90px]">
                                <div class="text-xs font-semibold text-white whitespace-nowrap">
                                    {{ $match['home_abbr'] }} vs {{ $match['away_abbr'] }}
                                </div>
                                <div class="text-[10px] text-zinc-500 mt-0.5">{{ $match['time'] }}</div>
                                @if ($match['is_completed'])
                                    <flux:badge variant="solid" color="green" size="sm" class="mt-1 !text-[9px]">
                                        {{ $match['home_result'] }}-{{ $match['away_result'] }}
                                    </flux:badge>
                                @else
                                    <span class="text-[10px] text-zinc-600 mt-1 block">Pend.</span>
                                @endif
                            </th>
                        @endforeach
                        <th class="px-3 py-3 text-center text-zinc-400 font-medium border-b border-zinc-700 min-w-[60px]">
                            Pts
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->grid['rows'] as $row)
                        <tr class="{{ $row['is_me'] ? 'bg-accent/5' : '' }}">
                            <td class="sticky left-0 {{ $row['is_me'] ? 'bg-accent/5' : 'bg-zinc-950' }} px-3 py-3 border-b border-zinc-800/50 text-xs whitespace-nowrap {{ $row['is_me'] ? 'text-accent font-medium' : 'text-zinc-300' }}">
                                {{ $row['name'] }}{{ $row['is_me'] ? ' (tú)' : '' }}
                            </td>
                            @foreach ($row['cells'] as $cell)
                                <td class="px-3 py-3 text-center border-b border-zinc-800/50">
                                    @if ($cell['has_prediction'])
                                        <span class="font-mono text-xs
                                            {{ $cell['exact'] ? 'text-green-400 font-semibold' : '' }}
                                            {{ $cell['winner'] && !$cell['exact'] ? 'text-amber-400' : '' }}
                                            {{ $cell['goals'] && !$cell['exact'] && !$cell['winner'] ? 'text-blue-400' : '' }}
                                            {{ !$cell['exact'] && !$cell['winner'] && !$cell['goals'] ? 'text-zinc-300' : '' }}
                                        ">
                                            {{ $cell['home_score'] }}-{{ $cell['away_score'] }}
                                            @if ($cell['points'] > 0)
                                                <span class="text-[10px] text-accent ml-0.5">+{{ $cell['points'] }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-zinc-600 text-xs">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-3 text-center border-b border-zinc-800/50">
                                <span class="text-xs font-bold {{ $row['total'] > 0 ? 'text-accent' : 'text-zinc-500' }}">{{ $row['total'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
