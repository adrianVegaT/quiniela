<?php

use App\Models\Group;
use App\Models\Quiniela;
use App\Models\Team;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Quiniela $quiniela;

    public string $newGroupName = '';

    public function mount(Quiniela $quiniela): void
    {
        if (auth()->id() !== $quiniela->owner_id) {
            abort(403);
        }

        $this->quiniela = $quiniela;
    }

    #[Computed]
    public function groups()
    {
        return $this->quiniela->groups()->with('teams')->get();
    }

    #[Computed]
    public function unassignedTeams()
    {
        return Team::where('quiniela_id', $this->quiniela->id)
            ->whereDoesntHave('groups')
            ->orderBy('name')
            ->get();
    }

    public function addGroup(): void
    {
        $this->validate(['newGroupName' => 'required|string|max:10']);

        Group::create([
            'quiniela_id' => $this->quiniela->id,
            'name' => trim($this->newGroupName),
        ]);

        $this->newGroupName = '';
        unset($this->groups);
        Flux::toast(variant: 'success', text: __('Grupo creado.'));
    }

    public function deleteGroup(Group $group): void
    {
        if ($group->matches()->exists()) {
            Flux::toast(variant: 'danger', text: __('No se puede eliminar: el grupo tiene partidos asociados.'));

            return;
        }

        $group->teams()->detach();
        $group->delete();
        unset($this->groups);
        Flux::toast(variant: 'success', text: __('Grupo eliminado.'));
    }

    public function assignTeam(Group $group, int $teamId): void
    {
        $team = Team::findOrFail($teamId);

        if ($team->quiniela_id !== $this->quiniela->id) {
            return;
        }

        if ($group->teams()->where('team_id', $teamId)->exists()) {
            return;
        }

        $group->teams()->attach($teamId);
        unset($this->groups, $this->unassignedTeams);
        Flux::toast(variant: 'success', text: "{$team->name} asignado a {$group->name}.");
    }

    public function unassignTeam(Group $group, int $teamId): void
    {
        $group->teams()->detach($teamId);
        unset($this->groups, $this->unassignedTeams);
        Flux::toast(variant: 'success', text: __('Equipo removido del grupo.'));
    }
}; ?>

<div>
    <div class="flex justify-between items-center mb-4">
        <flux:heading size="lg">{{ __('Grupos') }}</flux:heading>
    </div>

    <flux:card class="mb-4 max-w-md">
        <form wire:submit="addGroup" class="flex gap-3 items-end">
            <flux:input wire:model="newGroupName" label="Nombre del grupo" placeholder="Grupo A" maxlength="10" class="flex-1" />
            <flux:button type="submit" variant="primary">{{ __('Crear') }}</flux:button>
        </form>
    </flux:card>

    @if ($this->groups->isEmpty())
        <flux:card>
            <flux:text class="text-zinc-400">{{ __('No hay grupos creados. Crea grupos para organizar los equipos.') }}</flux:text>
        </flux:card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($this->groups as $group)
                <flux:card>
                    <div class="flex justify-between items-start mb-3">
                        <flux:heading>{{ $group->name }}</flux:heading>
                        <flux:button wire:click="deleteGroup({{ $group->id }})" wire:confirm="¿Eliminar {{ $group->name }}?" variant="subtle" size="sm" icon="trash" />
                    </div>

                    <div class="space-y-2 mb-3">
                        @foreach ($group->teams as $team)
                            <div class="flex justify-between items-center text-sm py-1 px-2 rounded bg-zinc-800">
                                <span>{{ $team->name }}</span>
                                <flux:button wire:click="unassignTeam({{ $group->id }}, {{ $team->id }})" variant="subtle" size="sm" icon="x-mark" />
                            </div>
                        @endforeach

                        @if ($group->teams->isEmpty())
                            <flux:text class="text-zinc-500 text-sm italic">Sin equipos</flux:text>
                        @endif
                    </div>

                    @if ($this->unassignedTeams->isNotEmpty())
                        <div class="pt-3 border-t border-zinc-700" wire:ignore x-data="{
                            open: false,
                            search: '',
                            highlightIndex: -1,
                            allTeams: {{ json_encode($this->unassignedTeams->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values()->toArray()) }},
                            filtered: [],
                            init() {
                                this.filtered = this.allTeams
                                var self = this
                                this.$watch('search', function(value) {
                                    self.highlightIndex = -1
                                    var s = value.toLowerCase()
                                    self.filtered = self.allTeams.filter(function(t) { return t.name.toLowerCase().includes(s) })
                                })
                            },
                            selectTeam(team) {
                                $wire.assignTeam({{ $group->id }}, team.id)
                                this.open = false
                                this.search = ''
                                this.highlightIndex = -1
                            },
                            toggle() {
                                this.open = !this.open
                                this.highlightIndex = -1
                                if (this.open) {
                                    var self = this
                                    this.$nextTick(function() {
                                        var input = self.$refs.searchInput
                                        if (input) { input.focus(); input.select() }
                                    })
                                }
                            },
                            onKeydown(event) {
                                if (this.filtered.length === 0) return
                                if (event.key === 'ArrowDown') {
                                    event.preventDefault()
                                    this.updateHighlight(this.highlightIndex < this.filtered.length - 1 ? this.highlightIndex + 1 : 0)
                                } else if (event.key === 'ArrowUp') {
                                    event.preventDefault()
                                    this.updateHighlight(this.highlightIndex > 0 ? this.highlightIndex - 1 : this.filtered.length - 1)
                                } else if (event.key === 'Enter') {
                                    if (this.highlightIndex >= 0) {
                                        event.preventDefault()
                                        var team = this.filtered[this.highlightIndex]
                                        if (team) this.selectTeam(team)
                                    }
                                } else if (event.key === 'Escape') {
                                    this.open = false
                                    this.updateHighlight(-1)
                                }
                            },
                            updateHighlight(newIndex) {
                                var items = this.$refs.list.querySelectorAll('[data-team-item]')
                                if (!items.length) { this.highlightIndex = newIndex; return }
                                if (this.highlightIndex >= 0 && this.highlightIndex < items.length) {
                                    items[this.highlightIndex].style.removeProperty('background-color')
                                }
                                this.highlightIndex = newIndex
                                if (this.highlightIndex >= 0 && this.highlightIndex < items.length) {
                                    items[this.highlightIndex].style.backgroundColor = '#2563eb'
                                    items[this.highlightIndex].scrollIntoView({ block: 'nearest' })
                                }
                            },
                        }" x-on:click.outside="open = false">
                            <button
                                type="button"
                                x-on:click="toggle()"
                                class="w-full flex items-center justify-between text-sm bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-zinc-400 hover:text-zinc-200 hover:border-zinc-600 transition"
                            >
                                <span>{{ __('Agregar equipo...') }}</span>
                                <svg x-show="!open" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                <svg x-cloak x-show="open" class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                            </button>

                            <div x-cloak x-show="open" x-transition class="mt-1 border border-zinc-700 rounded-lg bg-zinc-800 shadow-lg overflow-hidden">
                                <div class="p-2 border-b border-zinc-700">
                                    <input
                                        type="text"
                                        x-ref="searchInput"
                                        x-model="search"
                                        placeholder="{{ __('Buscar equipo...') }}"
                                        class="w-full text-sm bg-zinc-900 border border-zinc-700 rounded-md px-2.5 py-1.5 text-zinc-200 placeholder-zinc-500 focus:outline-none focus:border-zinc-500"
                                        x-on:click.stop=""
                                        x-on:keydown="onKeydown($event)"
                                    />
                                </div>
                                <ul x-ref="list" class="max-h-40 overflow-y-auto text-sm">
                                    <li
                                        x-cloak
                                        x-show="filtered.length === 0"
                                        class="px-3 py-2 text-zinc-500 italic"
                                    >
                                        {{ __('Sin resultados') }}
                                    </li>
                                    <template x-for="(team, index) in filtered" :key="team.id">
                                        <li
                                            data-team-item
                                            class="px-3 py-2 text-zinc-300 cursor-pointer transition hover:bg-zinc-700"
                                            x-on:click="selectTeam(team)"
                                            x-on:mouseenter="updateHighlight(index)"
                                            x-text="team.name"
                                        ></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
