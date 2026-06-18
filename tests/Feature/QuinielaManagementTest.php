<?php

use App\Models\Fixture;
use App\Models\Group;
use App\Models\Quiniela;
use App\Models\ScoringRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('usuario autenticado puede ver la lista de quinielas', function () {
    $user = User::factory()->create();

    $user->email_verified_at = now();
    $user->save();

    $this->actingAs($user)
        ->get(route('quinielas.index'))
        ->assertOk()
        ->assertSee('Mis quinielas')
        ->assertSee('Unirse con código')
        ->assertSee('Crear')
        ->assertSee('Activas');
});

test('usuario autenticado puede crear una quiniela', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::quinielas.create')
        ->set('name', 'Mundial 2026')
        ->set('description', 'Quiniela de prueba')
        ->call('save')
        ->assertRedirect(route('quinielas.show', Quiniela::first()));

    expect(Quiniela::count())->toBe(1);
    expect(Quiniela::first()->code)->not->toBeEmpty();
    expect(strlen(Quiniela::first()->code))->toBe(8);
    expect(Quiniela::first()->owner_id)->toBe($user->id);
    expect(Quiniela::first()->status)->toBe('active');
});

test('al crear quiniela se genera ScoringRule con defaults', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::quinielas.create')
        ->set('name', 'Test Quiniela')
        ->call('save');

    $quiniela = Quiniela::first();
    expect($quiniela->scoringRule)->not->toBeNull();
    expect($quiniela->scoringRule->points_exact_score)->toBe(3);
    expect($quiniela->scoringRule->points_winner_draw)->toBe(1);
    expect($quiniela->scoringRule->points_one_team_goals)->toBe(0);
});

test('codigo de quiniela es unico y se genera automaticamente', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::quinielas.create')
        ->set('name', 'Quiniela A')
        ->call('save');

    Livewire::actingAs($user)
        ->test('pages::quinielas.create')
        ->set('name', 'Quiniela B')
        ->call('save');

    expect(Quiniela::count())->toBe(2);
    expect(Quiniela::first()->code)->not->toBe(Quiniela::find(2)->code);
});

test('usuario no owner no puede acceder al panel de administracion', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($other)
        ->get(route('quinielas.show', $quiniela))
        ->assertForbidden();
});

test('owner puede ver el panel de administracion', function () {
    $owner = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('quinielas.show', $quiniela))
        ->assertOk()
        ->assertSee($quiniela->name)
        ->assertSee($quiniela->code)
        ->assertSee('Info')
        ->assertSee('Equipos')
        ->assertSee('Grupos')
        ->assertSee('Partidos')
        ->assertSee('Puntuación');
});

test('admin puede crear equipos', function () {
    $owner = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test('pages::quinielas.teams.index', ['quiniela' => $quiniela])
        ->set('newTeamName', 'Argentina')
        ->call('addTeam')
        ->assertHasNoErrors();

    expect($quiniela->teams)->toHaveCount(1);
    expect($quiniela->teams->first()->name)->toBe('Argentina');
});

test('no se puede eliminar equipo con partidos', function () {
    $owner = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $owner->id]);
    $team = Team::factory()->create(['quiniela_id' => $quiniela->id, 'name' => 'Argentina']);

    Fixture::factory()->create([
        'quiniela_id' => $quiniela->id,
        'home_team_id' => $team->id,
        'away_team_id' => Team::factory()->create(['quiniela_id' => $quiniela->id])->id,
    ]);

    expect(Team::count())->toBe(2);

    Livewire::actingAs($owner)
        ->test('pages::quinielas.teams.index', ['quiniela' => $quiniela])
        ->call('deleteTeam', team: $team);

    expect(Team::count())->toBe(2);
});

test('admin puede crear grupos y asignar equipos', function () {
    $owner = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $owner->id]);
    $team = Team::factory()->create(['quiniela_id' => $quiniela->id, 'name' => 'Argentina']);

    Livewire::actingAs($owner)
        ->test('pages::quinielas.groups.index', ['quiniela' => $quiniela])
        ->set('newGroupName', 'Grupo A')
        ->call('addGroup')
        ->assertHasNoErrors();

    $group = Group::first();
    expect($group->name)->toBe('Grupo A');

    Livewire::actingAs($owner)
        ->test('pages::quinielas.groups.index', ['quiniela' => $quiniela])
        ->call('assignTeam', group: $group, teamId: $team->id);

    expect($group->teams)->toHaveCount(1);
    expect($group->teams->first()->name)->toBe('Argentina');
});

test('admin puede crear partidos', function () {
    $owner = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $owner->id]);
    $homeTeam = Team::factory()->create(['quiniela_id' => $quiniela->id, 'name' => 'Argentina']);
    $awayTeam = Team::factory()->create(['quiniela_id' => $quiniela->id, 'name' => 'Brasil']);

    Livewire::actingAs($owner)
        ->test('pages::quinielas.matches.index', ['quiniela' => $quiniela])
        ->set('newHomeTeamId', (string) $homeTeam->id)
        ->set('newAwayTeamId', (string) $awayTeam->id)
        ->set('newMatchDate', '2026-06-15T18:00')
        ->call('addFixture')
        ->assertHasNoErrors();

    expect($quiniela->matches)->toHaveCount(1);
    expect($quiniela->matches->first()->homeTeam->name)->toBe('Argentina');
    expect($quiniela->matches->first()->awayTeam->name)->toBe('Brasil');
    expect($quiniela->matches->first()->is_completed)->toBeFalse();
});

test('admin puede configurar reglas de puntuacion', function () {
    $owner = User::factory()->create();
    $quiniela = Quiniela::factory()->create(['owner_id' => $owner->id]);
    ScoringRule::factory()->create(['quiniela_id' => $quiniela->id]);

    Livewire::actingAs($owner)
        ->test('pages::quinielas.scoring.index', ['quiniela' => $quiniela])
        ->set('pointsExactScore', 5)
        ->set('pointsWinnerDraw', 2)
        ->set('pointsOneTeamGoals', 1)
        ->set('instructions', 'Reglas especiales')
        ->call('save')
        ->assertHasNoErrors();

    $rules = $quiniela->scoringRule()->first();
    expect($rules->points_exact_score)->toBe(5);
    expect($rules->points_winner_draw)->toBe(2);
    expect($rules->points_one_team_goals)->toBe(1);
    expect($rules->instructions)->toBe('Reglas especiales');
});

test('usuario puede unirse a una quiniela con codigo', function () {
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

test('validacion de nombre al crear quiniela', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::quinielas.create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('no se puede unir dos veces a la misma quiniela', function () {
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
