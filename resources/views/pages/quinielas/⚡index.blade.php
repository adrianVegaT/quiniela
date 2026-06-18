<?php

use App\Models\Quiniela;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Mis quinielas')] class extends Component
{
    public ?string $joinCode = '';

    #[Computed]
    public function active()
    {
        $userId = auth()->id();

        $owned = Quiniela::where('owner_id', $userId)
            ->whereIn('status', ['active', 'closed'])
            ->orderBy('created_at', 'desc')
            ->get();

        $participating = auth()->user()->quinielas()
            ->wherePivotNull('deleted_at')
            ->where('owner_id', '!=', $userId)
            ->whereIn('status', ['active', 'closed'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $owned->merge($participating);
    }

    #[Computed]
    public function historical()
    {
        $userId = auth()->id();

        $owned = Quiniela::where('owner_id', $userId)
            ->where('status', 'historical')
            ->orderBy('created_at', 'desc')
            ->get();

        $participating = auth()->user()->quinielas()
            ->wherePivotNull('deleted_at')
            ->where('owner_id', '!=', $userId)
            ->where('status', 'historical')
            ->orderBy('created_at', 'desc')
            ->get();

        return $owned->merge($participating);
    }

    public function join(): void
    {
        $this->validate(['joinCode' => 'required|string|size:8']);

        $quiniela = Quiniela::where('code', strtoupper($this->joinCode))
            ->where('status', 'active')
            ->first();

        if (! $quiniela) {
            Flux::toast(variant: 'danger', text: __('Código no válido o quiniela no activa.'));

            return;
        }

        $exists = $quiniela->participants()
            ->where('user_id', auth()->id())
            ->whereNull('quiniela_user.deleted_at')
            ->exists();

        if ($exists) {
            Flux::toast(variant: 'warning', text: __('Ya estás participando en esta quiniela.'));

            return;
        }

        $quiniela->participants()->attach(auth()->id(), ['joined_at' => now()]);

        $this->joinCode = '';
        unset($this->active, $this->historical);
        Flux::toast(variant: 'success', text: __('Te has unido correctamente.'));
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">{{ __('Mis quinielas') }}</flux:heading>
        <flux:button wire:navigate :href="route('quinielas.create')" variant="primary" icon="plus">
            {{ __('Crear') }}
        </flux:button>
    </div>

    <flux:card class="mb-8 max-w-lg">
        <p class="text-xs font-medium text-zinc-400 mb-3">{{ __('Unirse con código') }}</p>
        <div class="flex gap-2">
            <flux:input wire:model="joinCode" placeholder="AB12CD34" maxlength="8" class="flex-1 uppercase font-mono tracking-wider text-center" />
            <flux:button wire:click="join" variant="primary">{{ __('Unirse') }}</flux:button>
        </div>
    </flux:card>

    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-4">{{ __('Activas') }}</p>

    @if ($this->active->isEmpty())
        <flux:card class="mb-8">
            <div class="text-center py-8">
                <flux:icon.trophy class="size-12 mx-auto mb-3 text-zinc-400" />
                <flux:heading size="lg">{{ __('No tienes quinielas activas') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Crea una quiniela nueva o únete a una con un código de invitación.') }}</flux:text>
            </div>
        </flux:card>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4 mb-8">
            @foreach ($this->active as $quiniela)
                <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition" wire:navigate :href="$quiniela->owner_id === auth()->id() ? route('quinielas.show', $quiniela) : route('quinielas.show-public', $quiniela)">
                    <div class="flex justify-between items-start">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <flux:heading class="truncate">{{ $quiniela->name }}</flux:heading>
                                @if ($quiniela->owner_id === auth()->id())
                                    <flux:badge variant="solid" color="amber" size="sm">Admin</flux:badge>
                                @endif
                            </div>
                            @if ($quiniela->description)
                                <flux:text class="mt-1">{{ Str::limit($quiniela->description, 80) }}</flux:text>
                            @endif
                            <div class="flex flex-wrap gap-3 mt-2 text-[11px] text-zinc-400">
                                <span>Código: <code class="text-zinc-300 font-mono">{{ $quiniela->code }}</code></span>
                                @if ($quiniela->prediction_edit_deadline)
                                    <span>Límite: {{ $quiniela->prediction_edit_deadline->format('d/m/Y') }}</span>
                                @endif
                            </div>
                        </div>
                        <flux:icon.chevron-right class="size-5 text-zinc-500 mt-1 shrink-0" />
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    @if ($this->historical->isNotEmpty())
        <div x-data="{ open: false }">
            <button
                x-on:click="open = !open"
                class="flex items-center gap-2 text-zinc-400 hover:text-zinc-200 transition w-full"
            >
                <flux:icon.chevron-right class="size-4 transition-transform" ::class="open ? 'rotate-90' : ''" />
                <flux:heading size="base">{{ __('Históricas') }}</flux:heading>
                <flux:badge variant="subtle" size="sm">{{ $this->historical->count() }}</flux:badge>
            </button>

            <div x-show="open" x-collapse class="mt-4 space-y-3 opacity-60">
                @foreach ($this->historical as $quiniela)
                    <flux:card class="cursor-pointer hover:opacity-80 transition" wire:navigate :href="$quiniela->owner_id === auth()->id() ? route('quinielas.show', $quiniela) : route('quinielas.show-public', $quiniela)">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:heading>{{ $quiniela->name }}</flux:heading>
                                    <flux:badge variant="solid" color="zinc" size="sm">Histórica</flux:badge>
                                </div>
                                <flux:text class="mt-1">Código: {{ $quiniela->code }}</flux:text>
                            </div>
                            <flux:icon.chevron-right class="size-5 text-zinc-500 mt-1" />
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif
</div>
