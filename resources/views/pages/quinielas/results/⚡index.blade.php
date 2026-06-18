<?php

use App\Models\Fixture;
use App\Models\Quiniela;
use App\Services\ScoringService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registrar resultados')] class extends Component
{
    public Quiniela $quiniela;

    public function mount(Quiniela $quiniela): void
    {
        if (auth()->id() !== $quiniela->owner_id) {
            abort(403);
        }

        $this->quiniela = $quiniela;
    }

    #[Computed]
    public function fixtures()
    {
        return $this->quiniela->matches()
            ->with(['homeTeam', 'awayTeam', 'group'])
            ->orderBy('match_date')
            ->get();
    }

    public function saveResult(Fixture $fixture, ?int $homeScore, ?int $awayScore): void
    {
        if ($homeScore === null || $awayScore === null) {
            Flux::toast(variant: 'danger', text: __('Ambos marcadores son requeridos.'));

            return;
        }

        if ($homeScore < 0 || $homeScore > 99 || $awayScore < 0 || $awayScore > 99) {
            Flux::toast(variant: 'danger', text: __('El marcador debe estar entre 0 y 99.'));

            return;
        }

        $fixture->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'is_completed' => true,
        ]);

        app(ScoringService::class)->scoreMatch($fixture);

        unset($this->fixtures);
        Flux::toast(variant: 'success', text: __('Resultado registrado y puntajes calculados.'));
    }

    public function clearResult(Fixture $fixture): void
    {
        $fixture->update([
            'home_score' => null,
            'away_score' => null,
            'is_completed' => false,
        ]);

        $fixture->predictions()->update([
            'exact_score_points' => null,
            'winner_draw_points' => null,
            'one_team_goals_points' => null,
        ]);

        unset($this->fixtures);
        Flux::toast(variant: 'success', text: __('Resultado eliminado.'));
    }
}; ?>

<div>
    <div class="flex items-center gap-3 mb-6">
        <flux:button x-on:click="window.history.back()" variant="subtle" icon="chevron-left" size="sm" />
        <flux:heading size="xl">{{ __('Registrar resultados') }}</flux:heading>
    </div>

    <div class="space-y-3">
        @foreach ($this->fixtures as $fixture)
            <flux:card class="{{ $fixture->is_completed ? 'border-l-4 border-l-green-500' : '' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading class="text-base">{{ $fixture->homeTeam->name }}</flux:heading>
                            <span class="text-zinc-500 text-xs">vs</span>
                            <flux:heading class="text-base">{{ $fixture->awayTeam->name }}</flux:heading>
                        </div>
                        <flux:text class="text-sm text-zinc-400">
                            {{ $fixture->match_date->format('d/m/Y H:i') }}
                            @if ($fixture->group)
                                &middot; {{ $fixture->group->name }}
                            @endif
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-3" x-data="{
                        home: {{ $fixture->home_score ?? 'null' }},
                        away: {{ $fixture->away_score ?? 'null' }}
                    }">
                        <flux:input
                            x-model="home"
                            type="number"
                            min="0"
                            class="w-16 text-center"
                            placeholder="-"
                        />
                        <span class="text-zinc-500">-</span>
                        <flux:input
                            x-model="away"
                            type="number"
                            min="0"
                            class="w-16 text-center"
                            placeholder="-"
                        />
                        <flux:button
                            variant="primary"
                            size="sm"
                            x-on:click="$wire.saveResult({{ $fixture->id }}, home, away)"
                        >
                            {{ $fixture->is_completed ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                        @if ($fixture->is_completed)
                            <flux:button
                                variant="subtle"
                                size="sm"
                                icon="x-mark"
                                wire:click="clearResult({{ $fixture->id }})"
                            />
                        @endif
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
