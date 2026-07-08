<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'last_login_at',
        'normes_personnalisees',
        'clinic_profile',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'normes_personnalisees' => 'array',
            'clinic_profile' => 'array',
        ];
    }

    public function aliments(): HasMany
    {
        return $this->hasMany(Aliment::class);
    }

    public function breeders(): HasMany
    {
        return $this->hasMany(Breeder::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function moduleSettings(): HasMany
    {
        return $this->hasMany(UserModuleSetting::class);
    }

    public function melanges(): HasMany
    {
        return $this->hasMany(Melange::class);
    }

    public function planRationnements(): HasMany
    {
        return $this->hasMany(PlanRationnement::class);
    }
}
