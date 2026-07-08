<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Espèce de ruminant couverte par le référentiel INRA 2018.
 */
enum Espece: string
{
    case Bovin = 'bovin';
    case Ovin = 'ovin';
    case Caprin = 'caprin';

    /**
     * Libellé affichable (pluriel, tel qu'utilisé dans les regroupements d'interface).
     */
    public function label(): string
    {
        return match ($this) {
            self::Bovin => 'Bovins',
            self::Ovin => 'Ovins',
            self::Caprin => 'Caprins',
        };
    }
}
