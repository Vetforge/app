<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanRationnement extends Model
{
    /** @use HasFactory<\Database\Factories\PlanRationnementFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'breeder_id', 'nom', 'date', 'inra'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Breeder, $this>
     */
    public function breeder(): BelongsTo
    {
        return $this->belongsTo(Breeder::class);
    }

    /**
     * @return HasMany<Ration, $this>
     */
    public function rations(): HasMany
    {
        return $this->hasMany(Ration::class);
    }
}
