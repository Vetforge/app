<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class PdfClinicHeader
{
    /**
     * @return array{name: string|null, address: string|null, postal_code: string|null, city: string|null, phone: string|null, email: string|null}|null
     */
    public static function forUser(?Authenticatable $user): ?array
    {
        if (! $user instanceof User || ! is_array($user->clinic_profile)) {
            return null;
        }

        $profile = $user->clinic_profile;
        $header = [
            'name' => self::stringValue($profile['name'] ?? null),
            'address' => self::stringValue($profile['address'] ?? null),
            'postal_code' => self::stringValue($profile['postal_code'] ?? null),
            'city' => self::stringValue($profile['city'] ?? null),
            'phone' => self::stringValue($profile['phone'] ?? null),
            'email' => self::stringValue($profile['email'] ?? null),
        ];

        if (collect($header)->every(fn ($value): bool => $value === null)) {
            return null;
        }

        return $header;
    }

    private static function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
