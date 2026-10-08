<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

enum TypeAliment: string
{
    case Fourrage = 'fourrage';
    case Concentre = 'concentre';
    case Mineral = 'mineral';

    public function label(): string
    {
        return $this->value;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromLoose(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (! is_string($value)) {
            return null;
        }

        if (($canonical = self::tryFrom($value)) !== null) {
            return $canonical;
        }

        $normalized = Str::of($value)->lower()->ascii()->squish()->value();

        return match ($normalized) {
            'fourrage', 'fourrages' => self::Fourrage,
            'conc', 'concentre', 'concentres' => self::Concentre,
            'mineral', 'mineraux' => self::Mineral,
            default => null,
        };
    }
}
