<?php

namespace App\Services\Tiny;

use App\Models\Order;
use Carbon\Carbon;

/**
 * Agrega a tabela `orders` no payload do painel "Live".
 * Reproduz o dashboard Python: KPIs com projeção, hoje vs ontem,
 * por empresa, por canal e matriz empresa × canal — tudo com delta
 * vs. mês anterior no mesmo período.
 */
class DashboardAggregator
{
    public function timezone(): string
    {
        return config('tiny.timezone', 'America/Sao_Paulo');
    }

    /** Meses com dados (YYYY-MM) + atual e anterior, desc. Portável (MySQL/Postgres). */
    public function availableMonths(): array
    {
        $now = Carbon::now($this->timezone());
        $months = Order::query()->distinct()->orderBy('order_date')->pluck('order_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m'))->all();

        $months[] = $now->format('Y-m');
        $months[] = $now->copy()->subMonthNoOverflow()->format('Y-m');

        $months = array_values(array_unique($months));
        rsort($months);
        return $months;
    }

    public function monthLabel(string $monthKey): string
    {
        $meses = [1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        [$y, $m] = array_map('intval', explode('-', $monthKey));
        return ($meses[$m] ?? '?').'/'.$y;
    }

    private function shortLabel(string $monthKey): string
    {
        $ab = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        [$y, $m] = array_map('intval', explode('-', $monthKey));
        return ($ab[$m] ?? '?').'/'.substr((string) $y, 2);
    }

    /** Delta percentual cur vs prev. null = sem base (prev=0, cur>0). */
    private function pct(float $cur, float $prev): ?float
    {
        if ($prev > 0) {
            return round(($cur - $prev) / $prev * 100, 1);
        }
        return $cur > 0 ? null : 0.0;
    }

    public function forMonth(string $monthKey): array
    {
        $tz = $this->timezone();
        $now = Carbon::now($tz);
        [$y, $m] = array_map('intval', explode('-', $monthKey));
        $monthStart = Carbon::create($y, $m, 1, 0, 0, 0, $tz);
        $daysInMonth = (int) $monthStart->daysInMonth;
        $isCurrent = ($monthKey === $now->format('Y-m'));
        $daysElapsed = $isCurrent ? (int) $now->day : $daysInMonth;
        $periodDays = $daysElapsed;
        $prevKey = $monthStart->copy()->subMonthNoOverflow()->format('Y-m');

        // Fração do dia de hoje já decorrida (segundos desde a meia-noite / dia).
        // Reusada na projeção (hoje não conta como dia cheio) e no "hoje vs ontem".
        $dayFraction = min(1.0, max(0.0, ($now->timestamp - $now->copy()->startOfDay()->timestamp) / 86400));

        $unknown = config('tiny.unknown_channel', 'Sem canal');
        $companiesCfg = config('tiny.companies', []);
        $slugs = array_keys($companiesCfg);

        // ---- limites de datas (portável: MySQL e Postgres, sem DATE_FORMAT/DAYOFMONTH) ----
        $curStart = $monthStart->toDateString();
        $curEnd = $isCurrent ? $now->toDateString() : $monthStart->copy()->endOfMonth()->toDateString();

        $prevStart = $monthStart->copy()->subMonthNoOverflow()->startOfMonth();
        $prevMonthEnd = $prevStart->copy()->endOfMonth();
        $prevPerEnd = $prevStart->copy()->addDays($periodDays - 1);
        if ($prevPerEnd->greaterThan($prevMonthEnd)) {
            $prevPerEnd = $prevMonthEnd;
        }

        // ---- agregados crus ----
        $cur = $this->grouped($curStart, $curEnd, $unknown);                              // mês selecionado (até hoje, se atual)
        $prevPer = $this->grouped($prevStart->toDateString(), $prevPerEnd->toDateString(), $unknown); // mês anterior, mesmo período
        $prevFullTotal = (float) Order::whereBetween('order_date', [$prevStart->toDateString(), $prevMonthEnd->toDateString()])->sum('value');

        // índices
        $coTotal = array_fill_keys($slugs, 0.0);
        $coOrders = array_fill_keys($slugs, 0);
        $coPrevTotal = array_fill_keys($slugs, 0.0);
        $coPrevOrders = array_fill_keys($slugs, 0);
        $chTotal = [];
        $chPrev = [];
        $cell = [];   // [channel][company] = valor atual
        $grand = 0.0; $grandOrders = 0; $grandPrev = 0.0; $grandPrevOrders = 0;

        foreach ($cur as $r) {
            $coTotal[$r->company] = ($coTotal[$r->company] ?? 0) + (float) $r->v;
            $coOrders[$r->company] = ($coOrders[$r->company] ?? 0) + (int) $r->c;
            $chTotal[$r->ch] = ($chTotal[$r->ch] ?? 0) + (float) $r->v;
            $cell[$r->ch][$r->company] = ((float) ($cell[$r->ch][$r->company] ?? 0)) + (float) $r->v;
            $grand += (float) $r->v; $grandOrders += (int) $r->c;
        }
        foreach ($prevPer as $r) {
            $coPrevTotal[$r->company] = ($coPrevTotal[$r->company] ?? 0) + (float) $r->v;
            $coPrevOrders[$r->company] = ($coPrevOrders[$r->company] ?? 0) + (int) $r->c;
            $chPrev[$r->ch] = ($chPrev[$r->ch] ?? 0) + (float) $r->v;
            $grandPrev += (float) $r->v; $grandPrevOrders += (int) $r->c;
        }

        // ---- KPIs ----
        $ticket = $grandOrders > 0 ? $grand / $grandOrders : 0;
        $ticketPrev = $grandPrevOrders > 0 ? $grandPrev / $grandPrevOrders : 0;

        // Projeção: hoje conta PROPORCIONAL ao horário (dias completos + fração
        // de hoje), senão a média diária fica diluída (hoje como dia cheio) e a
        // projeção sai baixa. Pressupõe ritmo de vendas ~uniforme no dia — como
        // os dias completos dominam o denominador, fica estável mesmo de manhã.
        $effectiveDays = $isCurrent ? (($daysElapsed - 1) + $dayFraction) : $daysElapsed;
        $projection = $effectiveDays > 0 ? $grand / $effectiveDays * $daysInMonth : 0;

        $kpis = [
            'faturamento' => ['value' => $grand, 'prev' => $grandPrev, 'delta' => $this->pct($grand, $grandPrev)],
            'pedidos'     => ['value' => $grandOrders, 'prev' => $grandPrevOrders, 'delta' => $this->pct($grandOrders, $grandPrevOrders)],
            'ticket'      => ['value' => $ticket, 'prev' => $ticketPrev, 'delta' => $this->pct($ticket, $ticketPrev)],
            'projecao'    => ['value' => $projection, 'prev' => $prevFullTotal, 'delta' => $this->pct($projection, $prevFullTotal)],
        ];

        // ---- por empresa ----
        $porEmpresa = [];
        foreach ($slugs as $slug) {
            $t = $coTotal[$slug]; $o = $coOrders[$slug];
            $porEmpresa[] = [
                'slug'   => $slug,
                'name'   => $companiesCfg[$slug]['name'],
                'color'  => $companiesCfg[$slug]['color'] ?? '#7c5cff',
                'fat'    => round($t, 2),
                'ped'    => $o,
                'ticket' => $o > 0 ? round($t / $o, 2) : 0,
                'delta'  => $this->pct($t, $coPrevTotal[$slug]),
            ];
        }

        // ---- por canal ----
        $allChannels = array_values(array_unique(array_merge(
            config('tiny.known_channels', []),
            array_keys($chTotal),
            array_keys($chPrev),
        )));
        $porCanal = [];
        foreach ($allChannels as $ch) {
            $fat = $chTotal[$ch] ?? 0;
            $prev = $chPrev[$ch] ?? 0;
            if ($fat <= 0 && $prev <= 0) {
                continue;
            }
            $porCanal[] = [
                'canal'   => $ch,
                'fat'     => round($fat, 2),
                'pct_mes' => $grand > 0 ? round($fat / $grand * 100, 1) : 0,
                'delta'   => $this->pct($fat, $prev),
            ];
        }
        usort($porCanal, fn ($a, $b) => $b['fat'] <=> $a['fat']);

        // ---- matriz empresa × canal ----
        // Mesma seleção do "por canal" (apareceu no atual OU no período anterior),
        // mas mantendo a ordem de $allChannels (conhecidos primeiro, depois extras).
        $matrixChannels = array_values(array_filter(
            $allChannels,
            fn ($ch) => ($chTotal[$ch] ?? 0) > 0 || ($chPrev[$ch] ?? 0) > 0
        ));

        $matrix = [];
        foreach ($matrixChannels as $ch) {
            $row = ['canal' => $ch, 'cells' => [], 'total' => round($chTotal[$ch] ?? 0, 2),
                'pct_total' => $grand > 0 ? round(($chTotal[$ch] ?? 0) / $grand * 100, 1) : 0];
            foreach ($slugs as $slug) {
                $v = (float) ($cell[$ch][$slug] ?? 0);
                $row['cells'][$slug] = [
                    'value'         => round($v, 2),
                    'pct_na_empresa'=> ($coTotal[$slug] ?? 0) > 0 ? round($v / $coTotal[$slug] * 100, 1) : 0, // % do canal dentro da empresa
                    'pct_no_canal'  => ($chTotal[$ch] ?? 0) > 0 ? round($v / $chTotal[$ch] * 100, 1) : 0,      // % da empresa dentro do canal
                ];
            }
            $matrix[] = $row;
        }

        // ---- hoje vs ontem (ontem proporcional ao horário atual) ----
        // order_date guarda só a DATA (sem hora) — o /pedidos do Tiny v3 é por
        // dia e o normalizeDate corta em YYYY-MM-DD. Então não dá pra recortar
        // ontem pela hora real. Comparar dia parcial (hoje) com dia inteiro
        // (ontem) enviesa tudo pra baixo; em vez disso escalamos o total de
        // ontem pela fração do dia já decorrida ($dayFraction, calculado acima).
        $today = $now->copy()->startOfDay();
        $yest = $now->copy()->subDay()->startOfDay();
        $todayBy = Order::selectRaw('company, SUM(value) v')->whereDate('order_date', $today->toDateString())->groupBy('company')->pluck('v', 'company');
        $yestBy = Order::selectRaw('company, SUM(value) v')->whereDate('order_date', $yest->toDateString())->groupBy('company')->pluck('v', 'company');
        $hojeVsOntem = [
            'date_today' => $today->format('d/m'),
            'date_yest'  => $yest->format('d/m'),
            'time_now'   => $now->format('H:i'),
            'day_pct'    => (int) round($dayFraction * 100),
            'rows'       => [],
            'total'      => [],
        ];
        $tHoje = 0.0; $tOntem = 0.0;
        foreach ($slugs as $slug) {
            $h = (float) ($todayBy[$slug] ?? 0);
            $o = (float) ($yestBy[$slug] ?? 0) * $dayFraction; // ontem proporcional ao horário
            $tHoje += $h; $tOntem += $o;
            $hojeVsOntem['rows'][] = [
                'name' => $companiesCfg[$slug]['name'],
                'color'=> $companiesCfg[$slug]['color'] ?? '#7c5cff',
                'hoje' => round($h, 2), 'ontem' => round($o, 2), 'delta' => $this->pct($h, $o),
            ];
        }
        $hojeVsOntem['total'] = ['hoje' => round($tHoje, 2), 'ontem' => round($tOntem, 2), 'delta' => $this->pct($tHoje, $tOntem)];

        return [
            'month'         => $monthKey,
            'month_label'   => $this->monthLabel($monthKey),
            'month_short'   => $this->shortLabel($monthKey),
            'prev_short'    => $this->shortLabel($prevKey),
            'is_current'    => $isCurrent,
            'days_in_month' => $daysInMonth,
            'days_elapsed'  => $daysElapsed,
            'generated_at'  => $now->format('H:i · d/m/Y'),
            'companies'     => array_map(fn ($s) => [
                'slug' => $s, 'name' => $companiesCfg[$s]['name'], 'color' => $companiesCfg[$s]['color'] ?? '#7c5cff',
            ], $slugs),
            'kpis'          => $kpis,
            'por_empresa'   => $porEmpresa,
            'por_canal'     => $porCanal,
            'matrix'        => $matrix,
            'hoje_vs_ontem' => $hojeVsOntem,
        ];
    }

    /**
     * Agrupa pedidos por (company, channel) num intervalo de datas [start, end]
     * (inclusivo). Portável entre MySQL e Postgres: usa whereBetween + COALESCE/
     * NULLIF (SQL padrão), sem funções específicas de data.
     */
    private function grouped(string $startDate, string $endDate, string $unknown)
    {
        return Order::query()
            ->selectRaw('company, COALESCE(NULLIF(channel, ?), ?) as ch, SUM(value) as v, COUNT(*) as c', ['', $unknown])
            ->whereBetween('order_date', [$startDate, $endDate])
            ->groupBy('company', 'ch')
            ->get();
    }
}
