<?php

use App\Models\Quiniela;
use App\Models\ScoringRule;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crear quiniela')] class extends Component
{
    public string $name = '';

    public string $description = '';

    public ?string $predictionEditDeadline = null;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'predictionEditDeadline' => 'nullable|date|after:now',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $quiniela = Quiniela::create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'code' => $this->generateUniqueCode(),
            'owner_id' => auth()->id(),
            'status' => 'active',
            'prediction_edit_deadline' => $this->predictionEditDeadline ?: null,
        ]);

        ScoringRule::create([
            'quiniela_id' => $quiniela->id,
            'points_exact_score' => 3,
            'points_winner_draw' => 1,
            'points_one_team_goals' => 0,
        ]);

        Flux::toast(variant: 'success', text: __('Quiniela creada correctamente.'));

        $this->redirect(route('quinielas.show', $quiniela));
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Quiniela::where('code', $code)->exists());

        return $code;
    }
}; ?>

<div>
    <flux:heading size="xl" class="mb-6">{{ __('Crear nueva quiniela') }}</flux:heading>

    <flux:card class="max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:input wire:model="name" label="Nombre" placeholder="Ej: Mundial FIFA 2026" required maxlength="100" />

            <flux:textarea wire:model="description" label="Descripción" placeholder="Descripción opcional de la quiniela..." rows="3" maxlength="500" />

            <flux:input wire:model="predictionEditDeadline" label="Fecha límite de edición" type="datetime-local" />

            <flux:text class="text-zinc-400">
                Los participantes no podrán editar sus predicciones después de esta fecha. Déjala vacía si no quieres límite.
            </flux:text>

            <div class="flex gap-3 pt-2">
                <flux:button type="submit" variant="primary">{{ __('Crear quiniela') }}</flux:button>
                <flux:button wire:navigate :href="route('quinielas.index')">{{ __('Cancelar') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
