<?php

namespace App\Console\Commands;

use App\Services\Tiny\OrderSyncService;
use Illuminate\Console\Command;

class TinySyncCommand extends Command
{
    protected $signature = 'tiny:sync {--mode=incremental : incremental | full}';

    protected $description = 'Sincroniza pedidos do Tiny v3 para a base (incremental ou full)';

    public function handle(OrderSyncService $sync): int
    {
        $mode = $this->option('mode');
        if (! in_array($mode, ['incremental', 'full'], true)) {
            $this->error("--mode inválido: {$mode}. Use incremental ou full.");
            return self::FAILURE;
        }

        $this->info("Sync iniciado (modo: {$mode})...");

        try {
            $log = $sync->sync($mode, function (string $slug, string $day, int $n) {
                if ($n > 0) {
                    $this->line("  [{$slug}] {$day}: {$n} pedidos brutos");
                }
            });
        } catch (\Throwable $e) {
            $this->error('ERRO: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info($log->message ?? "OK ({$mode}).");
        return self::SUCCESS;
    }
}
