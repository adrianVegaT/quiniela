<?php

use App\Models\Quiniela;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reglas de puntuación')] class extends Component
{
    public Quiniela $quiniela;

    public function mount(Quiniela $quiniela): void
    {
        $this->quiniela = $quiniela;
    }
}; ?>

<div>
    <div class="flex items-center gap-3 mb-6">
        <flux:button x-on:click="window.history.back()" variant="subtle" icon="chevron-left" size="sm" />
        <flux:heading size="xl">{{ __('Reglas de puntuación') }}</flux:heading>
    </div>

    @php $rules = $quiniela->scoringRule; @endphp

    <div class="max-w-lg space-y-4 mb-8">
        @if ($rules && $rules->points_exact_score > 0)
            <flux:card>
                <flux:heading size="lg" class="mb-2">{{ __('Marcador exacto') }}</flux:heading>
                <flux:text>
                    Acertar el marcador completo (goles de ambos equipos).
                </flux:text>
                <div class="mt-2">
                    <flux:badge variant="solid" color="green" size="lg">{{ $rules->points_exact_score }} puntos</flux:badge>
                </div>
                <flux:text class="text-zinc-400 text-sm mt-2">
                    Ejemplo: predices 2-1 y el partido queda 2-1.
                </flux:text>
            </flux:card>
        @endif

        @if ($rules && $rules->points_winner_draw > 0)
            <flux:card>
                <flux:heading size="lg" class="mb-2">{{ __('Ganador / Empate') }}</flux:heading>
                <flux:text>
                    Acertar quién gana o si es empate, sin importar los goles exactos.
                </flux:text>
                <div class="mt-2">
                    <flux:badge variant="solid" color="amber" size="lg">{{ $rules->points_winner_draw }} puntos</flux:badge>
                </div>
                <flux:text class="text-zinc-400 text-sm mt-2">
                    Ejemplo: predices 2-0 y queda 1-0 a favor del mismo equipo.
                </flux:text>
            </flux:card>
        @endif

        @if ($rules && $rules->points_one_team_goals > 0)
            <flux:card>
                <flux:heading size="lg" class="mb-2">{{ __('Goles de un equipo') }}</flux:heading>
                <flux:text>
                    Acertar los goles de al menos uno de los dos equipos (sin acertar el marcador exacto).
                </flux:text>
                <div class="mt-2">
                    <flux:badge variant="solid" color="blue" size="lg">{{ $rules->points_one_team_goals }} puntos</flux:badge>
                </div>
                <flux:text class="text-zinc-400 text-sm mt-2">
                    Ejemplo: predices 2-1 y queda 2-0 (acertaste los 2 goles del local). Si aciertas ambos, ya es marcador exacto.
                </flux:text>
            </flux:card>
        @endif

        @if (! $rules || ($rules->points_exact_score === 0 && $rules->points_winner_draw === 0 && $rules->points_one_team_goals === 0))
            <flux:card>
                <flux:text class="text-zinc-400">{{ __('El administrador aún no ha configurado las reglas de puntuación.') }}</flux:text>
            </flux:card>
        @endif
    </div>

    @if ($rules && $rules->instructions)
        <flux:card class="max-w-lg">
            <flux:heading size="lg" class="mb-3">{{ __('Instrucciones / Bases de competencia') }}</flux:heading>
            <div class="text-sm text-zinc-300 whitespace-pre-wrap">{{ $rules->instructions }}</div>
        </flux:card>
    @endif
</div>
