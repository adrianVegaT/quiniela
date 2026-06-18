<?php

use App\Models\Quiniela;
use App\Services\PredictionService;
use App\Services\ScoringService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Quiniela')] class extends Component
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
    public function canViewOthers(): bool
    {
        return app(PredictionService::class)->canViewOtherPredictions($this->quiniela, auth()->user());
    }

    #[Computed]
    public function progress(): array
    {
        return app(PredictionService::class)->getProgress($this->quiniela, auth()->user());
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
        <flux:heading size="xl">{{ $quiniela->name }}</flux:heading>
        <flux:badge variant="solid" size="sm" :color="$quiniela->status === 'active' ? 'green' : ($quiniela->status === 'closed' ? 'amber' : 'zinc')">
            {{ $quiniela->status === 'active' ? 'Activa' : ($quiniela->status === 'closed' ? 'Cerrada' : 'Histórica') }}
        </flux:badge>
    </div>

    @if ($quiniela->prediction_edit_deadline)
        <flux:card class="mb-6">
            <div class="flex items-center gap-2">
                <flux:text>Límite de edición: <strong>{{ $quiniela->prediction_edit_deadline->format('d/m/Y H:i') }}</strong></flux:text>
                @if ($quiniela->prediction_edit_deadline->isPast())
                    <flux:badge variant="solid" color="red" size="sm">Expirado</flux:badge>
                @else
                    <flux:badge variant="solid" color="green" size="sm">{{ $quiniela->prediction_edit_deadline->diffForHumans() }}</flux:badge>
                @endif
            </div>
        </flux:card>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-6">
        <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.leaderboard', $quiniela)">
            <div class="text-left">
                <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-amber-500/10 flex items-center justify-center mb-2 lg:mb-3">
                    <flux:icon.trophy class="size-4 lg:size-5 text-amber-400" />
                </div>
                <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Posiciones') }}</p>
                <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Ver tabla') }}</p>
            </div>
        </flux:card>
        <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.predictions', $quiniela)">
            <div class="text-left">
                <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-accent/10 flex items-center justify-center mb-2 lg:mb-3">
                    <flux:icon.pencil class="size-4 lg:size-5 text-accent" />
                </div>
                <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Predicciones') }}</p>
                <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Llenar marcadores') }}</p>
            </div>
        </flux:card>
        <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.rules', $quiniela)">
            <div class="text-left">
                <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-blue-500/10 flex items-center justify-center mb-2 lg:mb-3">
                    <flux:icon.information-circle class="size-4 lg:size-5 text-blue-400" />
                </div>
                <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Reglas') }}</p>
                <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Puntuación') }}</p>
            </div>
        </flux:card>
        <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.today', $quiniela)">
            <div class="text-left">
                <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-red-500/10 flex items-center justify-center mb-2 lg:mb-3">
                    <flux:icon.calendar class="size-4 lg:size-5 text-red-400" />
                </div>
                <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Hoy') }}</p>
                <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Partidos del día') }}</p>
            </div>
        </flux:card>
        @if ($quiniela->owner_id === auth()->id())
            <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.show', $quiniela)">
                <div class="text-left">
                    <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-purple-500/10 flex items-center justify-center mb-2 lg:mb-3">
                        <flux:icon.cog class="size-4 lg:size-5 text-purple-400" />
                    </div>
                    <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Administrar') }}</p>
                    <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Gestionar') }}</p>
                </div>
            </flux:card>
        @endif
    </div>

    <flux:card class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-[11px] text-zinc-500 mb-0.5">{{ __('Código de invitación') }}</p>
                <p class="text-lg lg:text-xl font-mono font-bold tracking-widest text-white select-all">{{ $quiniela->code }}</p>
            </div>
            <flux:button variant="subtle" icon="clipboard" size="sm" />
        </div>
    </flux:card>

    @if ($this->canViewOthers)
        @if (auth()->id() === $quiniela->owner_id)
            <flux:card class="mb-4 bg-amber-900/20 border border-amber-700/50">
                <flux:text class="text-amber-300 text-sm">{{ __('Administrador: haz clic en el nombre de un participante para ver y editar sus predicciones.') }}</flux:text>
            </flux:card>
        @else
            <flux:card class="mb-4 bg-zinc-800/50 border border-zinc-700/50">
                <flux:text class="text-zinc-400 text-sm">{{ __('Haz clic en el nombre de un participante para ver sus predicciones.') }}</flux:text>
            </flux:card>
        @endif

        <flux:heading size="lg" class="mb-4">{{ __('Tabla de posiciones') }}</flux:heading>

        @if (empty($this->leaderboard['rows']))
            <flux:card class="mb-8">
                <flux:text class="text-zinc-400">{{ __('Aún no hay resultados registrados.') }}</flux:text>
            </flux:card>
        @else
            <div class="overflow-x-auto mb-8">
                <div class="min-w-full">
                    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                        <div class="px-4 lg:px-6 py-2.5 lg:py-3 border-b border-zinc-800 flex items-center text-[11px] lg:text-xs text-zinc-500 font-medium">
                            <span class="w-8 lg:w-12">#</span>
                            <span class="flex-1">{{ __('Participante') }}</span>
                            @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_exact_score > 0)
                                <span class="w-12 lg:w-16 text-center">{{ __('Exacto') }}</span>
                            @endif
                            @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_winner_draw > 0)
                                <span class="w-12 lg:w-16 text-center">{{ __('Ganador') }}</span>
                            @endif
                            @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_one_team_goals > 0)
                                <span class="w-12 lg:w-16 text-center">{{ __('Goles') }}</span>
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
                                    <span class="w-12 lg:w-16 text-center text-sm text-zinc-300">{{ $row['exact_score_count'] }}</span>
                                @endif
                                @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_winner_draw > 0)
                                    <span class="w-12 lg:w-16 text-center text-sm text-zinc-300">{{ $row['winner_draw_count'] }}</span>
                                @endif
                                @if ($this->leaderboard['rules'] && $this->leaderboard['rules']->points_one_team_goals > 0)
                                    <span class="w-12 lg:w-16 text-center text-sm text-zinc-300">{{ $row['one_team_goals_count'] }}</span>
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
    @else
        <flux:card class="mb-8">
            <flux:text class="text-zinc-400">
                Las predicciones de otros participantes estarán visibles después de la fecha límite de edición.
                @if ($quiniela->prediction_edit_deadline)
                    <strong>{{ $quiniela->prediction_edit_deadline->format('d/m/Y H:i') }}</strong>
                @endif
            </flux:text>
        </flux:card>
    @endif

    <flux:heading size="lg" class="mb-4">{{ __('Actividad reciente') }}</flux:heading>

    @if ($this->recentLogs->isNotEmpty())
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
    @else
        <flux:card>
            <flux:text class="text-zinc-400">{{ __('No hay actividad registrada aún.') }}</flux:text>
        </flux:card>
    @endif
</div>
