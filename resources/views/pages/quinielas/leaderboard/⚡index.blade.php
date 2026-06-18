<?php

use App\Models\Quiniela;
use App\Services\ScoringService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tabla de posiciones')] class extends Component
{
    public Quiniela $quiniela;

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
    }

    #[Computed]
    public function leaderboard(): array
    {
        return app(ScoringService::class)->leaderboard($this->quiniela);
    }

    #[Computed]
    public function recentLogs()
    {
        return \App\Models\PredictionLog::whereIn('match_id', $this->quiniela->matches()->select('id'))
            ->with(['user', 'changedBy', 'match.homeTeam', 'match.awayTeam'])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get();
    }
}; ?>

<div>
    <div class="flex items-center gap-3 mb-6">
        <flux:button x-on:click="window.history.back()" variant="subtle" icon="chevron-left" size="sm" />
        <flux:heading size="xl">{{ __('Tabla de posiciones') }}</flux:heading>
    </div>

    <flux:card class="mb-4">
        <div class="flex gap-6 text-sm">
            <span>Partidos: <strong>{{ $this->leaderboard['completed_matches'] }}/{{ $this->leaderboard['total_matches'] }}</strong></span>
        </div>
    </flux:card>

    @if (auth()->id() === $quiniela->owner_id)
        <flux:card class="mb-4 bg-amber-900/20 border border-amber-700/50">
            <flux:text class="text-amber-300 text-sm">{{ __('Administrador: haz clic en el nombre de un participante para ver y editar sus predicciones.') }}</flux:text>
        </flux:card>
    @else
        <flux:card class="mb-4 bg-zinc-800/50 border border-zinc-700/50">
            <flux:text class="text-zinc-400 text-sm">{{ __('Haz clic en el nombre de un participante para ver sus predicciones.') }}</flux:text>
        </flux:card>
    @endif

    @if (empty($this->leaderboard['rows']))
        <flux:card>
            <flux:text class="text-zinc-400">{{ __('No hay participantes o resultados aún.') }}</flux:text>
        </flux:card>
    @else
        <div class="overflow-x-auto">
            <div class="min-w-full">
                <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                    <div class="px-4 lg:px-6 py-2.5 lg:py-3 border-b border-zinc-800 flex items-center text-[11px] lg:text-xs text-zinc-500 font-medium">
                        <span class="w-8 lg:w-12">#</span>
                        <span class="flex-1">{{ __('Participante') }}</span>
                        @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_exact_score > 0)
                            <span class="w-12 lg:w-20 text-center">{{ __('Exacto') }}</span>
                        @endif
                        @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_winner_draw > 0)
                            <span class="w-12 lg:w-20 text-center">{{ __('Ganador') }}</span>
                        @endif
                        @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_one_team_goals > 0)
                            <span class="w-12 lg:w-20 text-center">{{ __('Goles') }}</span>
                        @endif
                        <span class="w-14 lg:w-20 text-right">{{ __('Pts') }}</span>
                    </div>

                    @foreach ($this->leaderboard['rows'] as $index => $row)
                        @php
                            $isMe = $row['user']->id === auth()->id();
                            $isLast = $index === count($this->leaderboard['rows']) - 1;
                            $rowBg = match(true) {
                                $index === 0 => 'bg-amber-500/5',
                                $index === 2 => 'bg-orange-500/5',
                                $isLast && $index > 2 => 'bg-red-950/20',
                                default => '',
                            };
                        @endphp
                        <div class="px-4 lg:px-6 py-3 lg:py-4 flex items-center border-b border-zinc-800/50 {{ $rowBg }} {{ $isMe ? 'bg-accent/5 border-l-2 border-l-accent' : '' }} last:border-b-0">
                            <span class="w-8 lg:w-12">
                                @if ($index === 0)
                                    <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full bg-amber-500/20 text-amber-400 text-xs font-bold flex items-center justify-center">1</span>
                                @elseif ($index === 1)
                                    <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full bg-zinc-700 text-zinc-300 text-xs font-bold flex items-center justify-center">2</span>
                                @elseif ($index === 2)
                                    <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full bg-orange-500/20 text-orange-400 text-xs font-bold flex items-center justify-center">3</span>
                                @elseif ($isLast)
                                    <span class="w-6 h-6 lg:w-7 lg:h-7 rounded-full bg-red-500/10 text-red-400 text-xs font-bold flex items-center justify-center">{{ $index + 1 }}</span>
                                @else
                                    <span class="text-zinc-500 text-sm pl-1.5">{{ $index + 1 }}</span>
                                @endif
                            </span>
                            <span class="flex-1 text-sm {{ $isMe ? 'font-medium text-accent' : 'font-medium' }}">
                                <a href="{{ route('quinielas.user.show', [$quiniela, $row['user']]) }}" class="hover:underline">
                                    {{ $row['user']->name }}{{ $isMe ? ' (tú)' : '' }}
                                </a>
                            </span>
                            @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_exact_score > 0)
                                <span class="w-12 lg:w-20 text-center text-sm text-zinc-300">{{ $row['exact_score_count'] }}</span>
                            @endif
                            @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_winner_draw > 0)
                                <span class="w-12 lg:w-20 text-center text-sm text-zinc-300">{{ $row['winner_draw_count'] }}</span>
                            @endif
                            @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_one_team_goals > 0)
                                <span class="w-12 lg:w-20 text-center text-sm text-zinc-300">{{ $row['one_team_goals_count'] }}</span>
                            @endif
                            <span class="w-14 lg:w-20 text-right">
                                <span class="px-2 py-0.5 rounded-full bg-accent/15 text-accent text-xs font-bold">{{ $row['total_points'] }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($this->recentLogs->isNotEmpty())
        <flux:heading size="lg" class="mt-8 mb-4">{{ __('Actividad reciente') }}</flux:heading>
        <flux:card>
            <div class="divide-y divide-zinc-700">
                @foreach ($this->recentLogs as $log)
                    <div class="py-2 first:pt-0 last:pb-0 text-sm">
                        <div class="flex justify-between text-zinc-400">
                            <span>{{ $log->user->name }} — {{ $log->match->homeTeam->name ?? '?' }} vs {{ $log->match->awayTeam->name ?? '?' }}</span>
                            <span>{{ $log->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="mt-0.5">
                            @if ($log->action === 'created')
                                <span class="text-green-400">Predicción creada:</span>
                                <span class="text-zinc-300">{{ $log->new_home_score }}-{{ $log->new_away_score }}</span>
                            @elseif ($log->action === 'updated')
                                <span class="text-amber-400">Predicción editada:</span>
                                <span class="text-zinc-500 line-through">{{ $log->old_home_score }}-{{ $log->old_away_score }}</span>
                                <span class="text-zinc-300"> → {{ $log->new_home_score }}-{{ $log->new_away_score }}</span>
                            @elseif ($log->action === 'admin_edit')
                                <span class="text-red-400">Admin editó:</span>
                                <span class="text-zinc-300">{{ $log->new_home_score }}-{{ $log->new_away_score }}</span>
                                @if ($log->reason)
                                    <span class="text-zinc-500">({{ $log->reason }})</span>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
