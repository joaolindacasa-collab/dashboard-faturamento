<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Magalu Marketplace" e "Magalu" são o mesmo canal de venda. O alias em
 * config/tiny.php passa a normalizar os pedidos NOVOS pra "Magalu"; esta
 * migration unifica os que já estão gravados como "Magalu Marketplace".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->whereRaw('LOWER(channel) = ?', ['magalu marketplace'])
            ->update(['channel' => 'Magalu']);
    }

    public function down(): void
    {
        // Irreversível: depois de unir, não dá pra saber quais "Magalu" vieram
        // de "Magalu Marketplace". No-op de propósito.
    }
};
