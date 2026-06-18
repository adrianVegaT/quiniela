<?php

use App\Models\Quiniela;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gestionar quiniela')] class extends Component
{
    public Quiniela $quiniela;

    public string $tab = 'info';

    public string $editName = '';

    public string $editDescription = '';

    public ?string $editDeadline = null;

    public function mount(Quiniela $quiniela): void
    {
        if (auth()->id() !== $quiniela->owner_id) {
            abort(403);
        }

        $this->quiniela = $quiniela;
        $this->editName = $quiniela->name;
        $this->editDescription = $quiniela->description ?? '';
        $this->editDeadline = $quiniela->prediction_edit_deadline?->format('Y-m-d\TH:i');
    }

    public function copyCode(): void
    {
        Flux::toast(variant: 'success', text: 'Código copiado: '.$this->quiniela->code);
    }

    public function updateInfo(): void
    {
        $this->validate([
            'editName' => 'required|string|max:100',
            'editDescription' => 'nullable|string|max:500',
            'editDeadline' => 'nullable|date',
        ]);

        $this->quiniela->update([
            'name' => $this->editName,
            'description' => $this->editDescription ?: null,
            'prediction_edit_deadline' => $this->editDeadline ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Información actualizada.'));
    }

    #[Computed]
    public function matchStats(): array
    {
        $total = $this->quiniela->matches()->count();
        $completed = $this->quiniela->matches()->where('is_completed', true)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
            'all_done' => $total > 0 && $total === $completed,
        ];
    }

    public function closeQuiniela(): void
    {
        if ($this->quiniela->status !== 'active') {
            return;
        }

        $this->quiniela->update(['status' => 'closed']);
        Flux::toast(variant: 'success', text: __('Quiniela cerrada.'));
    }

    public function archiveQuiniela(): void
    {
        if ($this->quiniela->status !== 'closed') {
            return;
        }

        $this->quiniela->update(['status' => 'historical']);
        Flux::toast(variant: 'success', text: __('Quiniela enviada al historial.'));
    }

    public function cancelQuiniela(): void
    {
        $hasParticipants = $this->quiniela->participants()->whereNull('quiniela_user.deleted_at')->exists();
        $hasResults = $this->quiniela->matches()->where('is_completed', true)->exists();

        if ($hasParticipants || $hasResults) {
            Flux::toast(variant: 'danger', text: __('No se puede cancelar: tiene participantes o resultados.'));

            return;
        }

        $this->quiniela->delete();
        Flux::toast(variant: 'success', text: __('Quiniela eliminada.'));

        $this->redirect(route('quinielas.index'));
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <flux:button x-on:click="window.history.back()" variant="subtle" icon="chevron-left" size="sm" />
            <flux:heading size="xl">{{ $quiniela->name }}</flux:heading>
            <flux:badge variant="solid" size="sm" :color="$quiniela->status === 'active' ? 'green' : ($quiniela->status === 'closed' ? 'amber' : 'zinc')">
                {{ $quiniela->status === 'active' ? 'Activa' : ($quiniela->status === 'closed' ? 'Cerrada' : 'Histórica') }}
            </flux:badge>
        </div>
    </div>

    <div class="mb-6 overflow-x-auto -mx-4 px-4 lg:mx-0 lg:px-0">
        <nav class="flex gap-1 min-w-max">
            <button wire:click="$set('tab', 'info')" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition {{ $tab === 'info' ? 'bg-accent/15 text-accent' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}">
                <flux:icon.information-circle class="size-4" />
                <span class="hidden sm:inline">{{ __('Info') }}</span>
            </button>
            <button wire:click="$set('tab', 'teams')" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition {{ $tab === 'teams' ? 'bg-accent/15 text-accent' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}">
                <flux:icon.users class="size-4" />
                <span class="hidden sm:inline">{{ __('Equipos') }}</span>
            </button>
            <button wire:click="$set('tab', 'groups')" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition {{ $tab === 'groups' ? 'bg-accent/15 text-accent' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}">
                <flux:icon.squares-2x2 class="size-4" />
                <span class="hidden sm:inline">{{ __('Grupos') }}</span>
            </button>
            <button wire:click="$set('tab', 'matches')" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition {{ $tab === 'matches' ? 'bg-accent/15 text-accent' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}">
                <flux:icon.calendar class="size-4" />
                <span class="hidden sm:inline">{{ __('Partidos') }}</span>
            </button>
            <button wire:click="$set('tab', 'scoring')" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition {{ $tab === 'scoring' ? 'bg-accent/15 text-accent' : 'text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800' }}">
                <flux:icon.calculator class="size-4" />
                <span class="hidden sm:inline">{{ __('Puntuación') }}</span>
            </button>
            <a href="{{ route('quinielas.results', $quiniela) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800">
                <flux:icon.trophy class="size-4" />
                <span class="hidden sm:inline">{{ __('Resultados') }}</span>
            </a>
            <a href="{{ route('quinielas.leaderboard', $quiniela) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800">
                <flux:icon.chart-bar class="size-4" />
                <span class="hidden sm:inline">{{ __('Tabla') }}</span>
            </a>
            <a href="{{ route('quinielas.log', $quiniela) }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg transition text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800">
                <flux:icon.document-text class="size-4" />
                <span class="hidden sm:inline">{{ __('Log') }}</span>
            </a>
        </nav>
    </div>

    @if ($tab === 'info')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Código de invitación') }}</flux:heading>
                <div class="flex items-center gap-3">
                    <code class="text-2xl font-mono tracking-wider bg-zinc-800 px-4 py-2 rounded-lg select-all">{{ $quiniela->code }}</code>
                    <flux:button wire:click="copyCode" variant="outline" icon="clipboard" size="sm" />
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Estadísticas') }}</flux:heading>
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-3 rounded-lg bg-zinc-800">
                        <flux:heading size="xl">{{ $this->matchStats['total'] }}</flux:heading>
                        <flux:text class="text-zinc-400 text-xs mt-1">Totales</flux:text>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-zinc-800">
                        <flux:heading size="xl">{{ $this->matchStats['completed'] }}</flux:heading>
                        <flux:text class="text-zinc-400 text-xs mt-1">Completados</flux:text>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-zinc-800">
                        <flux:heading size="xl">{{ $this->matchStats['pending'] }}</flux:heading>
                        <flux:text class="text-zinc-400 text-xs mt-1">Pendientes</flux:text>
                    </div>
                </div>
            </flux:card>

            <flux:card class="lg:col-span-2">
                <flux:heading size="lg" class="mb-4">{{ __('Editar información') }}</flux:heading>
                <form wire:submit="updateInfo" class="space-y-4">
                    <flux:input wire:model="editName" label="Nombre" required maxlength="100" />
                    <flux:textarea wire:model="editDescription" label="Descripción" rows="3" maxlength="500" />
                    <flux:input wire:model="editDeadline" label="Fecha límite de edición" type="datetime-local" />
                    <flux:button type="submit" variant="primary">{{ __('Guardar cambios') }}</flux:button>
                </form>
            </flux:card>

            <flux:card class="lg:col-span-2">
                <flux:heading size="lg" class="mb-4">{{ __('Acciones de quiniela') }}</flux:heading>
                <div class="flex flex-wrap gap-3">
                    @if ($this->matchStats['all_done'] && $quiniela->status === 'active')
                        <flux:button wire:click="closeQuiniela" wire:confirm="¿Cerrar la quiniela? Los participantes ya no podrán editar predicciones." variant="primary">
                            {{ __('Cerrar quiniela') }}
                        </flux:button>
                    @endif

                    @if ($quiniela->status === 'closed')
                        <flux:button wire:click="archiveQuiniela" wire:confirm="¿Enviar al historial? La quiniela se mostrará en segundo plano." variant="outline">
                            {{ __('Enviar a histórico') }}
                        </flux:button>
                    @endif

                    @if ($quiniela->status === 'active' && $this->matchStats['completed'] === 0)
                        <flux:button wire:click="cancelQuiniela" wire:confirm="¿Eliminar esta quiniela permanentemente?" variant="danger">
                            {{ __('Cancelar quiniela') }}
                        </flux:button>
                    @endif
                </div>
            </flux:card>
        </div>
    @elseif ($tab === 'teams')
        <livewire:pages::quinielas.teams.index :quiniela="$quiniela" />
    @elseif ($tab === 'groups')
        <livewire:pages::quinielas.groups.index :quiniela="$quiniela" />
    @elseif ($tab === 'matches')
        <livewire:pages::quinielas.matches.index :quiniela="$quiniela" />
    @elseif ($tab === 'scoring')
        <livewire:pages::quinielas.scoring.index :quiniela="$quiniela" />
    @endif
</div>
