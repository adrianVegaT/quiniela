<?php

use App\Models\Quiniela;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    #[Computed]
    public function activeQuinielas()
    {
        $userId = auth()->id();

        $owned = Quiniela::where('owner_id', $userId)
            ->whereIn('status', ['active', 'closed'])
            ->orderBy('created_at', 'desc')
            ->get();

        $participating = auth()->user()->quinielas()
            ->wherePivotNull('deleted_at')
            ->whereIn('status', ['active', 'closed'])
            ->orderBy('created_at', 'desc')
            ->get();

        return $owned->merge($participating);
    }

    #[Computed]
    public function historicalQuinielas()
    {
        $userId = auth()->id();

        $owned = Quiniela::where('owner_id', $userId)
            ->where('status', 'historical')
            ->orderBy('created_at', 'desc')
            ->get();

        $participating = auth()->user()->quinielas()
            ->wherePivotNull('deleted_at')
            ->where('status', 'historical')
            ->orderBy('created_at', 'desc')
            ->get();

        return $owned->merge($participating);
    }

    #[Computed]
    public function selectedQuiniela()
    {
        return $this->activeQuinielas->first();
    }
}; ?>

<div>
    <flux:heading size="xl" class="mb-2">{{ __('Dashboard') }}</flux:heading>

    @if ($this->activeQuinielas->isNotEmpty())
        <flux:heading size="base" class="mb-6 text-zinc-400">
            {{ $this->activeQuinielas->count() }} quiniela(s) activa(s)
        </flux:heading>

        @foreach ($this->activeQuinielas as $quiniela)
            <div class="mb-8">
                <div class="flex items-center gap-2 mb-4">
                    <flux:heading size="lg">{{ $quiniela->name }}</flux:heading>
                    @if ($quiniela->owner_id === auth()->id())
                        <flux:badge variant="solid" color="amber" size="sm">Admin</flux:badge>
                    @endif
                    <flux:badge variant="solid" size="sm" color="green">Activa</flux:badge>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                    <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.leaderboard', $quiniela)">
                        <div class="text-left">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-amber-500/10 flex items-center justify-center mb-2 lg:mb-3">
                                <flux:icon.trophy class="size-4 lg:size-5 text-amber-400" />
                            </div>
                            <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Posiciones') }}</p>
                            <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Ver tabla') }}</p>
                        </div>
                    </flux:card>
                    <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.predictions', $quiniela)">
                        <div class="text-left">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-accent/10 flex items-center justify-center mb-2 lg:mb-3">
                                <flux:icon.pencil class="size-4 lg:size-5 text-accent" />
                            </div>
                            <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Predicciones') }}</p>
                            <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Llenar marcadores') }}</p>
                        </div>
                    </flux:card>
                    <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.today', $quiniela)">
                        <div class="text-left">
                            <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-red-500/10 flex items-center justify-center mb-2 lg:mb-3">
                                <flux:icon.calendar class="size-4 lg:size-5 text-red-400" />
                            </div>
                            <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Hoy') }}</p>
                            <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Partidos del día') }}</p>
                        </div>
                    </flux:card>
                    @if ($quiniela->owner_id === auth()->id())
                        <flux:card class="cursor-pointer hover:bg-zinc-800/50 transition group" wire:navigate :href="route('quinielas.show', $quiniela)">
                            <div class="text-left">
                                <div class="w-8 h-8 lg:w-10 lg:h-10 rounded-lg bg-purple-500/10 flex items-center justify-center mb-2 lg:mb-3">
                                    <flux:icon.cog class="size-4 lg:size-5 text-purple-400" />
                                </div>
                                <p class="text-sm font-medium group-hover:text-accent transition">{{ __('Administrar') }}</p>
                                <p class="text-[11px] text-zinc-500 mt-0.5">{{ __('Gestionar todo') }}</p>
                            </div>
                        </flux:card>
                    @endif
                </div>

                <flux:card class="mt-3 lg:mt-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[11px] text-zinc-500 mb-0.5">{{ __('Código de invitación') }}</p>
                            <p class="text-lg lg:text-xl font-mono font-bold tracking-widest text-white select-all">{{ $quiniela->code }}</p>
                        </div>
                        <flux:button variant="subtle" icon="clipboard" size="sm" />
                    </div>
                </flux:card>
            </div>
        @endforeach
    @else
        <flux:card class="mb-8">
            <div class="text-center py-8">
                <flux:icon.trophy class="size-12 mx-auto mb-3 text-zinc-400" />
                <flux:heading size="lg">{{ __('No tienes quinielas activas') }}</flux:heading>
                <flux:text class="mt-2 mb-4">{{ __('Crea una nueva o únete con un código.') }}</flux:text>
                <div class="flex gap-3 justify-center">
                    <flux:button wire:navigate :href="route('quinielas.create')" variant="primary" icon="plus">
                        {{ __('Crear quiniela') }}
                    </flux:button>
                    <flux:button wire:navigate :href="route('quinielas.index')" variant="outline" icon="magnifying-glass">
                        {{ __('Unirse a una quiniela') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>
    @endif

    @if ($this->historicalQuinielas->isNotEmpty())
        <div x-data="{ open: false }" class="mb-8">
            <button
                x-on:click="open = !open"
                class="flex items-center gap-2 text-zinc-400 hover:text-zinc-200 transition w-full mb-4"
            >
                <flux:icon.chevron-right class="size-4 transition-transform" ::class="open ? 'rotate-90' : ''" />
                <flux:heading size="base">{{ __('Quinielas históricas') }}</flux:heading>
                <flux:badge variant="subtle" size="sm">{{ $this->historicalQuinielas->count() }}</flux:badge>
            </button>

            <div x-show="open" x-collapse class="space-y-3 opacity-60">
                @foreach ($this->historicalQuinielas as $quiniela)
                    <flux:card class="cursor-pointer hover:opacity-80 transition" wire:navigate :href="route('quinielas.show-public', $quiniela)">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:heading>{{ $quiniela->name }}</flux:heading>
                                    <flux:badge variant="solid" color="zinc" size="sm">Histórica</flux:badge>
                                </div>
                                <flux:text class="mt-1 text-sm">Código: {{ $quiniela->code }}</flux:text>
                            </div>
                            <flux:icon.chevron-right class="size-5 text-zinc-500 mt-1" />
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @endif
</div>
