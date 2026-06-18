<?php

use App\Models\Quiniela;
use App\Models\Prediction;
use App\Services\PredictionService;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mis predicciones')] class extends Component
{
    public Quiniela $quiniela;

    public array $predictions = [];

    #[Computed]
    public function progress(): array
    {
        return app(PredictionService::class)->getProgress($this->quiniela, auth()->user());
    }

    #[Computed]
    public function canViewOthers(): bool
    {
        return app(PredictionService::class)->canViewOtherPredictions($this->quiniela, auth()->user());
    }

    #[Computed]
    public function canEdit(): bool
    {
        return app(PredictionService::class)->canEdit($this->quiniela, auth()->user());
    }

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
        $this->loadPredictions();
    }

    protected function loadPredictions(): void
    {
        $user = auth()->user();

        $this->predictions = $this->quiniela->matches()
            ->with(['homeTeam', 'awayTeam', 'group'])
            ->orderByRaw('group_id IS NULL, group_id')
            ->orderBy('match_date')
            ->get()
            ->map(function ($fixture) use ($user) {
                $prediction = Prediction::where('match_id', $fixture->id)
                    ->where('user_id', $user->id)
                    ->first();

                return [
                    'match_id' => $fixture->id,
                    'home_team' => $fixture->homeTeam->name,
                    'away_team' => $fixture->awayTeam->name,
                    'group' => $fixture->group?->name,
                    'match_date' => $fixture->match_date->format('d/m/Y H:i'),
                    'is_past' => $fixture->match_date->isPast(),
                    'home_score' => $prediction?->home_score,
                    'away_score' => $prediction?->away_score,
                    'has_prediction' => $prediction !== null,
                ];
            })
            ->values()
            ->toArray();
    }

    public function save(): void
    {
        $service = app(PredictionService::class);
        $user = auth()->user();

        if (! $service->canEdit($this->quiniela, $user)) {
            Flux::toast(variant: 'danger', text: __('El plazo de edición ha expirado. No puedes modificar tus predicciones.'));

            return;
        }

        $key = 'prediction-save:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            Flux::toast(variant: 'danger', text: __('Demasiados intentos. Espera :seconds segundos.', ['seconds' => $seconds]));

            return;
        }

        RateLimiter::hit($key, 60);

        $saved = 0;

        foreach ($this->predictions as $index => $data) {
            $fixture = $this->quiniela->matches()->find($data['match_id']);

            if (! $fixture) {
                continue;
            }

            $home = isset($data['home_score']) && $data['home_score'] !== ''
                ? (int) $data['home_score']
                : null;
            $away = isset($data['away_score']) && $data['away_score'] !== ''
                ? (int) $data['away_score']
                : null;

            if ($home === null && $away === null && ! ($data['has_prediction'] ?? false)) {
                continue;
            }

            $service->savePrediction($fixture, $user, $home, $away);
            $saved++;
        }

        $this->loadPredictions();
        unset($this->progress);

        Flux::toast(variant: 'success', text: "{$saved} predicciones guardadas.");
    }

    #[Computed]
    public function myLogs()
    {
        return \App\Models\PredictionLog::where('user_id', auth()->id())
            ->whereIn('match_id', $this->quiniela->matches()->select('id'))
            ->with(['changedBy', 'match.homeTeam', 'match.awayTeam'])
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();
    }
}; ?>

<div>
    <div class="flex items-center gap-3 mb-6">
        <flux:button x-on:click="window.history.back()" variant="subtle" icon="chevron-left" size="sm" />
        <flux:heading size="xl">{{ $quiniela->name }}</flux:heading>
    </div>

    <div class="flex gap-2 lg:gap-3 mb-6">
        <flux:card class="flex-1 text-center py-3">
            <p class="text-[10px] text-zinc-500">{{ __('Progreso') }}</p>
            <p class="text-lg font-bold">{{ $this->progress['completed'] }}<span class="text-zinc-500 font-normal text-sm">/{{ $this->progress['total'] }}</span></p>
        </flux:card>
        <flux:card class="flex-1 text-center py-3">
            <p class="text-[10px] text-zinc-500">{{ __('Límite') }}</p>
            @if ($quiniela->prediction_edit_deadline)
                <p class="text-lg font-bold text-accent">{{ $quiniela->prediction_edit_deadline->isPast() ? __('Expirado') : $quiniela->prediction_edit_deadline->diffForHumans() }}</p>
            @else
                <p class="text-lg font-bold text-zinc-400">{{ __('Sin límite') }}</p>
            @endif
        </flux:card>
        <div class="flex-1">
            <flux:button wire:click="save" variant="primary" class="w-full h-full! py-3 text-sm font-semibold">
                {{ __('Guardar') }}
            </flux:button>
        </div>
    </div>

    @php $currentGroup = null; @endphp
    @foreach ($predictions as $index => $pred)
        @if ($pred['group'] !== $currentGroup)
            @if ($currentGroup !== null)
                </div>
            @endif
            @php $currentGroup = $pred['group']; @endphp
            <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3 mt-6">
                {{ $currentGroup ?? __('Partidos') }}
            </p>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-2 lg:gap-3">
        @endif

        <flux:card class="{{ $pred['is_past'] ? 'opacity-50' : '' }} py-3">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-medium truncate">{{ $pred['home_team'] }}</span>
                        <span class="text-zinc-600 text-[10px]">vs</span>
                        <span class="text-sm font-medium truncate">{{ $pred['away_team'] }}</span>
                    </div>
                    <p class="text-[11px] text-zinc-500 mt-0.5">{{ $pred['match_date'] }}</p>
                </div>
                <div class="flex items-center gap-2 ml-3 shrink-0">
                    <flux:input
                        wire:model="predictions.{{ $index }}.home_score"
                        type="number"
                        min="0"
                        max="99"
                        class="w-12 text-center [&>input]:text-center"
                        placeholder="-"
                        :disabled="($pred['is_past'] && auth()->id() !== $quiniela->owner_id) || !$this->canEdit"
                    />
                    <span class="text-zinc-600 text-xs">-</span>
                    <flux:input
                        wire:model="predictions.{{ $index }}.away_score"
                        type="number"
                        min="0"
                        max="99"
                        class="w-12 text-center [&>input]:text-center"
                        placeholder="-"
                        :disabled="($pred['is_past'] && auth()->id() !== $quiniela->owner_id) || !$this->canEdit"
                    />
                    @if ($pred['has_prediction'] && !$pred['is_past'])
                        <span class="w-5 h-5 rounded-full bg-accent/15 flex items-center justify-center shrink-0">
                            <svg class="w-3 h-3 text-accent" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        </span>
                    @endif
                </div>
            </div>
        </flux:card>
    @endforeach
    @if ($currentGroup !== null)
        </div>
    @endif

    <div class="flex gap-2 mt-6">
        <flux:button wire:navigate :href="route('quinielas.rules', $quiniela)" variant="outline" size="sm" icon="information-circle">
            {{ __('Reglas de puntuación') }}
        </flux:button>
        <flux:button wire:click="save" variant="primary" class="lg:hidden flex-1">
            {{ __('Guardar') }}
        </flux:button>
    </div>

    @if ($this->myLogs->isNotEmpty())
        <flux:heading size="lg" class="mt-8 mb-4">{{ __('Mi historial de cambios') }}</flux:heading>
        <flux:card>
            <div class="divide-y divide-zinc-700">
                @foreach ($this->myLogs as $log)
                    <div class="py-2 first:pt-0 last:pb-0 text-sm">
                        <div class="flex justify-between text-zinc-400">
                            <span>{{ $log->match->homeTeam->name ?? '?' }} vs {{ $log->match->awayTeam->name ?? '?' }}</span>
                            <span>{{ $log->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="mt-1">
                            @if ($log->action === 'created')
                                <flux:badge variant="solid" color="green" size="sm">Creado</flux:badge>
                                <span class="text-zinc-300 ml-1">{{ $log->new_home_score }} - {{ $log->new_away_score }}</span>
                            @elseif ($log->action === 'updated')
                                <flux:badge variant="solid" color="amber" size="sm">Editado</flux:badge>
                                <span class="text-zinc-500"><span class="line-through">{{ $log->old_home_score }}-{{ $log->old_away_score }}</span> → <span class="text-zinc-300">{{ $log->new_home_score }}-{{ $log->new_away_score }}</span></span>
                            @elseif ($log->action === 'admin_edit')
                                <flux:badge variant="solid" color="red" size="sm">Admin</flux:badge>
                                <span class="text-zinc-500"><span class="line-through">{{ $log->old_home_score }}-{{ $log->old_away_score }}</span> → <span class="text-zinc-300">{{ $log->new_home_score }}-{{ $log->new_away_score }}</span></span>
                                @if ($log->reason)
                                    <p class="text-zinc-500 text-xs mt-0.5">Motivo: {{ $log->reason }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
