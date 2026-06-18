<?php

namespace App\Models;

use Database\Factories\ScoringRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiniela_id
 * @property int $points_exact_score
 * @property int $points_winner_draw
 * @property int $points_one_team_goals
 * @property string|null $instructions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quiniela $quiniela
 */
class ScoringRule extends Model
{
    /** @use HasFactory<ScoringRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'quiniela_id',
        'points_exact_score',
        'points_winner_draw',
        'points_one_team_goals',
        'instructions',
    ];

    public function quiniela(): BelongsTo
    {
        return $this->belongsTo(Quiniela::class);
    }
}
