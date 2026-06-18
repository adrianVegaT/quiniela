<?php

use App\Models\Quiniela;
use App\Models\Team;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Quiniela $quiniela;

    public string $newTeamName = '';

    public ?int $editingTeamId = null;

    public string $editingTeamName = '';

    public string $bulkNames = '';

    public function mount(Quiniela $quiniela): void
    {
        if (auth()->id() !== $quiniela->owner_id) {
            abort(403);
        }

        $this->quiniela = $quiniela;
    }

    #[Computed]
    public function teams()
    {
        return $this->quiniela->teams()->orderBy('name')->get();
    }

    public function addTeam(): void
    {
        $this->validate(['newTeamName' => 'required|string|max:100']);

        Team::create([
            'quiniela_id' => $this->quiniela->id,
            'name' => trim($this->newTeamName),
        ]);

        $this->newTeamName = '';
        unset($this->teams);
        Flux::toast(variant: 'success', text: __('Equipo agregado.'));
    }

    public function startEdit(Team $team): void
    {
        $this->editingTeamId = $team->id;
        $this->editingTeamName = $team->name;
    }

    public function cancelEdit(): void
    {
        $this->editingTeamId = null;
        $this->editingTeamName = '';
    }

    public function updateTeam(Team $team): void
    {
        $this->validate(['editingTeamName' => 'required|string|max:100']);

        $team->update(['name' => trim($this->editingTeamName)]);

        $this->editingTeamId = null;
        unset($this->teams);
        Flux::toast(variant: 'success', text: __('Equipo actualizado.'));
    }

    public function deleteTeam(Team $team): void
    {
        if ($team->homeMatches()->exists() || $team->awayMatches()->exists()) {
            Flux::toast(variant: 'danger', text: __('No se puede eliminar: el equipo tiene partidos registrados.'));

            return;
        }

        $team->delete();
        unset($this->teams);
        Flux::toast(variant: 'success', text: __('Equipo eliminado.'));
    }

    public function bulkAdd(): void
    {
        $this->validate(['bulkNames' => 'required|string']);

        $names = array_filter(array_map('trim', explode("\n", $this->bulkNames)));

        $count = 0;
        foreach ($names as $name) {
            if (! empty($name) && ! Team::where('quiniela_id', $this->quiniela->id)->where('name', $name)->exists()) {
                Team::create([
                    'quiniela_id' => $this->quiniela->id,
                    'name' => $name,
                ]);
                $count++;
            }
        }

        $this->bulkNames = '';
        unset($this->teams);
        Flux::toast(variant: 'success', text: "{$count} equipos agregados.");
    }
}; ?>

<div>
    <flux:heading size="lg" class="mb-4">{{ __('Equipos') }}</flux:heading>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div>
            <flux:card>
                <flux:heading class="mb-4">{{ __('Agregar equipo') }}</flux:heading>
                <form wire:submit="addTeam" class="flex gap-3 items-end">
                    <flux:input wire:model="newTeamName" label="Nombre del equipo" placeholder="Argentina" maxlength="100" class="flex-1" />
                    <flux:button type="submit" variant="primary">{{ __('Agregar') }}</flux:button>
                </form>
            </flux:card>

            <flux:card class="mt-4">
                <flux:heading class="mb-4">{{ __('Carga masiva') }}</flux:heading>
                <form wire:submit="bulkAdd" class="space-y-4">
                    <flux:textarea wire:model="bulkNames" label="Nombres (uno por línea)" rows="6" placeholder="Argentina&#10;Brasil&#10;Francia" />
                    <flux:button type="submit" variant="outline">{{ __('Agregar todos') }}</flux:button>
                </form>
            </flux:card>
        </div>

        <div>
            <flux:card>
                <flux:heading class="mb-4">{{ __('Equipos registrados') }} ({{ $this->teams->count() }})</flux:heading>

                @if ($this->teams->isEmpty())
                    <flux:text class="text-zinc-400">{{ __('No hay equipos registrados aún.') }}</flux:text>
                @else
                    <div class="divide-y divide-zinc-700">
                        @foreach ($this->teams as $team)
                            <div class="py-3 first:pt-0 last:pb-0">
                                @if ($editingTeamId === $team->id)
                                    <div class="flex gap-2">
                                        <flux:input wire:model="editingTeamName" class="flex-1" />
                                        <flux:button wire:click="updateTeam({{ $team->id }})" variant="primary" size="sm" icon="check" />
                                        <flux:button wire:click="cancelEdit" variant="subtle" size="sm" icon="x-mark" />
                                    </div>
                                @else
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-2">
                                            <flux:heading>{{ $team->name }}</flux:heading>
                                            @if ($team->groups()->exists())
                                                <flux:badge variant="subtle" size="sm">
                                                    {{ $team->groups->pluck('name')->join(', ') }}
                                                </flux:badge>
                                            @endif
                                        </div>
                                        <div class="flex gap-1">
                                            <flux:button wire:click="startEdit({{ $team->id }})" variant="subtle" size="sm" icon="pencil" />
                                            <flux:button wire:click="deleteTeam({{ $team->id }})" wire:confirm="¿Eliminar este equipo?" variant="subtle" size="sm" icon="trash" />
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>
