<?php

namespace App\Models;

use Database\Factories\PredictionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $prediction_id
 * @property int $match_id
 * @property int $user_id
 * @property int $changed_by_user_id
 * @property int|null $old_home_score
 * @property int|null $old_away_score
 * @property int|null $new_home_score
 * @property int|null $new_away_score
 * @property string $action
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property-read Prediction $prediction
 * @property-read Fixture $match
 * @property-read User $user
 * @property-read User $changedBy
 */
class PredictionLog extends Model
{
    /** @use HasFactory<PredictionLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'prediction_id',
        'match_id',
        'user_id',
        'changed_by_user_id',
        'old_home_score',
        'old_away_score',
        'new_home_score',
        'new_away_score',
        'action',
        'reason',
    ];

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Fixture::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
