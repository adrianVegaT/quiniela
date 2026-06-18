<?php

use App\Models\Quiniela;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Log de actividades')] class extends Component
{
    use WithPagination;

    public Quiniela $quiniela;

    public ?string $filterUser = 'all';

    public ?string $filterAction = 'all';

    public function mount(Quiniela $quiniela): void
    {
        $isOwner = auth()->id() === $quiniela->owner_id;
        $isParticipant = $quiniela->participants()
            ->where('user_id', auth()->id())
            ->whereNull('quiniela_user.deleted_at')
            ->exists();

        if (! $isOwner && ! $isParticipant) {
            abort(403);
        }

        $this->quiniela = $quiniela;
    }

    #[Computed]
    public function participants(): array
    {
        return $this->quiniela->participants()
            ->whereNull('quiniela_user.deleted_at')
            ->orderBy('name')
            ->pluck('name', 'users.id')
            ->toArray();
    }

    #[Computed]
    public function logs()
    {
        $userId = auth()->id();
        $isOwner = $userId === $this->quiniela->owner_id;

        $query = \App\Models\PredictionLog::query()
            ->whereIn('match_id', $this->quiniela->matches()->select('id'))
            ->with(['user', 'changedBy', 'match.homeTeam', 'match.awayTeam']);

        if (! $isOwner) {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere(function ($q2) use ($userId) {
                        $q2->where('user_id', $userId)
                            ->where('action', 'admin_edit');
                    });
            });
        }

        if ($this->filterUser !== 'all') {
            $query->where('user_id', $this->filterUser);
        }

        if ($this->filterAction !== 'all') {
            $query->where('action', $this->filterAction);
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }
}; ?>

<div>
    <div class="flex items-center gap-3 mb-6">
        <flux:button x-on:click="window.history.back()" variant="subtle" icon="chevron-left" size="sm" />
        <flux:heading size="xl">{{ __('Log de actividades') }}</flux:heading>
    </div>

    <div class="flex gap-4 mb-6">
        <div>
            <label class="block text-sm text-zinc-400 mb-1">{{ __('Usuario') }}</label>
            <select wire:model.live="filterUser" class="text-sm bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-zinc-300">
                <option value="all">Todos</option>
                @foreach ($this->participants as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-zinc-400 mb-1">{{ __('Acción') }}</label>
            <select wire:model.live="filterAction" class="text-sm bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-2 text-zinc-300">
                <option value="all">Todas</option>
                <option value="created">Creado</option>
                <option value="updated">Editado</option>
                <option value="admin_edit">Admin edit</option>
            </select>
        </div>
    </div>

    @if ($this->logs->isEmpty())
        <flux:card>
            <flux:text class="text-zinc-400">{{ __('No hay registros de actividad.') }}</flux:text>
        </flux:card>
    @else
        <flux:card>
            <div class="divide-y divide-zinc-700">
                @foreach ($this->logs as $log)
                    <div class="py-3 first:pt-0 last:pb-0">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="font-medium text-zinc-200">{{ $log->user->name }}</span>
                                    <span class="text-zinc-500">{{ $log->match->homeTeam->name ?? '?' }} vs {{ $log->match->awayTeam->name ?? '?' }}</span>
                                </div>
                                <div class="mt-1 text-xs text-zinc-400">
                                    {{ $log->created_at->format('d/m/Y H:i') }}
                                    @if ($log->changed_by_user_id !== $log->user_id)
                                        &middot; cambiado por <span class="text-zinc-300">{{ $log->changedBy->name }}</span>
                                    @endif
                                </div>
                                <div class="mt-1">
                                    @if ($log->action === 'created')
                                        <flux:badge variant="solid" color="green" size="sm">Creado</flux:badge>
                                        <span class="text-zinc-300 ml-2">{{ $log->new_home_score }} - {{ $log->new_away_score }}</span>
                                    @elseif ($log->action === 'updated')
                                        <flux:badge variant="solid" color="amber" size="sm">Editado</flux:badge>
                                        <span class="text-zinc-500 line-through ml-2">{{ $log->old_home_score }}-{{ $log->old_away_score }}</span>
                                        <span class="text-zinc-300"> → {{ $log->new_home_score }}-{{ $log->new_away_score }}</span>
                                    @elseif ($log->action === 'admin_edit')
                                        <flux:badge variant="solid" color="red" size="sm">Admin edit</flux:badge>
                                        <span class="text-zinc-500 line-through ml-2">{{ $log->old_home_score }}-{{ $log->old_away_score }}</span>
                                        <span class="text-zinc-300"> → {{ $log->new_home_score }}-{{ $log->new_away_score }}</span>
                                        @if ($log->reason)
                                            <p class="text-zinc-500 text-xs mt-0.5">Motivo: {{ $log->reason }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <div class="mt-4">{{ $this->logs->links() }}</div>
    @endif
</div>
