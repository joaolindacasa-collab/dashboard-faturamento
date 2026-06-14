<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'company',
        'tiny_order_id',
        'order_date',
        'value',
        'status_code',
        'channel_raw',
        'channel',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'value' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }
}
