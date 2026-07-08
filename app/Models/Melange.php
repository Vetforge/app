<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MelangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Melange extends Model
{
    /** @use HasFactory<MelangeFactory> */
    use HasFactory;

    protected $fillable = ['ration_id', 'nom', 'quantite', 'is_volonte', 'is_mb', 'ordre'];

    protected function casts(): array
    {
        return [
            'quantite' => 'float',
            'is_volonte' => 'boolean',
            'is_mb' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Melange $melange): void {
            $melange->melangeAliments->each->delete();
        });
    }

    /**
     * @return BelongsTo<Ration, $this>
     */
    public function ration(): BelongsTo
    {
        return $this->belongsTo(Ration::class);
    }

    /**
     * @return HasMany<MelangeAliment, $this>
     */
    public function melangeAliments(): HasMany
    {
        return $this->hasMany(MelangeAliment::class)->orderBy('ordre');
    }

    private ?float $recipeTotalMsMemo = null;

    private ?float $recipeTotalMbMemo = null;

    /**
     * Poids MS d'un ingrédient dans la recette du mélange.
     */
    private function recipeMsForAliment(MelangeAliment $melangeAliment): float
    {
        $qty = (float) ($melangeAliment->quantite ?? 0);
        if ($qty <= 0) {
            return 0.0;
        }
        $ms = (float) ($melangeAliment->aliment->ms ?? 0);

        return $melangeAliment->is_mb ? $qty * $ms / 100 : $qty;
    }

    /**
     * Poids MB d'un ingrédient dans la recette du mélange.
     */
    private function recipeMbForAliment(MelangeAliment $melangeAliment): float
    {
        $qty = (float) ($melangeAliment->quantite ?? 0);
        if ($qty <= 0) {
            return 0.0;
        }
        $ms = (float) ($melangeAliment->aliment->ms ?? 0);

        return $melangeAliment->is_mb ? $qty : ($ms > 0 ? $qty * 100 / $ms : 0.0);
    }

    private function recipeTotalMs(): float
    {
        return $this->recipeTotalMsMemo ??= (float) $this->melangeAliments->sum(
            fn (MelangeAliment $ma): float => $this->recipeMsForAliment($ma)
        );
    }

    private function recipeTotalMb(): float
    {
        return $this->recipeTotalMbMemo ??= (float) $this->melangeAliments->sum(
            fn (MelangeAliment $ma): float => $this->recipeMbForAliment($ma)
        );
    }

    /**
     * Quantités effectives d'un ingrédient du mélange dans la ration.
     *
     * Mise à l'échelle directe : si le mélange est en MB, on scale par MB ;
     * si en MS, on scale par MS. Le ratio MS/MB de chaque ingrédient est préservé.
     *
     * @return array{qty_ms: float, qty_mb: float}
     */
    public function effectiveContributionForAliment(MelangeAliment $melangeAliment): array
    {
        $mixQuantity = (float) ($this->quantite ?? 0);
        $ingredientMs = $this->recipeMsForAliment($melangeAliment);
        $ingredientMb = $this->recipeMbForAliment($melangeAliment);

        if ($mixQuantity <= 0 || ($ingredientMs <= 0 && $ingredientMb <= 0)) {
            return ['qty_ms' => 0.0, 'qty_mb' => 0.0];
        }

        if ($this->is_mb) {
            $recipeTotalMb = $this->recipeTotalMb();
            if ($recipeTotalMb <= 0) {
                return ['qty_ms' => 0.0, 'qty_mb' => 0.0];
            }
            $scale = $mixQuantity / $recipeTotalMb;
        } else {
            $recipeTotalMs = $this->recipeTotalMs();
            if ($recipeTotalMs <= 0) {
                return ['qty_ms' => 0.0, 'qty_mb' => 0.0];
            }
            $scale = $mixQuantity / $recipeTotalMs;
        }

        return [
            'qty_ms' => $ingredientMs * $scale,
            'qty_mb' => $ingredientMb * $scale,
        ];
    }
}
