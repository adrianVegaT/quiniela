<?php

use App\Models\Quiniela;
use App\Models\User;
use App\Services\PredictionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::prefix('quinielas')->name('quinielas.')->group(function () {
        Route::livewire('/', 'pages::quinielas.index')->name('index');
        Route::livewire('/create', 'pages::quinielas.create')->name('create');
        Route::livewire('/{quiniela}', 'pages::quinielas.show')->name('show');
        Route::livewire('/{quiniela}/today', 'pages::quinielas.today.index')->name('today');
        Route::livewire('/{quiniela}/teams', 'pages::quinielas.teams.index')->name('teams');
        Route::livewire('/{quiniela}/groups', 'pages::quinielas.groups.index')->name('groups');
        Route::livewire('/{quiniela}/matches', 'pages::quinielas.matches.index')->name('matches');
        Route::livewire('/{quiniela}/scoring', 'pages::quinielas.scoring.index')->name('scoring');
        Route::livewire('/{quiniela}/predictions', 'pages::quinielas.predictions.index')->name('predictions');
        Route::livewire('/{quiniela}/rules', 'pages::quinielas.rules.index')->name('rules');
        Route::livewire('/{quiniela}/results', 'pages::quinielas.results.index')->name('results');
        Route::livewire('/{quiniela}/leaderboard', 'pages::quinielas.leaderboard.index')->name('leaderboard');
        Route::livewire('/{quiniela}/today', 'pages::quinielas.today.index')->name('today');
        Route::livewire('/{quiniela}/view', 'pages::quinielas.show-public')->name('show-public');
        Route::livewire('/{quiniela}/users/{user}', 'pages::quinielas.user.show')->name('user.show');
        Route::livewire('/{quiniela}/log', 'pages::quinielas.log.index')->name('log');
    });

    Route::get('/quinielas/{quiniela}/users/{user}/pdf', function (Quiniela $quiniela, User $user) {
        $currentUser = auth()->user();
        $isOwner = $currentUser->id === $quiniela->owner_id;
        $isSelf = $currentUser->id === $user->id;
        $canViewOthers = app(PredictionService::class)->canViewOtherPredictions($quiniela, $currentUser);

        if (! $isOwner && ! $isSelf && ! $canViewOthers) {
            abort(403);
        }

        $matches = $quiniela->matches()
            ->with(['homeTeam', 'awayTeam', 'group'])
            ->orderByRaw('group_id IS NULL, group_id')
            ->orderBy('match_date')
            ->get()
            ->map(function ($fixture) use ($user) {
                $prediction = $fixture->predictions()
                    ->where('user_id', $user->id)
                    ->first();

                return (object) [
                    'match' => $fixture,
                    'home_score' => $prediction?->home_score,
                    'away_score' => $prediction?->away_score,
                    'has_prediction' => $prediction !== null,
                ];
            });

        $pdf = Pdf::loadView('pdf.predictions', [
            'quiniela' => $quiniela,
            'user' => $user,
            'matches' => $matches,
        ]);

        return $pdf->download('boleta-'.$quiniela->code.'-'.$user->id.'.pdf');
    })->name('quinielas.users.pdf');
});

require __DIR__.'/settings.php';
