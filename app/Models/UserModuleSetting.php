<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserModuleSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $settings
 */
class UserModuleSetting extends Model
{
    /** @use HasFactory<UserModuleSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'module',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
