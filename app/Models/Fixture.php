<?php

namespace App\Models;

use Database\Factories\FixtureFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiniela_id
 * @property int $home_team_id
 * @property int $away_team_id
 * @property int|null $group_id
 * @property string|null $phase
 * @property string|null $round
 * @property Carbon $match_date
 * @property string|null $venue
 * @property int|null $home_score
 * @property int|null $away_score
 * @property bool $is_completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quiniela $quiniela
 * @property-read Team $homeTeam
 * @property-read Team $awayTeam
 * @property-read Group|null $group
 * @property-read Collection<int, Prediction> $predictions
 */
class Fixture extends Model
{
    /** @use HasFactory<FixtureFactory> */
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'quiniela_id',
        'home_team_id',
        'away_team_id',
        'group_id',
        'phase',
        'round',
        'match_date',
        'venue',
        'home_score',
        'away_score',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'match_date' => 'datetime',
            'is_completed' => 'boolean',
        ];
    }

    public function quiniela(): BelongsTo
    {
        return $this->belongsTo(Quiniela::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }
}
