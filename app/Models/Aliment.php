<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AlimentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Aliment extends Model
{
    /**
     * Jetons de type d'aliment canoniques attendus par le moteur INRA 2018.
     */
    public const TYPES_CANONIQUES = ['Fourrage', 'Conc', 'Mineral'];

    /** @use HasFactory<AlimentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'code_inra', 'type', 'libelle0', 'libelle1', 'libelle2', 'libelle3', 'libelle4',
        'prix', 'usage_aliment',
        'ms', 'mo', 'mat', 'cb', 'ndf', 'adf', 'adl', 'ee', 'ag', 'eb', 'em', 'amidon', 'sucres', 'pf',
        'd_mo', 'd_ma', 'd_cb', 'd_ndf', 'd_adf', 'd_e', 'dt_n', 'dt6_n', 'dr_n', 'dt_ami', 'dt6_ami', 'dt_ms', 'dt6_ms',
        'ufl', 'ufv', 'uem', 'uel', 'ueb',
        'pdia', 'pdi', 'bpr', 'niref',
        'lys_di', 'met_di', 'his_di', 'arg_di', 'thr_di', 'val_di', 'ile_di', 'leu_di', 'phe_di', 'asp_di', 'ser_di', 'glu_di', 'pro_di', 'gly_di', 'ala_di', 'tyr_di',
        'lys_bp', 'his_bp', 'arg_bp', 'thr_bp', 'val_bp', 'met_bp', 'ile_bp', 'leu_bp', 'phe_bp', 'asp_bp', 'ser_bp', 'glu_bp', 'pro_bp', 'gly_bp', 'ala_bp', 'tyr_bp', 'cys_trp_bp',
        'ca', 'caabs', 'p', 'pabs', 'mg', 'na', 'k', 'cl', 's', 'be', 'baca',
        'cu', 'zn', 'mn', 'co', 'se', 'i',
        'vit_a', 'vit_d', 'vit_e',
        'c6_10', 'c12_0', 'c14_0', 'c16_0', 'c16_1', 'c18_0', 'c18_1', 'c18_2', 'c18_3', 'c20_0', 'c20_1', 'c22_0', 'c22_1', 'c24_0', 'b_vec',
        'ufl2007', 'ufv2007', 'pdia2007', 'pdie2007', 'pdin2007', 'd_mo2007', 'd_ma2007', 'd_cb2007', 'd_ndf2007', 'd_adf2007', 'uem2007', 'uel2007', 'ueb2007', 'eb2007', 'd_e2007', 'em2007',
    ];

    /**
     * Le type est normalisé vers un jeton canonique à l'écriture, quelle que soit la source
     * (formulaire, import CSV, seeder) : « Concentré », « concentre », « Minéral »… deviennent
     * respectivement « Conc », « Mineral ». Le moteur compare des jetons stricts (cf. ALI-01).
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => self::canonicalType($value),
        );
    }

    /**
     * Résout une valeur de type libre vers un jeton canonique du moteur, ou null si vide.
     * Une valeur non reconnue est conservée telle quelle afin de rester visible/corrigeable.
     */
    public static function canonicalType(mixed $value): ?string
    {
        $normalized = Str::of((string) $value)->lower()->ascii()->squish()->value();

        return match (true) {
            $normalized === '' => null,
            str_starts_with($normalized, 'fourrage') => 'Fourrage',
            str_contains($normalized, 'conc') => 'Conc',
            str_contains($normalized, 'miner') => 'Mineral',
            default => trim((string) $value),
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MelangeAliment, $this>
     */
    public function melangeAliments(): HasMany
    {
        return $this->hasMany(MelangeAliment::class);
    }

    /**
     * @return HasMany<RationAliment, $this>
     */
    public function rationAliments(): HasMany
    {
        return $this->hasMany(RationAliment::class);
    }
}
