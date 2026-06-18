<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiniela_id
 * @property string $name
 * @property string|null $flag_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quiniela $quiniela
 * @property-read Collection<int, Group> $groups
 * @property-read Collection<int, Fixture> $homeMatches
 * @property-read Collection<int, Fixture> $awayMatches
 */
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'quiniela_id',
        'name',
        'flag_url',
    ];

    public function quiniela(): BelongsTo
    {
        return $this->belongsTo(Quiniela::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'team_group');
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(Fixture::class, 'home_team_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(Fixture::class, 'away_team_id');
    }
}
