<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RationAliment extends Model
{
    /** @use HasFactory<\Database\Factories\RationAlimentFactory> */
    use HasFactory;

    protected $fillable = ['ration_id', 'aliment_id', 'quantite', 'is_volonte', 'is_mb', 'ordre'];

    protected static function booted(): void
    {
        static::deleting(function (RationAliment $rationAliment): void {
            $aliment = $rationAliment->aliment;
            if ($aliment && $aliment->usage_aliment === 2) {
                $aliment->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_volonte' => 'boolean',
            'is_mb' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Ration, $this>
     */
    public function ration(): BelongsTo
    {
        return $this->belongsTo(Ration::class);
    }

    /**
     * @return BelongsTo<Aliment, $this>
     */
    public function aliment(): BelongsTo
    {
        return $this->belongsTo(Aliment::class);
    }
}
