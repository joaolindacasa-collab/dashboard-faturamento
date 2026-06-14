<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinyToken extends Model
{
    protected $fillable = [
        'company',
        'refresh_token',
        'access_token',
        'access_expires_at',
        'scope',
        'refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'access_expires_at' => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }
}
