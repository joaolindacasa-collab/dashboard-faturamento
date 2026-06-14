<?php

namespace App\Console\Commands;

use App\Services\Tiny\OrderSyncService;
use Illuminate\Console\Command;

class TinySyncCommand extends Command
{
    protected $signature = 'tiny:sync {--mode=incremental : incremental | full} {--company= : limita a uma ou mais empresas (ex.: linda ou linda,gv)}';

    protected $description = 'Sincroniza pedidos do Tiny v3 para a base (incremental ou full). Empresas não conectadas são puladas.';

    public function handle(OrderSyncService $sync): int
    {
        $mode = $this->option('mode');
        if (! in_array($mode, ['incremental', 'full'], true)) {
            $this->error("--mode inválido: {$mode}. Use incremental ou full.");
            return self::FAILURE;
        }

        $only = $this->option('company');
        $onlyCompanies = $only ? array_map('trim', explode(',', $only)) : null;

        $this->info("Sync iniciado (modo: {$mode}" . ($onlyCompanies ? ', empresas: ' . implode(',', $onlyCompanies) : '') . ')...');

        try {
            $log = $sync->sync($mode, function (string $slug, string $info, int $n) {
                if (str_starts_with($info, 'PULADA')) {
                    $this->warn("  [{$slug}] {$info}");
                } elseif ($n > 0) {
                    $this->line("  [{$slug}] {$info}: {$n} pedidos brutos");
                }
            }, $onlyCompanies);
        } catch (\Throwable $e) {
            $this->error('ERRO: '.$e->getMessage());
            return self::FAILURE;
        }

        if ($log->status === 'ok') {
            $this->info($log->message);
            return self::SUCCESS;
        }

        $this->error($log->message ?? 'Nenhuma empresa sincronizada.');
        return self::FAILURE;
    }
}
