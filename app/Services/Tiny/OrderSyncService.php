<?php

namespace App\Services\Tiny;

use App\Models\Order;
use App\Models\SyncLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza pedidos do Tiny v3 -> tabela `orders`.
 *
 * Equivalente ao update_dashboard.py (full_sync_company / incremental_sync_company).
 * Chunk dia-a-dia via dataPedido (a v3 rejeita ranges grandes; e o filtro
 * dataAlteracao dá HTTP 400 — por isso re-fetch por dia).
 */
class OrderSyncService
{
    public function __construct(private TinyClient $client)
    {
    }

    /**
     * full: re-puxa mês atual + anterior, dia a dia, e remove da base os
     * pedidos desses meses que não voltaram (status mudou pra fora do filtro,
     * ex.: cancelado). incremental: só os últimos N dias.
     */
    public function sync(string $mode = 'incremental', ?callable $onProgress = null, ?array $onlyCompanies = null): SyncLog
    {
        $tz = config('tiny.timezone', 'America/Sao_Paulo');
        $now = Carbon::now($tz);
        $statusFilter = collect(config('tiny.status_filter', []))->map(fn ($s) => (string) $s)->all();

        $log = SyncLog::create([
            'mode' => $mode,
            'status' => 'ok',
            'started_at' => now(),
            'orders_seen' => 0,
            'orders_upserted' => 0,
        ]);

        $totals = [];
        $totalSeen = 0;
        $totalUpserted = 0;
        $okCompanies = [];
        $skipped = [];

        $slugs = array_keys($this->client->companies());
        if ($onlyCompanies) {
            $slugs = array_values(array_filter($slugs, fn ($s) => in_array($s, $onlyCompanies, true)));
        }

        // Cada empresa é isolada: se uma não está conectada / sem credenciais,
        // ela é PULADA (logada) e o sync continua com as demais.
        foreach ($slugs as $slug) {
            try {
                [$days, $isFullScope] = $this->daysToFetch($mode, $now);

                $access = $this->client->accessTokenFor($slug);

                $seenIds = []; // por mês: ['YYYY-MM' => [tiny_order_id,...]]
                $seen = 0;
                $upserted = 0;

                foreach ($days as $day) {
                    $dayYmd = $day->format('Y-m-d');
                    $raws = $this->client->fetchOrdersForDay($slug, $access, $dayYmd);
                    foreach ($raws as $raw) {
                        $seen++;
                        $rec = $this->extractRecord($raw);
                        if ($rec === null) {
                            continue;
                        }
                        if (! in_array($rec['status_code'], $statusFilter, true)) {
                            continue;
                        }
                        $monthKey = substr($rec['order_date'], 0, 7);
                        $seenIds[$monthKey][] = $rec['tiny_order_id'];

                        Order::updateOrCreate(
                            ['company' => $slug, 'tiny_order_id' => $rec['tiny_order_id']],
                            [
                                'order_date'  => $rec['order_date'],
                                'value'       => $rec['value'],
                                'status_code' => $rec['status_code'],
                                'channel_raw' => $rec['channel_raw'],
                                'channel'     => ChannelNormalizer::normalize($rec['channel_raw']),
                                'synced_at'   => now(),
                            ]
                        );
                        $upserted++;
                    }
                    if ($onProgress) {
                        $onProgress($slug, $dayYmd, count($raws));
                    }
                }

                // No full: limpa pedidos dos meses varridos que não vieram mais
                // (mudaram pra status fora do filtro). No incremental não dá pra
                // saber se sumiu, então não removemos.
                if ($isFullScope) {
                    foreach ($seenIds as $monthKey => $ids) {
                        Order::where('company', $slug)
                            ->whereYear('order_date', substr($monthKey, 0, 4))
                            ->whereMonth('order_date', substr($monthKey, 5, 2))
                            ->whereNotIn('tiny_order_id', $ids)
                            ->delete();
                    }
                }

                $totals[$slug] = ['seen' => $seen, 'upserted' => $upserted];
                $totalSeen += $seen;
                $totalUpserted += $upserted;
                $okCompanies[] = $slug;
            } catch (\Throwable $e) {
                Log::warning("[tiny:sync] empresa '{$slug}' pulada: ".$e->getMessage());
                $totals[$slug] = ['error' => $e->getMessage()];
                $skipped[$slug] = $e->getMessage();
                if ($onProgress) {
                    $onProgress($slug, 'PULADA: '.$e->getMessage(), 0);
                }
            }
        }

        // status ok se pelo menos uma empresa sincronizou; error só se nenhuma.
        $status = empty($okCompanies) ? 'error' : 'ok';
        $parts = [($status === 'ok' ? 'OK' : 'FALHOU')." ({$mode})", count($okCompanies).' empresa(s) sincronizada(s)'];
        if ($skipped) {
            $parts[] = count($skipped).' pulada(s): '.implode(', ', array_keys($skipped));
        }
        $parts[] = "vistos {$totalSeen}, gravados {$totalUpserted}";

        $log->update([
            'status' => $status,
            'totals' => $totals,
            'orders_seen' => $totalSeen,
            'orders_upserted' => $totalUpserted,
            'finished_at' => now(),
            'message' => implode(' · ', $parts),
        ]);

        return $log;
    }

    /**
     * @return array{0: CarbonPeriod|array, 1: bool} lista de dias e se é "full scope"
     */
    private function daysToFetch(string $mode, Carbon $now): array
    {
        if ($mode === 'full') {
            $start = $now->copy()->subMonthNoOverflow()->startOfMonth();
            $end = $now->copy()->endOfMonth();
            return [CarbonPeriod::create($start, '1 day', $end), true];
        }

        $lookback = max(1, (int) config('tiny.incremental_lookback_days', 2));
        $days = [];
        for ($i = $lookback - 1; $i >= 0; $i--) {
            $days[] = $now->copy()->subDays($i)->startOfDay();
        }
        return [$days, false];
    }

    /**
     * Converte um pedido bruto da v3 num record. Defensivo quanto a variações
     * de campo (mesma ideia do extract_pedido_record do Python).
     */
    private function extractRecord(array $p): ?array
    {
        $pid = $this->pick($p, ['id', 'numero', 'numeroPedido']);
        if (! $pid) {
            return null;
        }

        $rawDate = $this->pick($p, ['dataPedido', 'data', 'dataCriacao', 'data_pedido']);
        $date = $this->normalizeDate($rawDate);
        if (! $date) {
            return null;
        }

        $rawValor = $this->pick($p, ['valor', 'valorTotal', 'total']) ?? 0;
        if (is_string($rawValor)) {
            $rawValor = (float) str_replace(',', '.', $rawValor);
        }
        $value = round((float) $rawValor, 2);

        $situacao = $p['situacao'] ?? null;
        if (is_array($situacao)) {
            $situacao = $this->pick($situacao, ['nome', 'descricao', 'id']);
        }
        $statusCode = trim((string) ($situacao ?? ''));

        $canalRaw = '';
        $eco = $p['ecommerce'] ?? null;
        if (is_array($eco)) {
            $canalRaw = trim((string) ($this->pick($eco, ['nome', 'nomeEcommerce', 'descricao']) ?? ''));
        } elseif (is_string($eco)) {
            $canalRaw = trim($eco);
        }
        if ($canalRaw === '') {
            $canalRaw = trim((string) ($this->pick($p, ['nome_ecommerce', 'marketplace']) ?? ''));
        }

        return [
            'tiny_order_id' => (string) $pid,
            'order_date'    => $date,
            'value'         => $value,
            'status_code'   => $statusCode,
            'channel_raw'   => $canalRaw !== '' ? $canalRaw : null,
        ];
    }

    private function pick(array $arr, array $keys)
    {
        foreach ($keys as $k) {
            if (isset($arr[$k]) && $arr[$k] !== '' && $arr[$k] !== null) {
                return $arr[$k];
            }
        }
        return null;
    }

    private function normalizeDate($raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $s = trim((string) $raw);
        // ISO YYYY-MM-DD (possivelmente com hora)
        if (strlen($s) >= 10 && $s[4] === '-' && $s[7] === '-') {
            return substr($s, 0, 10);
        }
        // BR DD/MM/YYYY
        if (strlen($s) >= 10 && $s[2] === '/' && $s[5] === '/') {
            [$d, $m, $y] = explode('/', substr($s, 0, 10));
            if (is_numeric($d) && is_numeric($m) && is_numeric($y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }
        return null;
    }
}
