<?php

namespace App\Console\Commands;

use App\Services\Tiny\OrderSyncService;
use Illuminate\Console\Command;

class TinySyncCommand extends Command
{
    protected $signature = 'tiny:sync {--mode=incremental : incremental | full} {--company= : limita a uma ou mais empresas (ex.: linda ou linda,gv)} {--month= : re-puxa só um mês YYYY-MM (backfill)} {--from= : data inicial YYYY-MM-DD (com --to)} {--to= : data final YYYY-MM-DD (com --from)}';

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

        $month = $this->option('month');
        if ($month && ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("--month inválido: {$month}. Use o formato YYYY-MM (ex.: 2026-06).");
            return self::FAILURE;
        }

        $from = $this->option('from');
        $to = $this->option('to');
        if (($from && ! $to) || ($to && ! $from)) {
            $this->error('Use --from e --to juntos (YYYY-MM-DD).');
            return self::FAILURE;
        }
        foreach (['from' => $from, 'to' => $to] as $k => $v) {
            if ($v && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                $this->error("--{$k} inválido: {$v}. Use YYYY-MM-DD.");
                return self::FAILURE;
            }
        }

        $this->info("Sync iniciado (modo: {$mode}"
            . ($month ? ", mês: {$month}" : '')
            . ($from ? ", de {$from} até {$to}" : '')
            . ($onlyCompanies ? ', empresas: ' . implode(',', $onlyCompanies) : '') . ')...');

        try {
            $log = $sync->sync($mode, function (string $slug, string $info, int $n) {
                if (str_starts_with($info, 'PULADA')) {
                    $this->warn("  [{$slug}] {$info}");
                } elseif ($n > 0) {
                    $this->line("  [{$slug}] {$info}: {$n} pedidos brutos");
                }
            }, $onlyCompanies, $month, $from, $to);
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
