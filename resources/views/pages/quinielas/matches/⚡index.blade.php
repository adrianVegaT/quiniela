<?php

use App\Models\Fixture;
use App\Models\Quiniela;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Quiniela $quiniela;

    public ?string $filterGroup = 'all';

    public string $newHomeTeamId = '';

    public string $newAwayTeamId = '';

    public string $newGroupId = '';

    public string $newMatchDate = '';

    public string $newVenue = '';

    public ?int $editingFixtureId = null;

    public string $editHomeTeamId = '';

    public string $editAwayTeamId = '';

    public string $editGroupId = '';

    public string $editMatchDate = '';

    public string $editVenue = '';

    public string $importText = '';

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
        $query = $this->quiniela->matches()->with(['homeTeam', 'awayTeam', 'group']);

        if ($this->filterGroup !== 'all') {
            if ($this->filterGroup === 'none') {
                $query->whereNull('group_id');
            } else {
                $query->where('group_id', $this->filterGroup);
            }
        }

        return $query->orderBy('match_date', 'asc')->get();
    }

    #[Computed]
    public function groups()
    {
        return $this->quiniela->groups()->orderBy('name')->get();
    }

    #[Computed]
    public function teams()
    {
        return $this->quiniela->teams()->orderBy('name')->get();
    }

    public function addFixture(): void
    {
        $this->validate([
            'newHomeTeamId' => 'required|exists:teams,id',
            'newAwayTeamId' => 'required|exists:teams,id|different:newHomeTeamId',
            'newGroupId' => 'nullable|exists:groups,id',
            'newMatchDate' => 'required|date',
            'newVenue' => 'nullable|string|max:200',
        ]);

        Fixture::create([
            'quiniela_id' => $this->quiniela->id,
            'home_team_id' => $this->newHomeTeamId,
            'away_team_id' => $this->newAwayTeamId,
            'group_id' => $this->newGroupId ?: null,
            'match_date' => $this->newMatchDate,
            'venue' => $this->newVenue ?: null,
        ]);

        $this->reset(['newHomeTeamId', 'newAwayTeamId', 'newGroupId', 'newMatchDate', 'newVenue']);
        unset($this->fixtures);
        Flux::toast(variant: 'success', text: __('Partido agregado.'));
    }

    public function startEdit(Fixture $fixture): void
    {
        $this->editingFixtureId = $fixture->id;
        $this->editHomeTeamId = (string) $fixture->home_team_id;
        $this->editAwayTeamId = (string) $fixture->away_team_id;
        $this->editGroupId = (string) ($fixture->group_id ?? '');
        $this->editMatchDate = $fixture->match_date->format('Y-m-d\TH:i');
        $this->editVenue = $fixture->venue ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingFixtureId = null;
    }

    public function updateFixture(Fixture $fixture): void
    {
        $this->validate([
            'editHomeTeamId' => 'required|exists:teams,id',
            'editAwayTeamId' => 'required|exists:teams,id|different:editHomeTeamId',
            'editGroupId' => 'nullable|exists:groups,id',
            'editMatchDate' => 'required|date',
            'editVenue' => 'nullable|string|max:200',
        ]);

        $fixture->update([
            'home_team_id' => $this->editHomeTeamId,
            'away_team_id' => $this->editAwayTeamId,
            'group_id' => $this->editGroupId ?: null,
            'match_date' => $this->editMatchDate,
            'venue' => $this->editVenue ?: null,
        ]);

        $this->editingFixtureId = null;
        unset($this->fixtures);
        Flux::toast(variant: 'success', text: __('Partido actualizado.'));
    }

    public function deleteFixture(Fixture $fixture): void
    {
        if ($fixture->predictions()->exists()) {
            Flux::toast(variant: 'danger', text: __('No se puede eliminar: el partido tiene predicciones.'));

            return;
        }

        $fixture->delete();
        unset($this->fixtures);
        Flux::toast(variant: 'success', text: __('Partido eliminado.'));
    }

    public function importFixtures(): void
    {
        $this->validate(['importText' => 'required|string']);

        $lines = array_filter(explode("\n", trim($this->importText)));
        $teams = $this->quiniela->teams()->orderBy('name')->get();
        $groups = $this->quiniela->groups()->orderBy('name')->get();
        $created = 0;
        $errors = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if (! preg_match('/^\/(.+?)\/(.+?)\/(\d{2}-\d{2}-\d{4}\s\d{2}:\d{2}:\d{2})(?:\/(.+))?$/', $line, $matches)) {
                $errors[] = "Línea ".($lineNumber + 1).": formato inválido";

                continue;
            }

            $homeName = trim($matches[1]);
            $awayName = trim($matches[2]);
            $dateStr = $matches[3];
            $groupName = isset($matches[4]) ? trim($matches[4]) : null;

            $homeTeam = $teams->first(fn ($t) => mb_strtolower($t->name) === mb_strtolower($homeName));
            $awayTeam = $teams->first(fn ($t) => mb_strtolower($t->name) === mb_strtolower($awayName));

            if (! $homeTeam) {
                $errors[] = "Línea ".($lineNumber + 1).": equipo no encontrado — {$homeName}";

                continue;
            }
            if (! $awayTeam) {
                $errors[] = "Línea ".($lineNumber + 1).": equipo no encontrado — {$awayName}";

                continue;
            }
            if ($homeTeam->id === $awayTeam->id) {
                $errors[] = "Línea ".($lineNumber + 1).": equipo local y visitante son el mismo";

                continue;
            }

            $groupId = null;
            if ($groupName) {
                $group = $groups->first(fn ($g) => mb_strtolower($g->name) === mb_strtolower($groupName));
                if (! $group) {
                    $errors[] = "Línea ".($lineNumber + 1).": grupo no encontrado — {$groupName}";

                    continue;
                }
                $groupId = $group->id;
            }

            try {
                $date = \Illuminate\Support\Carbon::createFromFormat('d-m-Y H:i:s', $dateStr);
            } catch (\Exception $e) {
                $errors[] = "Línea ".($lineNumber + 1).": fecha inválida — {$dateStr}";

                continue;
            }

            $exists = Fixture::where('quiniela_id', $this->quiniela->id)
                ->where('home_team_id', $homeTeam->id)
                ->where('away_team_id', $awayTeam->id)
                ->where('match_date', $date)
                ->exists();

            if ($exists) {
                continue;
            }

            Fixture::create([
                'quiniela_id' => $this->quiniela->id,
                'home_team_id' => $homeTeam->id,
                'away_team_id' => $awayTeam->id,
                'group_id' => $groupId,
                'match_date' => $date,
            ]);

            $created++;
        }

        $this->importText = '';
        unset($this->fixtures);

        if ($created > 0) {
            Flux::toast(variant: 'success', text: "{$created} partidos importados.");
        }

        if (! empty($errors)) {
            foreach ($errors as $error) {
                Flux::toast(variant: 'danger', text: $error);
            }
        }
    }
}; ?>

<div>
    <div class="flex justify-between items-center mb-4">
        <flux:heading size="lg">{{ __('Partidos') }}</flux:heading>
        <div>
        <flux:select wire:model.live="filterGroup" class="w-auto">
            <option value="all">Todos los grupos</option>
            <option value="none">Sin grupo</option>
            @foreach ($this->groups as $group)
                <option value="{{ $group->id }}">{{ $group->name }}</option>
            @endforeach
        </flux:select>
        </div>
    </div>

    <div x-data="{ open: false }" class="mb-6">
        <button type="button" x-on:click="open = !open"
            class="flex items-center gap-2 text-sm text-zinc-400 hover:text-zinc-200 transition">
            <svg class="size-4 transition-transform" ::class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span>{{ __('Importar partidos') }}</span>
        </button>
        <div x-show="open" x-collapse class="mt-3">
            <flux:card>
                <flux:heading size="base" class="mb-2">{{ __('Importación masiva') }}</flux:heading>
                <flux:text class="mb-3 text-zinc-400 text-sm">
                    Pega los partidos en formato <code class="text-zinc-300">/EquipoA/EquipoB/DD-MM-AAAA HH:MM:SS</code>, uno por línea.
                </flux:text>
                <form wire:submit="importFixtures" class="space-y-3">
                    <flux:textarea wire:model="importText" rows="8"
                        class="font-mono"
                        placeholder="/México/Sudáfrica/11-06-2026 13:00:00&#10;/Brasil/Marruecos/13-06-2026 16:00:00" />
                    <flux:button type="submit" variant="primary">{{ __('Importar') }}</flux:button>
                </form>
            </flux:card>
        </div>
    </div>

    <flux:card class="mb-6">
        <flux:heading class="mb-4">{{ __('Agregar partido') }}</flux:heading>
        <form wire:submit="addFixture" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:select wire:model="newHomeTeamId" label="Equipo local">
                    <option value="">Seleccionar...</option>
                    @foreach ($this->teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="newAwayTeamId" label="Equipo visitante">
                    <option value="">Seleccionar...</option>
                    @foreach ($this->teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="newGroupId" label="Grupo (opcional)">
                    <option value="">Sin grupo</option>
                    @foreach ($this->groups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="newMatchDate" label="Fecha y hora" type="datetime-local" />
                <flux:input wire:model="newVenue" label="Sede (opcional)" placeholder="Estadio XYZ" maxlength="200" />
                <div class="flex items-end">
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Agregar partido') }}</flux:button>
                </div>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading class="mb-4">{{ __('Partidos registrados') }} ({{ $this->fixtures->count() }})</flux:heading>

        @if ($this->fixtures->isEmpty())
            <flux:text class="text-zinc-400">{{ __('No hay partidos registrados.') }}</flux:text>
        @else
            <div class="divide-y divide-zinc-700">
                @foreach ($this->fixtures as $fixture)
                    <div class="py-3 first:pt-0 last:pb-0">
                        @if ($editingFixtureId === $fixture->id)
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <flux:select wire:model="editHomeTeamId">
                                    @foreach ($this->teams as $team)
                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="editAwayTeamId">
                                    @foreach ($this->teams as $team)
                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="editMatchDate" type="datetime-local" />
                                <div class="flex gap-2">
                                    <flux:button wire:click="updateFixture({{ $fixture->id }})" variant="primary" size="sm" icon="check" />
                                    <flux:button wire:click="cancelEdit" variant="subtle" size="sm" icon="x-mark" />
                                </div>
                            </div>
                        @else
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    @if ($fixture->is_completed)
                                        <flux:badge variant="solid" color="green" size="sm">&#10003;</flux:badge>
                                    @else
                                        <flux:badge variant="solid" color="zinc" size="sm">Pend</flux:badge>
                                    @endif
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <flux:heading class="text-sm">{{ $fixture->homeTeam->name }}</flux:heading>
                                            <span class="text-zinc-500 text-xs">vs</span>
                                            <flux:heading class="text-sm">{{ $fixture->awayTeam->name }}</flux:heading>
                                        </div>
                                        <div class="flex gap-2 text-xs text-zinc-400 mt-0.5">
                                            <span>{{ $fixture->match_date->format('d/m/Y H:i') }}</span>
                                            @if ($fixture->group)
                                                <span>&middot; {{ $fixture->group->name }}</span>
                                            @endif
                                            @if ($fixture->venue)
                                                <span>&middot; {{ $fixture->venue }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-1">
                                    <flux:button wire:click="startEdit({{ $fixture->id }})" variant="subtle" size="sm" icon="pencil" />
                                    <flux:button wire:click="deleteFixture({{ $fixture->id }})" wire:confirm="¿Eliminar este partido?" variant="subtle" size="sm" icon="trash" />
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>
</div>
