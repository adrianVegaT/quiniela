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

uses(RefreshDatabase::class);

test('admin edita prediccion de otro usuario y se registra log con admin_edit', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->addDays(3),
    ]);
    $prediction = Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    app(PredictionService::class)->adminEditPrediction(
        $prediction, $admin, 2, 1, 'El usuario pidió cambiar su predicción'
    );

    expect($prediction->fresh()->home_score)->toBe(2);
    expect($prediction->fresh()->away_score)->toBe(1);

    $log = PredictionLog::where('action', 'admin_edit')->first();
    expect($log)->not->toBeNull();
    expect($log->changed_by_user_id)->toBe($admin->id);
    expect($log->reason)->toBe('El usuario pidió cambiar su predicción');
    expect($log->old_home_score)->toBe(1);
    expect($log->new_home_score)->toBe(2);
});

test('admin puede ver pagina de log', function () {
    $admin = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('quinielas.log', $quiniela))
        ->assertOk()
        ->assertSee('Log de actividades');
});

test('participante ve logs propios en pagina de log', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);
    ScoringRule::factory()->create(['quiniela_id' => $quiniela->id]);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->addDays(3),
    ]);
    $prediction = Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    app(PredictionService::class)->savePrediction($fixture, $user, 2, 0);

    $this->actingAs($user)
        ->get(route('quinielas.log', $quiniela))
        ->assertOk()
        ->assertSee('Editado');
});

test('PDF se genera correctamente', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);

    $home = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $away = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'home_team_id' => $home->id,
        'away_team_id' => $away->id,
        'match_date' => now()->addDays(3),
    ]);
    Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('quinielas.users.pdf', [$quiniela, $user]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

test('cerrar quiniela requiere todos los partidos completados', function () {
    $admin = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);
    Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'is_completed' => false,
    ]);

    expect($quiniela->matches()->where('is_completed', false)->count())->toBeGreaterThan(0);
    expect($quiniela->status)->toBe('active');
});

test('cerrar quiniela cambia status a closed', function () {
    $admin = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);
    Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'is_completed' => true,
    ]);

    $quiniela->update(['status' => 'closed']);

    expect($quiniela->fresh()->status)->toBe('closed');
});

test('archivar quiniela cambia status a historical', function () {
    $admin = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $admin->id, 'status' => 'closed']);

    $quiniela->update(['status' => 'historical']);

    expect($quiniela->fresh()->status)->toBe('historical');
});

test('cancelar quiniela con participantes falla', function () {
    $admin = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);
    $user = User::factory()->create();
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    expect($quiniela->participants()->whereNull('quiniela_user.deleted_at')->exists())->toBeTrue();
});

test('cancelar quiniela sin participantes ni resultados funciona', function () {
    $admin = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);

    $quiniela->delete();

    expect(Quiniela::count())->toBe(0);
    expect(Quiniela::withTrashed()->count())->toBe(1);
});

test('participante ve log de admin_edit sobre sus predicciones', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'match_date' => now()->addDays(3),
    ]);
    $prediction = Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    app(PredictionService::class)->adminEditPrediction(
        $prediction, $admin, 2, 1, 'Ajuste necesario'
    );

    $this->actingAs($user)
        ->get(route('quinielas.log', $quiniela))
        ->assertOk()
        ->assertSee('Admin edit')
        ->assertSee('Ajuste necesario');
});

test('no-admin no puede descargar PDF', function () {
    $admin = User::factory()->create();
    $other = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);

    $this->actingAs($other)
        ->get(route('quinielas.users.pdf', [$quiniela, $user]))
        ->assertForbidden();
});

test('no-admin no puede ver pagina de log de otros usuarios', function () {
    $admin = User::factory()->create();
    $other = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $admin->id]);
    $quiniela->participants()->attach($other->id, ['joined_at' => now()]);

    $this->actingAs($other)
        ->get(route('quinielas.log', $quiniela))
        ->assertOk();
});
