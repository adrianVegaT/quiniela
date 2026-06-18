<?php

use App\Models\Quiniela;
use App\Models\User;
use App\Services\PredictionService;
use App\Services\ScoringService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Quiniela $quiniela;

    public User $targetUser;

    public ?int $editingPredictionId = null;

    public ?int $editHomeScore = null;

    public ?int $editAwayScore = null;

    public string $editReason = '';

    public array $pendingMatches = [];

    public function mount(Quiniela $quiniela, User $user): void
    {
        $isOwner = auth()->id() === $quiniela->owner_id;
        $isSelf = auth()->id() === $user->id;
        $canView = app(PredictionService::class)->canViewOtherPredictions($quiniela, auth()->user());

        if (! $isOwner && ! $isSelf && ! $canView) {
            abort(403);
        }

        $this->quiniela = $quiniela;
        $this->targetUser = $user;
        $this->loadPendingMatches();
    }

    protected function loadPendingMatches(): void
    {
        $this->pendingMatches = $this->quiniela->matches()
            ->where('is_completed', false)
            ->with(['homeTeam', 'awayTeam', 'group'])
            ->orderBy('match_date')
            ->get()
            ->map(function ($fixture) {
                $prediction = $fixture->predictions()
                    ->where('user_id', $this->targetUser->id)
                    ->first();

                return [
                    'match_id' => $fixture->id,
                    'prediction_id' => $prediction?->id,
                    'match_date' => $fixture->match_date->format('d/m/Y H:i'),
                    'home_team' => $fixture->homeTeam->name,
                    'away_team' => $fixture->awayTeam->name,
                    'home_score' => $prediction?->home_score,
                    'away_score' => $prediction?->away_score,
                    'has_prediction' => $prediction !== null,
                ];
            })->toArray();
    }

    #[Computed]
    public function isOwner(): bool
    {
        return auth()->id() === $this->quiniela->owner_id;
    }

    #[Computed]
    public function isReadOnly(): bool
    {
        return ! $this->isOwner && auth()->id() !== $this->targetUser->id;
    }

    #[Computed]
    public function canViewOthers(): bool
    {
        return app(PredictionService::class)->canViewOtherPredictions($this->quiniela, auth()->user());
    }

    #[Computed]
    public function rows(): array
    {
        $matches = $this->quiniela->matches()
            ->where('is_completed', true)
            ->with(['homeTeam', 'awayTeam', 'group'])
            ->orderBy('match_date')
            ->get();

        return $matches->map(function ($fixture) {
            $prediction = $fixture->predictions()
                ->where('user_id', $this->targetUser->id)
                ->first();

            $total = ($prediction->exact_score_points ?? 0)
                + ($prediction->winner_draw_points ?? 0)
                + ($prediction->one_team_goals_points ?? 0);

            return [
                'prediction_id' => $prediction?->id,
                'match_date' => $fixture->match_date->format('d/m/Y H:i'),
                'home_team' => $fixture->homeTeam->name,
                'away_team' => $fixture->awayTeam->name,
                'result' => $fixture->home_score.'-'.$fixture->away_score,
                'prediction' => $prediction ? $prediction->home_score.'-'.$prediction->away_score : '-',
                'home_score' => $prediction?->home_score,
                'away_score' => $prediction?->away_score,
                'exact' => $prediction?->exact_score_points > 0,
                'winner' => $prediction?->winner_draw_points > 0,
                'goals' => $prediction?->one_team_goals_points > 0,
                'total_points' => $total,
            ];
        })->toArray();
    }

    #[Computed]
    public function stats(): array
    {
        $leaderboard = app(ScoringService::class)->leaderboard($this->quiniela);
        $myRow = collect($leaderboard['rows'])->firstWhere('user.id', $this->targetUser->id);

        return $myRow ?? ['exact_score_count' => 0, 'winner_draw_count' => 0, 'one_team_goals_count' => 0, 'total_points' => 0];
    }

    public function startEdit(int $predictionId, int $homeScore, int $awayScore): void
    {
        if (auth()->id() !== $this->quiniela->owner_id) {
            return;
        }

        $this->editingPredictionId = $predictionId;
        $this->editHomeScore = $homeScore;
        $this->editAwayScore = $awayScore;
        $this->editReason = '';
    }

    public function cancelEdit(): void
    {
        $this->editingPredictionId = null;
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editHomeScore' => 'required|integer|min:0',
            'editAwayScore' => 'required|integer|min:0',
            'editReason' => 'required|string|min:5|max:500',
        ]);

        $prediction = \App\Models\Prediction::findOrFail($this->editingPredictionId);

        app(PredictionService::class)->adminEditPrediction(
            $prediction,
            auth()->user(),
            $this->editHomeScore,
            $this->editAwayScore,
            $this->editReason,
        );

        $this->editingPredictionId = null;
        unset($this->rows);
        Flux::toast(variant: 'success', text: __('Predicción editada y registrada en el log.'));
    }

    public function savePending(int $matchId, string $reason = ''): void
    {
        if (auth()->id() !== $this->quiniela->owner_id) {
            return;
        }

        if (mb_strlen(trim($reason)) < 5) {
            Flux::toast(variant: 'danger', text: __('El motivo debe tener al menos 5 caracteres.'));

            return;
        }

        $idx = collect($this->pendingMatches)->search(fn ($m) => $m['match_id'] === $matchId);

        if ($idx === false) {
            return;
        }

        $data = $this->pendingMatches[$idx];

        $this->validate([
            'pendingMatches.'.$idx.'.home_score' => 'required|integer|min:0',
            'pendingMatches.'.$idx.'.away_score' => 'required|integer|min:0',
        ]);

        $fixture = \App\Models\Fixture::findOrFail($matchId);

        $prediction = \App\Models\Prediction::updateOrCreate(
            ['match_id' => $matchId, 'user_id' => $this->targetUser->id],
            [
                'home_score' => $data['home_score'],
                'away_score' => $data['away_score'],
                'is_partial' => false,
            ]
        );

        app(PredictionService::class)->adminEditPrediction(
            $prediction,
            auth()->user(),
            $data['home_score'],
            $data['away_score'],
            $reason,
        );

        $this->loadPendingMatches();
        Flux::toast(variant: 'success', text: __('Predicción guardada para el usuario.'));
    }
}; ?>

<div>
    <div class="flex items-center gap-3 mb-6">
        <flux:button x-on:click="window.history.back()" variant="subtle" icon="chevron-left" size="sm" />
        <flux:heading size="xl">{{ __('Predicciones de') }} {{ $targetUser->name }}</flux:heading>
        @if ($this->isOwner || auth()->id() === $targetUser->id || $this->canViewOthers)
            <flux:button :href="route('quinielas.users.pdf', [$quiniela, $targetUser])" variant="outline" size="sm" icon="printer" target="_blank">
                {{ __('Imprimir boleta') }}
            </flux:button>
        @endif
    </div>

    @if ($this->isOwner)
        <flux:card class="mb-6 bg-amber-900/20 border border-amber-700/50">
            <flux:text class="text-amber-300 text-sm">{{ __('Vista de administrador: puedes editar estas predicciones. Cada cambio queda registrado en el log.') }}</flux:text>
        </flux:card>
    @endif

    <div class="flex gap-4 mb-6">
        <flux:card class="flex-1">
            <flux:text class="text-zinc-400 text-sm">Puntaje total</flux:text>
            <flux:heading size="xl">{{ $this->stats['total_points'] }}</flux:heading>
        </flux:card>
        <flux:card class="flex-1">
            <flux:text class="text-zinc-400 text-sm">Exactos</flux:text>
            <flux:heading size="xl">{{ $this->stats['exact_score_count'] }}</flux:heading>
        </flux:card>
        <flux:card class="flex-1">
            <flux:text class="text-zinc-400 text-sm">Ganador</flux:text>
            <flux:heading size="xl">{{ $this->stats['winner_draw_count'] }}</flux:heading>
        </flux:card>
        <flux:card class="flex-1">
            <flux:text class="text-zinc-400 text-sm">Goles</flux:text>
            <flux:heading size="xl">{{ $this->stats['one_team_goals_count'] }}</flux:heading>
        </flux:card>
    </div>

    @if ($this->isOwner && ! empty($this->pendingMatches))
        <flux:heading size="lg" class="mb-4">{{ __('Partidos pendientes (llenar por el usuario)') }}</flux:heading>
        <div class="space-y-3 mb-8">
            @foreach ($this->pendingMatches as $idx => $match)
                <flux:card>
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <flux:heading class="text-base">{{ $match['home_team'] }}</flux:heading>
                                <span class="text-zinc-500 text-xs">vs</span>
                                <flux:heading class="text-base">{{ $match['away_team'] }}</flux:heading>
                            </div>
                            <flux:text class="text-sm text-zinc-400">{{ $match['match_date'] }}</flux:text>
                        </div>
                        <div class="flex items-center gap-3" x-data="{ reason: '' }">
                            <flux:input
                                wire:model="pendingMatches.{{ $idx }}.home_score"
                                type="number"
                                min="0"
                                class="w-16 text-center"
                                placeholder="-"
                            />
                            <span class="text-zinc-500">-</span>
                            <flux:input
                                wire:model="pendingMatches.{{ $idx }}.away_score"
                                type="number"
                                min="0"
                                class="w-16 text-center"
                                placeholder="-"
                            />
                            <flux:input
                                x-model="reason"
                                placeholder="Motivo del cambio"
                                class="w-48"
                                maxlength="500"
                            />
                            <flux:button x-on:click="$wire.savePending({{ $match['match_id'] }}, reason)" variant="primary" size="sm">
                                {{ __('Guardar') }}
                            </flux:button>
                        </div>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    <flux:heading size="lg" class="mb-4">{{ __('Partidos completados') }}</flux:heading>

    @if (empty($this->rows))
        <flux:card>
            <flux:text class="text-zinc-400">{{ __('No hay partidos completados aún.') }}</flux:text>
        </flux:card>
    @else
        <flux:card class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-zinc-400 text-left border-b border-zinc-700">
                        <th class="pb-3 pr-4">{{ __('Partido') }}</th>
                        <th class="pb-3 pr-4 text-center">{{ __('Predicción') }}</th>
                        <th class="pb-3 pr-4 text-center">{{ __('Resultado') }}</th>
                        <th class="pb-3 pr-4 text-center">M</th>
                        <th class="pb-3 pr-4 text-center">G</th>
                        <th class="pb-3 pr-4 text-center">Eq</th>
                        <th class="pb-3 pr-4 text-right">{{ __('Pts') }}</th>
                        @if ($this->isOwner)
                            <th class="pb-3 pr-4 text-right"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-700">
                    @foreach ($this->rows as $row)
                        <tr>
                            <td class="py-3 pr-4">
                                <div>{{ $row['home_team'] }} vs {{ $row['away_team'] }}</div>
                                <div class="text-xs text-zinc-500">{{ $row['match_date'] }}</div>
                            </td>
                            <td class="py-3 pr-4 text-center font-mono">
                                @if ($editingPredictionId === $row['prediction_id'])
                                    <div class="flex flex-col gap-2">
                                        <div class="flex items-center gap-2 justify-center">
                                            <flux:input wire:model="editHomeScore" type="number" min="0" class="w-14 text-center" />
                                            <span>-</span>
                                            <flux:input wire:model="editAwayScore" type="number" min="0" class="w-14 text-center" />
                                        </div>
                                        <flux:input wire:model="editReason" placeholder="Motivo (mín 5 chars)" class="w-40" maxlength="500" />
                                        <div class="flex gap-1 justify-center">
                                            <flux:button wire:click="saveEdit" variant="primary" size="sm" icon="check" />
                                            <flux:button wire:click="cancelEdit" variant="subtle" size="sm" icon="x-mark" />
                                        </div>
                                    </div>
                                @else
                                    {{ $row['prediction'] }}
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-center font-mono">{{ $row['result'] }}</td>
                            <td class="py-3 pr-4 text-center">
                                <flux:badge variant="solid" size="sm" :color="$row['exact'] ? 'green' : 'zinc'">
                                    {{ $row['exact'] ? '✓' : '-' }}
                                </flux:badge>
                            </td>
                            <td class="py-3 pr-4 text-center">
                                <flux:badge variant="solid" size="sm" :color="$row['winner'] ? 'amber' : 'zinc'">
                                    {{ $row['winner'] ? '✓' : '-' }}
                                </flux:badge>
                            </td>
                            <td class="py-3 pr-4 text-center">
                                <flux:badge variant="solid" size="sm" :color="$row['goals'] ? 'blue' : 'zinc'">
                                    {{ $row['goals'] ? '✓' : '-' }}
                                </flux:badge>
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <flux:badge variant="solid" color="green" size="sm">{{ $row['total_points'] }}</flux:badge>
                            </td>
                            @if ($this->isOwner && $row['prediction_id'])
                                <td class="py-3 pr-4 text-right">
                                    <flux:button
                                        wire:click="startEdit({{ $row['prediction_id'] }}, {{ $row['home_score'] ?? 0 }}, {{ $row['away_score'] ?? 0 }})"
                                        variant="subtle"
                                        size="sm"
                                        icon="pencil"
                                    />
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </flux:card>
    @endif
</div>
