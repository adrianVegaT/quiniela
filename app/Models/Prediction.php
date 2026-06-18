<?php

namespace App\Models;

use Database\Factories\PredictionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_id
 * @property int $user_id
 * @property int|null $home_score
 * @property int|null $away_score
 * @property bool $is_partial
 * @property int|null $exact_score_points
 * @property int|null $winner_draw_points
 * @property int|null $one_team_goals_points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Fixture $match
 * @property-read User $user
 * @property-read Collection<int, PredictionLog> $logs
 */
class Prediction extends Model
{
    /** @use HasFactory<PredictionFactory> */
    use HasFactory;

    protected $fillable = [
        'match_id',
        'user_id',
        'home_score',
        'away_score',
        'is_partial',
        'exact_score_points',
        'winner_draw_points',
        'one_team_goals_points',
    ];

    protected function casts(): array
    {
        return [
            'is_partial' => 'boolean',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Fixture::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PredictionLog::class);
    }
}
