<?php

use App\Models\Quiniela;
use App\Models\ScoringRule;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public Quiniela $quiniela;

    public int $pointsExactScore = 3;

    public int $pointsWinnerDraw = 1;

    public int $pointsOneTeamGoals = 0;

    public string $instructions = '';

    public function mount(Quiniela $quiniela): void
    {
        if (auth()->id() !== $quiniela->owner_id) {
            abort(403);
        }

        $this->quiniela = $quiniela;
        $rules = $quiniela->scoringRule;

        if ($rules) {
            $this->pointsExactScore = $rules->points_exact_score;
            $this->pointsWinnerDraw = $rules->points_winner_draw;
            $this->pointsOneTeamGoals = $rules->points_one_team_goals;
            $this->instructions = $rules->instructions ?? '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'pointsExactScore' => 'required|integer|min:0|max:100',
            'pointsWinnerDraw' => 'required|integer|min:0|max:100',
            'pointsOneTeamGoals' => 'required|integer|min:0|max:100',
            'instructions' => 'nullable|string|max:2000',
        ]);

        $rules = $this->quiniela->scoringRule;

        if ($rules) {
            $rules->update([
                'points_exact_score' => $this->pointsExactScore,
                'points_winner_draw' => $this->pointsWinnerDraw,
                'points_one_team_goals' => $this->pointsOneTeamGoals,
                'instructions' => $this->instructions ?: null,
            ]);
        } else {
            ScoringRule::create([
                'quiniela_id' => $this->quiniela->id,
                'points_exact_score' => $this->pointsExactScore,
                'points_winner_draw' => $this->pointsWinnerDraw,
                'points_one_team_goals' => $this->pointsOneTeamGoals,
                'instructions' => $this->instructions ?: null,
            ]);
        }

        Flux::toast(variant: 'success', text: __('Configuración guardada.'));
    }
}; ?>

<div>
    <flux:heading size="lg" class="mb-4">{{ __('Configuración de puntuación') }}</flux:heading>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <flux:card>
            <flux:heading class="mb-6">{{ __('Reglas de puntuación') }}</flux:heading>

            <form wire:submit="save" class="space-y-6">
                <div class="space-y-4">
                    <flux:input wire:model="pointsExactScore" label="Puntos por marcador exacto" type="number" min="0" max="100" />
                    <flux:text class="text-zinc-400 text-sm">
                        Acertar los goles de ambos equipos. Ej: predice 2-1 y el partido queda 2-1.
                    </flux:text>
                </div>

                <div class="space-y-4">
                    <flux:input wire:model="pointsWinnerDraw" label="Puntos por ganador o empate" type="number" min="0" max="100" />
                    <flux:text class="text-zinc-400 text-sm">
                        Acertar quién gana o si es empate, sin importar los goles exactos. Ej: predice 2-0 y queda 1-0 a favor del mismo equipo.
                    </flux:text>
                </div>

                <div class="space-y-4">
                    <flux:input wire:model="pointsOneTeamGoals" label="Puntos por goles de un equipo" type="number" min="0" max="100" />
                    <flux:text class="text-zinc-400 text-sm">
                        Acertar los goles de al menos uno de los dos equipos (sin acertar el marcador exacto). Ej: predice 2-1 y queda 2-0.
                    </flux:text>
                </div>

                <div class="space-y-4">
                    <flux:textarea wire:model="instructions" label="Instrucciones / Bases de competencia" rows="5" maxlength="2000" placeholder="Texto opcional con indicaciones para los participantes..." />
                </div>

                <flux:button type="submit" variant="primary">{{ __('Guardar configuración') }}</flux:button>
            </form>
        </flux:card>

        <flux:card>
            <flux:heading class="mb-4">{{ __('Vista previa (lo que verán los participantes)') }}</flux:heading>

            <div class="space-y-4">
                @if ($pointsExactScore > 0)
                    <div class="p-3 rounded bg-zinc-800">
                        <flux:heading size="base">{{ __('Marcador exacto') }}</flux:heading>
                        <div class="flex justify-between text-sm mt-1">
                            <flux:text>Acertar el marcador completo</flux:text>
                            <flux:badge variant="solid" color="green">{{ $pointsExactScore }} pts</flux:badge>
                        </div>
                    </div>
                @endif

                @if ($pointsWinnerDraw > 0)
                    <div class="p-3 rounded bg-zinc-800">
                        <flux:heading size="base">{{ __('Ganador / Empate') }}</flux:heading>
                        <div class="flex justify-between text-sm mt-1">
                            <flux:text>Acertar el resultado general</flux:text>
                            <flux:badge variant="solid" color="amber">{{ $pointsWinnerDraw }} pts</flux:badge>
                        </div>
                    </div>
                @endif

                @if ($pointsOneTeamGoals > 0)
                    <div class="p-3 rounded bg-zinc-800">
                        <flux:heading size="base">{{ __('Goles de un equipo') }}</flux:heading>
                        <div class="flex justify-between text-sm mt-1">
                            <flux:text>Acertar los goles de un equipo</flux:text>
                            <flux:badge variant="solid" color="blue">{{ $pointsOneTeamGoals }} pts</flux:badge>
                        </div>
                    </div>
                @endif

                @if ($pointsExactScore === 0 && $pointsWinnerDraw === 0 && $pointsOneTeamGoals === 0)
                    <flux:text class="text-zinc-400">{{ __('No hay categorías con puntos configurados.') }}</flux:text>
                @endif

                @if ($instructions)
                    <div class="pt-4 mt-4 border-t border-zinc-700">
                        <flux:heading size="base" class="mb-2">{{ __('Instrucciones') }}</flux:heading>
                        <p class="text-sm text-zinc-300 whitespace-pre-wrap">{{ $instructions }}</p>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>
</div>
