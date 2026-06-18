<?php

use App\Models\Fixture;
use App\Models\Group;
use App\Models\Prediction;
use App\Models\PredictionLog;
use App\Models\Quiniela;
use App\Models\ScoringRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Quiniela se crea correctamente con factory', function () {
    $quiniela = Quiniela::factory()->create([
        'name' => 'Mundial 2026',
        'status' => 'active',
    ]);

    expect($quiniela->name)->toBe('Mundial 2026');
    expect($quiniela->status)->toBe('active');
    expect($quiniela->code)->not->toBeEmpty();
    expect(strlen($quiniela->code))->toBe(8);
    expect($quiniela->owner)->toBeInstanceOf(User::class);
});

test('Quiniela tiene relaciones correctas', function () {
    $quiniela = Quiniela::factory()->create();

    $team = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $group = Group::factory()->create(['quiniela_id' => $quiniela->id]);
    $fixture = Fixture::factory()->create(['quiniela_id' => $quiniela->id]);
    ScoringRule::factory()->create(['quiniela_id' => $quiniela->id]);

    expect($quiniela->teams)->toHaveCount(1);
    expect($quiniela->teams->first()->name)->toBe($team->name);

    expect($quiniela->groups)->toHaveCount(1);
    expect($quiniela->matches)->toHaveCount(1);
    expect($quiniela->scoringRule)->toBeInstanceOf(ScoringRule::class);
});

test('Team pertenece a Quiniela y puede asociarse a Group', function () {
    $quiniela = Quiniela::factory()->create();
    $team = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $group = Group::factory()->create(['quiniela_id' => $quiniela->id]);

    $team->groups()->attach($group->id);

    expect($team->quiniela->id)->toBe($quiniela->id);
    expect($team->groups)->toHaveCount(1);
    expect($team->groups->first()->name)->toBe($group->name);
    expect($group->teams)->toHaveCount(1);
});

test('Fixture con equipos local/visitante y grupo nullable', function () {
    $quiniela = Quiniela::factory()->create();
    $homeTeam = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $awayTeam = Team::factory()->create(['quiniela_id' => $quiniela->id]);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'group_id' => null,
    ]);

    expect($fixture->homeTeam->id)->toBe($homeTeam->id);
    expect($fixture->awayTeam->id)->toBe($awayTeam->id);
    expect($fixture->group)->toBeNull();
    expect($fixture->is_completed)->toBeFalse();
});

test('Fixture puede asociarse a un grupo', function () {
    $quiniela = Quiniela::factory()->create();
    $group = Group::factory()->create(['quiniela_id' => $quiniela->id]);
    $homeTeam = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $awayTeam = Team::factory()->create(['quiniela_id' => $quiniela->id]);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'group_id' => $group->id,
    ]);

    expect($fixture->group->id)->toBe($group->id);
    expect($group->matches)->toHaveCount(1);
});

test('Fixture completed state funciona correctamente', function () {
    $fixture = Fixture::factory()->completed()->create();

    expect($fixture->is_completed)->toBeTrue();
    expect($fixture->home_score)->not->toBeNull();
    expect($fixture->away_score)->not->toBeNull();
});

test('ScoringRule es única por quiniela', function () {
    $quiniela = Quiniela::factory()->create();

    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 5,
    ]);

    expect(fn () => ScoringRule::factory()->create(['quiniela_id' => $quiniela->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('Prediction tiene unique composite key match_id user_id', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create();

    Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
    ]);

    expect(fn () => Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('Prediction relaciona correctamente match y user', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create();

    $prediction = Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    expect($prediction->match->id)->toBe($fixture->id);
    expect($prediction->user->id)->toBe($user->id);
    expect($prediction->home_score)->toBe(2);
    expect($prediction->away_score)->toBe(1);
});

test('Prediction is_partial flag funciona', function () {
    $prediction = Prediction::factory()->partial()->create();

    expect($prediction->is_partial)->toBeTrue();
    expect($prediction->home_score)->toBeNull();
    expect($prediction->away_score)->toBeNull();
});

test('PredictionLog registra accion created correctamente', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create();
    $prediction = Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
    ]);

    $log = PredictionLog::factory()->create([
        'prediction_id' => $prediction->id,
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'changed_by_user_id' => $user->id,
        'action' => 'created',
        'new_home_score' => 3,
        'new_away_score' => 1,
    ]);

    expect($log->action)->toBe('created');
    expect($log->prediction->id)->toBe($prediction->id);
    expect($log->user->id)->toBe($user->id);
    expect($log->changedBy->id)->toBe($user->id);
});

test('PredictionLog registra accion updated correctamente', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create();
    $prediction = Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
    ]);

    $log = PredictionLog::factory()->updated()->create([
        'prediction_id' => $prediction->id,
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'changed_by_user_id' => $user->id,
    ]);

    expect($log->action)->toBe('updated');
    expect($log->old_home_score)->not->toBeNull();
    expect($log->old_away_score)->not->toBeNull();
});

test('PredictionLog registra accion admin_edit con motivo', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create();
    $prediction = Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user->id,
    ]);

    $log = PredictionLog::factory()->adminEdit()->create([
        'prediction_id' => $prediction->id,
        'match_id' => $fixture->id,
        'user_id' => $user->id,
        'changed_by_user_id' => $admin->id,
    ]);

    expect($log->action)->toBe('admin_edit');
    expect($log->reason)->not->toBeNull();
    expect($log->changed_by_user_id)->toBe($admin->id);
    expect($log->user_id)->toBe($user->id);
});

test('quiniela_user soporta soft-delete manual', function () {
    $quiniela = Quiniela::factory()->create();
    $user = User::factory()->create();

    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    expect($quiniela->participants)->toHaveCount(1);

    $quiniela->participants()->updateExistingPivot($user->id, ['deleted_at' => now()]);

    $active = $quiniela->participants()->wherePivotNull('deleted_at')->get();
    expect($active)->toHaveCount(0);

    $all = $quiniela->participants()->withPivot('deleted_at')->get();
    expect($all)->toHaveCount(1);
    expect($all->first()->pivot->deleted_at)->not->toBeNull();
});

test('soft delete de Quiniela funciona', function () {
    $quiniela = Quiniela::factory()->create();

    $quiniela->delete();

    expect(Quiniela::count())->toBe(0);
    expect(Quiniela::withTrashed()->count())->toBe(1);
    expect($quiniela->trashed())->toBeTrue();
});

test('User tiene relaciones con entidades del dominio', function () {
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $user->id]);

    expect($user->ownedQuinielas)->toHaveCount(1);
    expect($user->ownedQuinielas->first()->id)->toBe($quiniela->id);
});

test('User puede participar en múltiples quinielas', function () {
    $user = User::factory()->create();
    $quiniela1 = Quiniela::factory()->create();
    $quiniela2 = Quiniela::factory()->create();

    $quiniela1->participants()->attach($user->id, ['joined_at' => now()]);
    $quiniela2->participants()->attach($user->id, ['joined_at' => now()]);

    expect($user->quinielas()->wherePivotNull('deleted_at')->count())->toBe(2);
});

test('Quiniela tiene estados active closed historical', function () {
    $active = Quiniela::factory()->active()->create();
    $closed = Quiniela::factory()->closed()->create();
    $historical = Quiniela::factory()->historical()->create();

    expect($active->status)->toBe('active');
    expect($closed->status)->toBe('closed');
    expect($historical->status)->toBe('historical');
});

test('Fixture acepta phase y round nullable para futuro soporte de eliminatorias', function () {
    $fixture = Fixture::factory()->create([
        'phase' => 'round_of_16',
        'round' => 'knockout',
    ]);

    expect($fixture->phase)->toBe('round_of_16');
    expect($fixture->round)->toBe('knockout');
});
