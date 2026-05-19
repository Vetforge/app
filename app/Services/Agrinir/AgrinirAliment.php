<?php

declare(strict_types=1);

namespace App\Services\Agrinir;

use BadMethodCallException;

/**
 * @method self setMS(float $value)
 * @method self setADF(float $value)
 * @method self setNDF(float $value)
 * @method self setUFL2007(float $value)
 * @method self setUFV2007(float $value)
 * @method self setPDIE2007(float $value)
 * @method self setPDIN2007(float $value)
 * @method self setPDIA2007(float $value)
 * @method self setUEM2007(float $value)
 * @method self setUEL2007(float $value)
 * @method self setUEB2007(float $value)
 * @method self setMO(float $value)
 * @method self setDMO2007(float $value)
 * @method self setMAT(float $value)
 * @method self setDMA2007(float $value)
 * @method self setCB(float $value)
 * @method self setAmidon(float $value)
 * @method self setCa(float $value)
 * @method self setCaabs(float $value)
 * @method self setP(float $value)
 * @method self setPabs(float $value)
 * @method self setMg(float $value)
 * @method float|null getUFL2007()
 * @method float|null getUFV2007()
 * @method float|null getPDIA2007()
 * @method float|null getPDIE2007()
 * @method float|null getPDIN2007()
 * @method float|null getDMO2007()
 * @method float|null getDMA2007()
 * @method float|null getUEM2007()
 * @method float|null getUEL2007()
 * @method float|null getUEB2007()
 */
final class AgrinirAliment
{
    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    public function __call(string $method, array $arguments): mixed
    {
        if (str_starts_with($method, 'set')) {
            $this->attributes[substr($method, 3)] = $arguments[0] ?? null;

            return $this;
        }

        if (str_starts_with($method, 'get')) {
            return $this->attributes[substr($method, 3)] ?? null;
        }

        throw new BadMethodCallException("Méthode legacy Agrinir non supportée : {$method}");
    }
}
