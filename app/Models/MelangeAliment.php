<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MelangeAliment extends Model
{
    /** @use HasFactory<\Database\Factories\MelangeAlimentFactory> */
    use HasFactory;

    protected $fillable = ['melange_id', 'aliment_id', 'quantite', 'is_mb', 'ordre'];

    protected static function booted(): void
    {
        static::deleting(function (MelangeAliment $melangeAliment): void {
            $aliment = $melangeAliment->aliment;
            if ($aliment && $aliment->usage_aliment === 2) {
                $aliment->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_mb' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Melange, $this>
     */
    public function melange(): BelongsTo
    {
        return $this->belongsTo(Melange::class);
    }

    /**
     * @return BelongsTo<Aliment, $this>
     */
    public function aliment(): BelongsTo
    {
        return $this->belongsTo(Aliment::class);
    }
}
