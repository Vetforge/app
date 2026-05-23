<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Breeder extends Model
{
    /** @use HasFactory<\Database\Factories\BreederFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'postal_code',
        'city',
        'phone',
        'email',
        'herd_number',
        'notes',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Analysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * @return HasMany<PlanRationnement, $this>
     */
    public function planRationnements(): HasMany
    {
        return $this->hasMany(PlanRationnement::class);
    }
}
