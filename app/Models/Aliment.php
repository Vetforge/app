<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TypeAliment;
use Database\Factories\AlimentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property string|null $type
 * @property string|null $famille_botanique
 * @property string|null $procede_technologique
 */
class Aliment extends Model
{
    /**
     * Jetons de type d'aliment canoniques attendus par le moteur INRA 2018.
     */
    public const TYPES_CANONIQUES = [TypeAliment::Fourrage->value, TypeAliment::Concentre->value, TypeAliment::Mineral->value];

    public const FAMILLES_BOTANIQUES = ['mais', 'luzerne', 'legumineuse', 'graminee', 'autre'];

    public const PROCEDES_TECHNOLOGIQUES = ['vert', 'ensile', 'foin', 'deshydrate', 'paille', 'concentre', 'mineral', 'autre'];

    /** @use HasFactory<AlimentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $aliment): void {
            $type = TypeAliment::tryFromLoose($aliment->type);
            if ($type !== null) {
                $aliment->type = $type->value;
            } elseif (! $aliment->exists || $aliment->isDirty('type')) {
                throw ValidationException::withMessages(['type' => "Le type de l'aliment doit être fourrage, concentre ou mineral."]);
            }
            if ($type === TypeAliment::Mineral) {
                $aliment->ms = 100.0;
            }
        });
    }

    protected $fillable = [
        'user_id', 'breeder_id', 'code_inra', 'type', 'famille_botanique', 'procede_technologique', 'libelle0', 'libelle1', 'libelle2', 'libelle3', 'libelle4',
        'prix', 'usage_aliment',
        'ms', 'mo', 'mat', 'cb', 'ndf', 'adf', 'adl', 'ee', 'ag', 'eb', 'em', 'amidon', 'sucres', 'pf',
        'd_mo', 'd_ma', 'd_cb', 'd_ndf', 'd_adf', 'd_e', 'dt_n', 'dt6_n', 'dr_n', 'dt_ami', 'dt6_ami', 'dt_ms', 'dt6_ms',
        'ufl', 'ufv', 'uem', 'uel', 'ueb',
        'pdia', 'pdi', 'bpr', 'niref',
        'lys_di', 'met_di', 'his_di', 'arg_di', 'thr_di', 'val_di', 'ile_di', 'leu_di', 'phe_di', 'asp_di', 'ser_di', 'glu_di', 'pro_di', 'gly_di', 'ala_di', 'tyr_di',
        'lys_bp', 'his_bp', 'arg_bp', 'thr_bp', 'val_bp', 'met_bp', 'ile_bp', 'leu_bp', 'phe_bp', 'asp_bp', 'ser_bp', 'glu_bp', 'pro_bp', 'gly_bp', 'ala_bp', 'tyr_bp', 'cys_trp_bp',
        'ca', 'caabs', 'p', 'pabs', 'mg', 'na', 'k', 'cl', 's', 'be', 'baca',
        'cu', 'zn', 'mn', 'co', 'se', 'i', 'molybdene',
        'vit_a', 'vit_d', 'vit_e',
        'c6_10', 'c12_0', 'c14_0', 'c16_0', 'c16_1', 'c18_0', 'c18_1', 'c18_2', 'c18_3', 'c20_0', 'c20_1', 'c22_0', 'c22_1', 'c24_0', 'b_vec',
        'ufl2007', 'ufv2007', 'pdia2007', 'pdie2007', 'pdin2007', 'd_mo2007', 'd_ma2007', 'd_cb2007', 'd_ndf2007', 'd_adf2007', 'uem2007', 'uel2007', 'ueb2007', 'eb2007', 'd_e2007', 'em2007',
    ];

    /** Normalise aussi les anciennes valeurs lors de la lecture et de la copie. */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?string => self::canonicalType($value),
            set: function (mixed $value): ?string {
                if ($value !== null && ! is_string($value) && ! $value instanceof TypeAliment) {
                    throw new \InvalidArgumentException("Le type d'aliment doit être une chaîne ou un TypeAliment.");
                }
                $canonical = self::canonicalType($value);
                if ($canonical !== null && TypeAliment::tryFrom($canonical) === null) {
                    throw new \InvalidArgumentException("Type d'aliment inconnu : {$canonical}");
                }

                return $canonical;
            },
        );
    }

    /**
     * Les minéraux sont exprimés à 100 % MS, y compris dans les anciennes données.
     *
     * @return Attribute<float|int|string|null, float|int|string|null>
     */
    protected function ms(): Attribute
    {
        return Attribute::make(
            get: fn (float|int|string|null $value): float|int|string|null => $this->type === TypeAliment::Mineral->value ? 100.0 : $value,
        );
    }

    protected function familleBotanique(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => self::canonicalClassification($value, self::FAMILLES_BOTANIQUES),
        );
    }

    protected function procedeTechnologique(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => self::canonicalClassification($value, self::PROCEDES_TECHNOLOGIQUES),
        );
    }

    /** Préserve les valeurs inconnues pour permettre leur correction et leur rejet par la validation. */
    public static function canonicalType(mixed $value): ?string
    {
        $type = TypeAliment::tryFromLoose($value);
        if ($type !== null) {
            return $type->value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param list<string> $allowed */
    private static function canonicalClassification(mixed $value, array $allowed): ?string
    {
        $normalized = Str::of((string) $value)->lower()->ascii()->snake()->value();

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    /**
     * Convertit une fois les libellés du référentiel historique en codes persistés.
     * Le moteur ne relit jamais ces libellés pour sélectionner une équation.
     *
     * @return array{famille_botanique: string, procede_technologique: string}
     */
    public static function classificationFromReference(string $type, string $familyLabel, string $detailLabel): array
    {
        $detail = Str::of($familyLabel.' '.$detailLabel)->lower()->ascii()->squish()->value();

        return [
            'famille_botanique' => match (true) {
                str_contains($detail, 'mais') => 'mais',
                str_contains($detail, 'luzerne') => 'luzerne',
                str_contains($detail, 'trefle'), str_contains($detail, 'legumineuse') => 'legumineuse',
                str_contains($detail, 'prairie'), str_contains($detail, 'graminee') => 'graminee',
                default => 'autre',
            },
            'procede_technologique' => match (true) {
                self::canonicalType($type) === TypeAliment::Mineral->value => 'mineral',
                str_contains($detail, 'deshydrat') => 'deshydrate',
                str_contains($detail, 'ensilage') => 'ensile',
                str_contains($detail, 'foin') => 'foin',
                str_contains($detail, 'paille') => 'paille',
                str_contains($detail, 'fourrages verts') => 'vert',
                self::canonicalType($type) === TypeAliment::Concentre->value => 'concentre',
                default => 'autre',
            },
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Breeder, $this> */
    public function breeder(): BelongsTo
    {
        return $this->belongsTo(Breeder::class);
    }

    #[Scope]
    protected function forBreeder(Builder $query, ?int $breederId): void
    {
        $query->where(function (Builder $query) use ($breederId): void {
            $query->whereNull('breeder_id');
            if ($breederId !== null) {
                $query->orWhere('breeder_id', $breederId);
            }
        });
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
