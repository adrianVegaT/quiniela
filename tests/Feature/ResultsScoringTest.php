<?php

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\Quiniela;
use App\Models\ScoringRule;
use App\Models\Team;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMatchWithPredictions(Quiniela $quiniela, array $predictionsData, ?int $matchHome = null, ?int $matchAway = null): Fixture
{
    $homeTeam = Team::factory()->create(['quiniela_id' => $quiniela->id]);
    $awayTeam = Team::factory()->create(['quiniela_id' => $quiniela->id]);

    $fixture = Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'home_team_id' => $homeTeam->id,
        'away_team_id' => $awayTeam->id,
        'home_score' => $matchHome,
        'away_score' => $matchAway,
        'is_completed' => $matchHome !== null,
    ]);

    foreach ($predictionsData as $data) {
        Prediction::factory()->create([
            'match_id' => $fixture->id,
            'user_id' => $data['user_id'],
            'home_score' => $data['home_score'],
            'away_score' => $data['away_score'],
        ]);
    }

    return $fixture;
}

test('exact score calculation returns correct points', function () {
    $quiniela = Quiniela::factory()->active()->create();
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 1,
        'points_one_team_goals' => 1,
    ]);

    $fixture = createMatchWithPredictions($quiniela, [
        ['user_id' => User::factory()->create()->id, 'home_score' => 2, 'away_score' => 1],
    ], 2, 1);

    $service = app(ScoringService::class);
    $service->scoreMatch($fixture);

    $prediction = Prediction::first();
    expect($prediction->exact_score_points)->toBe(3);
    expect($prediction->winner_draw_points)->toBe(0);
    expect($prediction->one_team_goals_points)->toBe(0);
});

test('winner draw calculation returns correct points', function () {
    $quiniela = Quiniela::factory()->active()->create();
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 1,
        'points_one_team_goals' => 1,
    ]);

    $fixture = createMatchWithPredictions($quiniela, [
        ['user_id' => User::factory()->create()->id, 'home_score' => 2, 'away_score' => 0],
    ], 1, 0);

    $service = app(ScoringService::class);
    $service->scoreMatch($fixture);

    $prediction = Prediction::first();
    expect($prediction->exact_score_points)->toBe(0);
    expect($prediction->winner_draw_points)->toBe(1);
    expect($prediction->one_team_goals_points)->toBe(1);
});

test('one team goals calculation returns correct points', function () {
    $quiniela = Quiniela::factory()->active()->create();
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 1,
        'points_one_team_goals' => 1,
    ]);

    $fixture = createMatchWithPredictions($quiniela, [
        ['user_id' => User::factory()->create()->id, 'home_score' => 2, 'away_score' => 0],
    ], 2, 1);

    $service = app(ScoringService::class);
    $service->scoreMatch($fixture);

    $prediction = Prediction::first();
    expect($prediction->exact_score_points)->toBe(0);
    expect($prediction->winner_draw_points)->toBe(1);
    expect($prediction->one_team_goals_points)->toBe(1);
});

test('one team goals is not awarded when exact score already awarded', function () {
    $quiniela = Quiniela::factory()->active()->create();
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 1,
        'points_one_team_goals' => 1,
    ]);

    $fixture = createMatchWithPredictions($quiniela, [
        ['user_id' => User::factory()->create()->id, 'home_score' => 2, 'away_score' => 1],
    ], 2, 1);

    $service = app(ScoringService::class);
    $service->scoreMatch($fixture);

    $prediction = Prediction::first();
    expect($prediction->exact_score_points)->toBe(3);
    expect($prediction->one_team_goals_points)->toBe(0);
});

test('completely wrong prediction gets zero points', function () {
    $quiniela = Quiniela::factory()->active()->create();
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 1,
        'points_one_team_goals' => 1,
    ]);

    $fixture = createMatchWithPredictions($quiniela, [
        ['user_id' => User::factory()->create()->id, 'home_score' => 0, 'away_score' => 3],
    ], 2, 1);

    $service = app(ScoringService::class);
    $service->scoreMatch($fixture);

    $prediction = Prediction::first();
    expect($prediction->exact_score_points)->toBe(0);
    expect($prediction->winner_draw_points)->toBe(0);
    expect($prediction->one_team_goals_points)->toBe(0);
});

test('draw prediction awarded correctly', function () {
    $quiniela = Quiniela::factory()->active()->create();
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 1,
        'points_one_team_goals' => 0,
    ]);

    $fixture = createMatchWithPredictions($quiniela, [
        ['user_id' => User::factory()->create()->id, 'home_score' => 1, 'away_score' => 1],
    ], 2, 2);

    $service = app(ScoringService::class);
    $service->scoreMatch($fixture);

    $prediction = Prediction::first();
    expect($prediction->winner_draw_points)->toBe(1);
    expect($prediction->exact_score_points)->toBe(0);
});

test('leaderboard shows correct rankings', function () {
    $owner = User::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 1,
        'points_one_team_goals' => 0,
    ]);

    $quiniela->participants()->attach($user1->id, ['joined_at' => now()]);
    $quiniela->participants()->attach($user2->id, ['joined_at' => now()]);

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
        'user_id' => $user1->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);
    Prediction::factory()->create([
        'match_id' => $fixture->id,
        'user_id' => $user2->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    app(ScoringService::class)->scoreMatch($fixture);

    $leaderboard = app(ScoringService::class)->leaderboard($quiniela);

    expect(count($leaderboard['rows']))->toBeGreaterThanOrEqual(2);
    $row1 = collect($leaderboard['rows'])->firstWhere('user.id', $user1->id);
    $row2 = collect($leaderboard['rows'])->firstWhere('user.id', $user2->id);
    expect($row1)->not->toBeNull();
    expect($row2)->not->toBeNull();
    expect($row1['total_points'])->toBe(3);
    expect($row2['total_points'])->toBe(1);
});

test('admin can view results page', function () {
    $owner = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('quinielas.results', $quiniela))
        ->assertOk()
        ->assertSee('Registrar resultados');
});

test('admin can view leaderboard page', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $this->actingAs($owner)
        ->get(route('quinielas.leaderboard', $quiniela))
        ->assertOk()
        ->assertSee('Tabla de posiciones');
});

test('leaderboard hides zero-point columns', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $quiniela = Quiniela::factory()->active()->create(['owner_id' => $owner->id]);
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 0,
        'points_one_team_goals' => 0,
    ]);
    $quiniela->participants()->attach($user->id, ['joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('quinielas.leaderboard', $quiniela))
        ->assertOk()
        ->assertSee('Exacto')
        ->assertDontSee('Ganador')
        ->assertDontSee('Goles');
});

test('recalculate updates all match scores', function () {
    $quiniela = Quiniela::factory()->active()->create();
    ScoringRule::factory()->create([
        'quiniela_id' => $quiniela->id,
        'points_exact_score' => 3,
        'points_winner_draw' => 0,
        'points_one_team_goals' => 0,
    ]);

    $user = User::factory()->create();

    createMatchWithPredictions($quiniela, [
        ['user_id' => $user->id, 'home_score' => 2, 'away_score' => 1],
    ], 2, 1);

    createMatchWithPredictions($quiniela, [
        ['user_id' => $user->id, 'home_score' => 1, 'away_score' => 0],
    ], 1, 0);

    $service = app(ScoringService::class);
    $service->recalculateQuiniela($quiniela);

    $predictions = Prediction::all();
    expect($predictions->every(fn ($p) => $p->exact_score_points !== null))->toBeTrue();
    expect($predictions->sum('exact_score_points'))->toBe(6);
});
