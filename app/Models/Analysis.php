<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Analysis extends Model
{
    /** @use HasFactory<\Database\Factories\AnalysisFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'breeder_id',
        'animal_nom',
        'module',
        'status',
        'sampled_at',
        'analyzed_at',
        'intervenant',
        'payload',
        'results',
        'settings_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'date:Y-m-d',
            'analyzed_at' => 'date:Y-m-d',
            'payload' => 'array',
            'results' => 'array',
            'settings_snapshot' => 'array',
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
}
