<?php

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\PredictionLog;
use App\Models\Quiniela;
use App\Models\ScoringRule;
use App\Models\Team;
use App\Models\User;
use App\Services\PredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('usuario se une a quiniela con codigo valido', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id, 'code' => 'ABC12345']);

    Livewire::actingAs($user)
        ->test('pages::quinielas.index')
        ->set('joinCode', 'ABC12345')
        ->call('join')
        ->assertHasNoErrors();

    expect($quiniela->participants()->whereNull('deleted_at')->count())->toBe(1);
});

test('usuario intenta unirse con codigo invalido', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::quinielas.index')
        ->set('joinCode', 'XXXXXXXX')
        ->call('join');

    expect(Quiniela::first()->participants ?? collect())->toHaveCount(0);
});

test('usuario no puede unirse dos veces a la misma quiniela', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id, 'code' => 'XYZ12345']);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    Livewire::actingAs($user)
        ->test('pages::quinielas.index')
        ->set('joinCode', 'XYZ12345')
        ->call('join');

    expect($quiniela->participants()->whereNull('deleted_at')->count())->toBe(1);
});

test('usuario guarda predicciones', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $homeTeam = Team::factory()->create(['quiniela_id' => $quiniela->id, 'name' => 'Argentina']);
    $awayTeam = Team::factory()->create(['quiniela_id' => $quiniela->id, 'name' => 'Brasil']);
    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'match_date' => now()->addDays(3),
    ]);

    Livewire::actingAs($user)
        ->test('pages::quinielas.predictions.index', ['quiniela' => $quiniela])
        ->set('predictions.0.home_score', 2)
        ->set('predictions.0.away_score', 1)
        ->call('save')
        ->assertHasNoErrors();

    $prediction = Prediction::first();
    expect($prediction->home_score)->toBe(2);
    expect($prediction->away_score)->toBe(1);
    expect($prediction->user_id)->toBe($user->id);
});

test('usuario edita prediccion y se crea PredictionLog con accion updated', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->addDays(5),
    ]);

    Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    Livewire::actingAs($user)
        ->test('pages::quinielas.predictions.index', ['quiniela' => $quiniela])
        ->set('predictions.0.home_score', 3)
        ->set('predictions.0.away_score', 2)
        ->call('save')
        ->assertHasNoErrors();

    expect(PredictionLog::where('action', 'updated')->count())->toBe(1);
    expect(PredictionLog::first()->old_home_score)->toBe(1);
    expect(PredictionLog::first()->new_home_score)->toBe(3);
});

test('usuario puede editar prediccion de partido ya iniciado si el deadline no ha expirado', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);
    $service = app(PredictionService::class);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->subHours(2),
    ]);

    expect($service->canEdit($quiniela, $user))->toBeTrue();
});

test('usuario no puede editar prediccion despues del deadline global', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->create([
        'owner_id' => $owner->id,
        'status' => 'active',
        'prediction_edit_deadline' => now()->subDay(),
    ]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);
    $service = app(PredictionService::class);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->addDays(5),
    ]);

    expect($service->canEdit($quiniela, $user))->toBeFalse();
});

test('admin siempre puede editar predicciones', function () {
    $admin = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);
    $service = app(PredictionService::class);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->subHours(2),
    ]);

    expect($service->canEdit($quiniela, $admin))->toBeTrue();
});

test('usuario puede ver reglas de puntuacion', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 1,
        'instructions' => 'Reglas de prueba',
    ]);

    $this->actingAs($user)
        ->get(route('quinielas.rules', $quiniela))
        ->assertOk()
        ->assertSee('Marcador exacto')
        ->assertSee('3 puntos')
        ->assertSee('Reglas de prueba');
});

test('usuario puede ver sus predicciones', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->addDays(3),
    ]);
    Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('quinielas.predictions', $quiniela))
        ->assertOk()
        ->assertSee($fixture->homeTeam->name)
        ->assertSee('2')
        ->assertSee('1');
});

test('guardado parcial no bloquea el guardado', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    Fixture::factory(3)->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->addDays(3),
    ]);

    Livewire::actingAs($user)
        ->test('pages::quinielas.predictions.index', ['quiniela' => $quiniela])
        ->set('predictions.0.home_score', 1)
        ->set('predictions.0.away_score', 1)
        ->call('save')
        ->assertHasNoErrors();

    expect(Prediction::count())->toBe(1);
});
