<?php

namespace App\Models;

use Database\Factories\QuinielaFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $code
 * @property int $owner_id
 * @property string $status
 * @property Carbon|null $prediction_edit_deadline
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $owner
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Group> $groups
 * @property-read Collection<int, Fixture> $matches
 */
class Quiniela extends Model
{
    /** @use HasFactory<QuinielaFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'code',
        'owner_id',
        'status',
        'prediction_edit_deadline',
    ];

    protected function casts(): array
    {
        return [
            'prediction_edit_deadline' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    public function scoringRule(): HasOne
    {
        return $this->hasOne(ScoringRule::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quiniela_user')
            ->withPivot('joined_at', 'deleted_at');
    }
}
