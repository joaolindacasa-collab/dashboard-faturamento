<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'mode',
        'status',
        'message',
        'totals',
        'orders_seen',
        'orders_upserted',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'totals' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
