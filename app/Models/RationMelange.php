<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RationMelangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RationMelange extends Model
{
    /** @use HasFactory<RationMelangeFactory> */
    use HasFactory;

    protected $fillable = ['ration_id', 'melange_id', 'quantite', 'is_volonte', 'is_mb'];

    protected function casts(): array
    {
        return [
            'is_volonte' => 'boolean',
            'is_mb' => 'boolean',
        ];
    }

    public function ration(): BelongsTo
    {
        return $this->belongsTo(Ration::class);
    }

    public function melange(): BelongsTo
    {
        return $this->belongsTo(Melange::class);
    }
}
