<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unifica canais que são o mesmo canal de venda mas vinham com nomes distintos:
 *   - "Magalu Marketplace" -> "Magalu"
 *   - "Amazon Fba Classic" -> "Amazon"
 *
 * Os aliases em config/tiny.php normalizam os pedidos NOVOS; esta migration
 * reescreve os que já estão gravados (histórico), pra que "Por canal" e a matriz
 * mostrem uma linha única por canal e o comparativo mês a mês fique consistente.
 */
return new class extends Migration
{
    /** @var array<string,string> lower(canal atual) => canal unificado */
    private array $map = [
        'magalu marketplace' => 'Magalu',
        'amazon fba classic' => 'Amazon',
    ];

    public function up(): void
    {
        foreach ($this->map as $from => $to) {
            DB::table('orders')
                ->whereRaw('LOWER(channel) = ?', [$from])
                ->update(['channel' => $to]);
        }
    }

    public function down(): void
    {
        // Irreversível: depois de unir, não dá pra saber a origem de cada pedido.
        // No-op de propósito.
    }
};
