<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmoCrmToken extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'access_token',
        'refresh_token',
        'base_domain',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Проверяет, истек ли токен доступа
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
