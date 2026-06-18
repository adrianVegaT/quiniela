<?php

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Quiniela;
use App\Models\ScoringRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard muestra CTA cuando no tiene quinielas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No tienes quinielas activas')
        ->assertSee('Crear quiniela')
        ->assertSee('Unirse a una quiniela');
});

test('dashboard muestra quinielas activas del usuario', function () {
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($quiniela->name)
        ->assertSee('Admin')
        ->assertDontSee('No tienes quinielas activas');
});

test('vista publica de quiniela muestra leaderboard si deadline expiro', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->create([
        'owner_id' => $owner->id,
        'status' => 'active',
        'prediction_edit_deadline' => now()->subDay(),
    ]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('quinielas.show-public', $quiniela))
        ->assertOk()
        ->assertSee('Tabla de posiciones');
});

test('vista publica oculta leaderboard si deadline no ha expirado', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->create([
        'owner_id' => $owner->id,
        'status' => 'active',
        'prediction_edit_deadline' => now()->addWeek(),
    ]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('quinielas.show-public', $quiniela))
        ->assertOk()
        ->assertSee('estarán visibles después de la fecha límite');
});

test('vista individual muestra predicciones vs resultados', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);
    ScoringRule::factory()->create(['quiniela_id' => $quiniela->id]);

    $home = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $away = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'home_score' => 2,
        'away_score' => 1,
        'is_completed' => true,
    ]);
    Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 3,
        'away_score' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('quinielas.user.show', [$quiniela, $user]))
        ->assertOk()
        ->assertSee($home->name)
        ->assertSee('Predicción')
        ->assertSee('3-0')
        ->assertSee('2-1');
});

test('admin puede ver predicciones de cualquier usuario', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);
    ScoringRule::factory()->create(['quiniela_id' => $quiniela->id]);

    $home = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $away = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'home_score' => 2,
        'away_score' => 1,
        'is_completed' => true,
    ]);
    Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 1,
        'away_score' => 1,
    ]);

    $this->actingAs($owner)
        ->get(route('quinielas.user.show', [$quiniela, $user]))
        ->assertOk()
        ->assertSee($user->name)
        ->assertSee('Vista de administrador');
});

test('quinielas historicas estan en segundo plano en el dashboard', function () {
    $user = User::factory()->create();
    Quiniela::factory()->historical()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Quinielas históricas');
});

test('pagina de reglas es accesible desde vista publica', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('quinielas.show-public', $quiniela))
        ->assertOk()
        ->assertSee('Reglas');
});
