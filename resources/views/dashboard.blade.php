@php
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 0, ',', '.');
    $int = fn ($v) => number_format((float) $v, 0, ',', '.');

    // Renderiza um delta (▲ verde / ▼ vermelho / → neutro / "novo").
    $delta = function ($pct) {
        if (is_null($pct)) {
            return '<span class="text-sky-400">novo</span>';
        }
        $abs = number_format(abs($pct), 1, ',', '.') . '%';
        if ($pct > 0) {
            return '<span class="text-emerald-400">▲ ' . $abs . '</span>';
        }
        if ($pct < 0) {
            return '<span class="text-rose-400">▼ ' . $abs . '</span>';
        }
        return '<span class="text-gray-500">→ ' . $abs . '</span>';
    };

    // Mini-gráfico (sparkline) SVG a partir de uma série numérica. Usado nos tooltips.
    $spark = function (array $series, string $color = '#7c5cff', int $w = 220, int $h = 36) {
        $series = array_values(array_map('floatval', $series));
        $n = count($series);
        if ($n < 2) {
            return '';
        }
        $min = min($series);
        $max = max($series);
        $range = ($max - $min) ?: 1;
        $dx = $w / ($n - 1);
        $pts = [];
        foreach ($series as $i => $v) {
            $x = $i * $dx;
            $y = $h - (($v - $min) / $range) * ($h - 4) - 2;
            $pts[] = round($x, 1) . ',' . round($y, 1);
        }
        return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" style="width:100%;height:' . $h . 'px;display:block">'
            . '<polyline fill="none" stroke="' . $color . '" stroke-width="2" points="' . implode(' ', $pts) . '"/></svg>';
    };

    $d = $data;
@endphp

<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Faturamento · Live</title>

    {{-- PWA / iOS: instalável na tela de início, tela cheia, respeita o notch --}}
    <meta name="theme-color" content="#0a0b14">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Faturamento">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background:#0a0b14; }
        .panel { background:#0f111e; border:1px solid #1e2235; }
        .lbl { letter-spacing:.08em; }
        /* PWA no iPhone: soma o inset da safe-area (notch/barra de status/home)
           ao padding base, sem perder o respiro em telas normais. */
        .safe {
            padding-top: max(1rem, env(safe-area-inset-top));
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            padding-bottom: max(1rem, env(safe-area-inset-bottom));
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-gray-200 antialiased"
      x-data="liveReload({{ (int) (request()->cookie('reload_secs', 120)) }})" x-init="init()">

    <div class="max-w-[1400px] mx-auto safe space-y-4">

        {{-- ============ HEADER ============ --}}
        <header class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    Faturamento <span class="text-rose-500">·</span> <span class="text-rose-400">Live</span>
                </h1>
                @php
                    // Empresas ativas no mês visto (some quem foi removida — ex.: GV a partir de ago/26).
                    $coNames = collect($d['companies'])->map(fn ($c) => explode(' ', $c['name'])[0])->implode(' · ');
                @endphp
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $coNames }} — {{ $d['month_label'] }} ·
                    @if ($d['is_current'])
                        até dia {{ $d['days_elapsed'] }} vs. mesmo período de {{ $d['prev_short'] }}
                    @else
                        mês fechado vs. {{ $d['prev_short'] }}
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                @php
                    // Indicador de frescor: verde (<=15min), amarelo (<=90min), vermelho (mais/sem sync).
                    $age = $sync['age_min'] ?? null;
                    $dot = $age === null ? 'bg-rose-500' : ($age <= 15 ? 'bg-emerald-400' : ($age <= 90 ? 'bg-amber-400' : 'bg-rose-500'));
                    $syncTxt = $age === null ? 'sem sync' : ($age < 1 ? 'sincronizado agora' : 'sincronizado há ' . $age . ' min');
                @endphp
                <span class="flex items-center gap-1.5 text-gray-400" title="Última sync OK: {{ $sync['at'] ?? '—' }}">
                    <span class="h-2 w-2 rounded-full {{ $dot }}"></span> {{ $syncTxt }}
                </span>
                <span class="text-gray-400 hidden sm:inline">
                    Próximo reload em <span class="text-gray-200 font-medium" x-text="countdownLabel()"></span>
                </span>

                <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-1">
                    <label class="text-gray-500">Mês</label>
                    <select name="month" onchange="this.form.submit()"
                            class="bg-[#161a2c] border border-[#272c45] text-gray-200 text-xs rounded-md py-1 pl-2 pr-7 focus:ring-indigo-500 focus:border-indigo-500">
                        @foreach ($monthOptions as $mk => $label)
                            <option value="{{ $mk }}" @selected($mk === $selected)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="items-center gap-1 hidden sm:flex">
                    <label class="text-gray-500">Reload</label>
                    <select x-model="secs" @change="setSecs()"
                            class="bg-[#161a2c] border border-[#272c45] text-gray-200 text-xs rounded-md py-1 pl-2 pr-7 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="0">off</option>
                        <option value="60">1 min</option>
                        <option value="120">2 min</option>
                        <option value="300">5 min</option>
                    </select>
                </div>

                <button onclick="window.location.reload()"
                        class="bg-[#161a2c] border border-[#272c45] hover:bg-[#1e2336] text-gray-200 rounded-md px-3 py-1">Recarregar</button>
                <a href="{{ route('status') }}"
                   class="bg-[#161a2c] border border-[#272c45] hover:bg-[#1e2336] text-gray-200 rounded-md px-3 py-1">Status</a>

                <div class="flex items-center gap-2 pl-2 border-l border-[#272c45]">
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.users.index') }}" class="text-gray-400 hover:text-gray-200">Usuários</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="text-gray-400 hover:text-gray-200">Sair</button>
                    </form>
                </div>
            </div>
        </header>

        {{-- ============ FATURAMENTO DO DIA (por empresa) ============ --}}
        @php
            $h = $d['hoje'];
            // Dia CORRENTE = parcial (só foi até agora). Comparar com o dia inteiro de
            // ontem daria uma queda enorme e falsa de manhã, então não mostramos o Δ;
            // exibimos o valor de ontem só como referência ("dia todo"). Em dias fechados
            // (histórico) o comparativo é dia-cheio vs dia-cheio, aí o Δ vale.
            $live = $h['is_today'];
        @endphp
        <section class="panel rounded-xl p-4">
            <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
                <div class="text-[11px] lbl uppercase text-gray-500 flex items-center gap-2">
                    <span>Faturamento de {{ $live ? 'hoje' : 'último dia' }} <span class="text-gray-600">({{ $h['date_label'] }})</span></span>
                    @if ($live)
                        <span class="normal-case tracking-normal text-[10px] font-medium text-amber-400/90 bg-amber-400/10 rounded px-1.5 py-0.5">parcial</span>
                    @endif
                </div>
                <div class="text-xs text-gray-500">
                    @if ($live)
                        Ontem ({{ $h['prev_date_label'] }}), dia todo: <span class="text-gray-400">{{ $money($h['total_prev']) }}</span>
                    @else
                        Dia anterior ({{ $h['prev_date_label'] }}): {{ $money($h['total_prev']) }} {!! $delta($h['total_delta']) !!}
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($h['rows'] as $r)
                    <div class="rounded-lg bg-[#0c0e19] border border-[#1a1e30] p-3">
                        <div class="text-xs text-gray-400 flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }}
                        </div>
                        <div class="text-2xl font-bold text-white mt-1">{{ $money($r['hoje']) }}</div>
                        <div class="text-[11px] text-gray-500 mt-0.5">
                            @if ($live)
                                <span class="text-gray-600">ontem: {{ $money($r['ontem']) }}</span>
                            @else
                                {!! $delta($r['delta']) !!} vs. {{ $h['prev_date_label'] }}
                            @endif
                        </div>
                    </div>
                @endforeach
                {{-- total consolidado do dia --}}
                <div class="rounded-lg bg-[#11131f] border border-[#242a41] p-3">
                    <div class="text-xs text-gray-400 uppercase tracking-wide">Total do dia</div>
                    <div class="text-2xl font-bold text-white mt-1">{{ $money($h['total']) }}</div>
                    <div class="text-[11px] text-gray-500 mt-0.5">
                        @if ($live)
                            <span class="text-gray-600">ontem: {{ $money($h['total_prev']) }}</span>
                        @else
                            {!! $delta($h['total_delta']) !!} vs. {{ $h['prev_date_label'] }}
                        @endif
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ KPIs ============ --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @php
                $k = $d['kpis'];
            @endphp
            <div class="panel rounded-xl p-4">
                <div class="text-[11px] lbl uppercase text-gray-500">Faturamento do mês</div>
                <div class="text-3xl font-bold text-white mt-1">{{ $money($k['faturamento']['value']) }}</div>
                <div class="text-xs text-gray-500 mt-1">Mês anterior: {{ $money($k['faturamento']['prev']) }} {!! $delta($k['faturamento']['delta']) !!}</div>
            </div>
            <div class="panel rounded-xl p-4">
                <div class="text-[11px] lbl uppercase text-gray-500">Pedidos do mês</div>
                <div class="text-3xl font-bold text-white mt-1">{{ $int($k['pedidos']['value']) }}</div>
                <div class="text-xs text-gray-500 mt-1">Mês anterior: {{ $int($k['pedidos']['prev']) }} {!! $delta($k['pedidos']['delta']) !!}</div>
            </div>
            <div class="panel rounded-xl p-4">
                <div class="text-[11px] lbl uppercase text-gray-500">Ticket médio</div>
                <div class="text-3xl font-bold text-white mt-1">{{ $money($k['ticket']['value']) }}</div>
                <div class="text-xs text-gray-500 mt-1">Mês anterior: {{ $money($k['ticket']['prev']) }} {!! $delta($k['ticket']['delta']) !!}</div>
            </div>
        </section>

        {{-- ============ PROJEÇÃO DO MÊS (largura total) ============ --}}
        <section class="panel rounded-xl p-4" x-data="{ open: window.innerWidth >= 640 }">
            <button type="button" class="w-full flex items-start justify-between gap-3 text-left" @click="if (window.innerWidth < 640) open = !open">
                <div>
                    <div class="text-[11px] lbl uppercase text-gray-500">Projeção do mês</div>
                    <div class="text-[10px] text-gray-600 mt-0.5">Com base no ritmo diário atual · Δ vs. {{ $d['prev_short'] }} (mês inteiro)</div>
                </div>
                <svg class="w-4 h-4 text-gray-500 shrink-0 sm:hidden transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" x-cloak class="mt-4 grid grid-cols-1 lg:grid-cols-5 gap-6">
                {{-- tabela de projeção --}}
                <div class="lg:col-span-2">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead>
                            <tr class="text-[11px] uppercase text-gray-500 border-b border-[#1e2235]">
                                <th class="text-left font-medium py-1.5">Empresa</th>
                                <th class="text-right font-medium pl-3 hidden sm:table-cell">Atual</th>
                                <th class="text-right font-medium pl-3">Projeção</th>
                                <th class="text-right font-medium pl-3 hidden sm:table-cell">{{ $d['prev_short'] }}</th>
                                <th class="text-right font-medium pl-3">Δ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($d['projecao_mes']['rows'] as $r)
                                <tr class="border-b border-[#161a2c]">
                                    <td class="py-2 flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }}
                                    </td>
                                    <td class="text-right text-gray-400 pl-3 hidden sm:table-cell">{{ $money($r['atual']) }}</td>
                                    <td class="text-right text-gray-200 pl-3">{{ $money($r['projecao']) }}</td>
                                    <td class="text-right text-gray-500 pl-3 hidden sm:table-cell">{{ $money($r['mes_anterior']) }}</td>
                                    <td class="text-right pl-3">{!! $delta($r['delta']) !!}</td>
                                </tr>
                            @endforeach
                            <tr class="font-semibold">
                                <td class="py-2 text-gray-400 uppercase text-xs">Total</td>
                                <td class="text-right text-gray-300 pl-3 hidden sm:table-cell">{{ $money($d['projecao_mes']['total']['atual']) }}</td>
                                <td class="text-right text-white pl-3">{{ $money($d['projecao_mes']['total']['projecao']) }}</td>
                                <td class="text-right text-gray-400 pl-3 hidden sm:table-cell">{{ $money($d['projecao_mes']['total']['mes_anterior']) }}</td>
                                <td class="text-right pl-3">{!! $delta($d['projecao_mes']['total']['delta']) !!}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- gráfico de barras empilhadas: faturamento por dia, empilhado por empresa (maior na base) --}}
                <div class="lg:col-span-3">
                    @php
                        $fd = $d['faturamento_diario'];
                        $axisMax = $fd['axis_max'] > 0 ? $fd['axis_max'] : 1;
                        $cos = $fd['companies_ordered'];   // maior faturamento primeiro
                        $chartH = 256;                     // px, casa com h-64
                    @endphp
                    <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
                        <span class="text-[11px] uppercase text-gray-500">Faturamento por dia</span>
                        <div class="flex flex-wrap gap-3 text-[11px] text-gray-400">
                            @foreach ($cos as $co)
                                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" style="background: {{ $co['color'] }}"></span>{{ $co['name'] }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex gap-2">
                        {{-- eixo Y (escala de 50 em 50 mil) --}}
                        <div class="relative w-9 shrink-0 h-64 text-[9px] text-gray-500">
                            @for ($g = 0; $g <= $axisMax; $g += $fd['step'])
                                <div class="absolute right-0 -translate-y-1/2 pr-1 whitespace-nowrap" style="bottom: {{ $g / $axisMax * 100 }}%">{{ $g === 0 ? '0' : $int($g / 1000) . 'k' }}</div>
                            @endfor
                        </div>

                        <div class="flex-1">
                            <div class="relative h-64">
                                {{-- linhas de grade --}}
                                @for ($g = 0; $g <= $axisMax; $g += $fd['step'])
                                    <div class="absolute left-0 right-0 border-t border-[#1a1e30]" style="bottom: {{ $g / $axisMax * 100 }}%"></div>
                                @endfor
                                {{-- barras --}}
                                <div class="absolute inset-0 flex items-end gap-px">
                                    @foreach ($fd['days'] as $day)
                                        @php
                                            $barPct = $day['total'] / $axisMax * 100;
                                        @endphp
                                        <div class="flex-1 flex flex-col justify-end h-full hover:opacity-90 transition-opacity rounded-sm {{ $day['is_weekend'] ? 'bg-white/[0.04]' : '' }}" data-tip>
                                            <div class="tipc hidden">
                                                <div class="font-semibold text-white mb-1">Dia {{ substr($day['date'], 8, 2) }}/{{ substr($day['date'], 5, 2) }}
                                                    @if ($day['is_today'])<span class="text-amber-300 font-normal">· hoje (parcial)</span>@elseif ($day['is_weekend'])<span class="text-gray-500 font-normal">· fim de semana</span>@endif
                                                </div>
                                                <div class="flex justify-between gap-6 mb-1"><span class="text-gray-400">Total</span><span class="text-gray-100 font-medium">{{ $money($day['total']) }}</span></div>
                                                <div class="space-y-0.5">
                                                    @foreach ($cos as $co)
                                                        @php $vv = $day['values'][$co['slug']] ?? 0; $pp = $day['total'] > 0 ? round($vv / $day['total'] * 100) : 0; @endphp
                                                        @if ($vv > 0)
                                                            <div class="flex items-center justify-between gap-6">
                                                                <span class="flex items-center gap-1.5 text-gray-400"><span class="h-2 w-2 rounded-sm" style="background: {{ $co['color'] }}"></span>{{ $co['name'] }}</span>
                                                                <span class="text-gray-100">{{ $money($vv) }} <span class="text-gray-500">({{ $pp }}%)</span></span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="flex flex-col-reverse rounded-t-sm overflow-hidden {{ $day['is_today'] ? 'ring-1 ring-amber-300/80' : '' }}" style="height: {{ $barPct }}%">
                                                @foreach ($cos as $co)
                                                    @php
                                                        $v = $day['values'][$co['slug']] ?? 0;
                                                        $segPct = $day['total'] > 0 ? $v / $day['total'] * 100 : 0;
                                                        $segPx = $segPct / 100 * $barPct / 100 * $chartH;  // altura aprox. do bloco em px
                                                    @endphp
                                                    @if ($v > 0)
                                                        <div class="flex items-center justify-center overflow-hidden" style="height: {{ $segPct }}%; background: {{ $co['color'] }}">
                                                            @if ($segPx >= 13)
                                                                <span class="hidden sm:inline text-[9px] font-semibold leading-none text-white" style="text-shadow:0 1px 2px rgba(0,0,0,.55)">{{ number_format($segPct, 0) }}%</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            {{-- eixo X: todo dia rotulado --}}
                            <div class="flex gap-px mt-1">
                                @foreach ($fd['days'] as $day)
                                    <div class="flex-1 text-center text-[8px] tabular-nums {{ $day['is_today'] ? 'text-amber-300 font-bold' : ($day['is_weekend'] ? 'text-gray-600' : 'text-gray-500') }}">{{ $day['dia'] }}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ POR EMPRESA / POR CANAL ============ --}}
        <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- POR EMPRESA --}}
            <div class="panel rounded-xl p-4">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[11px] lbl uppercase text-gray-500">Por empresa ({{ $d['month_short'] }})</span>
                    <span class="text-[9px] text-gray-600 sm:hidden">toque p/ detalhes</span>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase text-gray-500 border-b border-[#1e2235]">
                            <th class="text-left font-medium py-1.5">Empresa</th>
                            <th class="text-right font-medium">Fatur.</th>
                            <th class="text-right font-medium hidden sm:table-cell">Ped.</th>
                            <th class="text-right font-medium hidden sm:table-cell">Ticket</th>
                            <th class="text-right font-medium">Δ vs. ant.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($d['por_empresa'] as $r)
                            @php
                                $serieCo = array_map(fn ($day) => $day['values'][$r['slug']] ?? 0, $d['faturamento_diario']['days']);
                                $chOfCo = [];
                                foreach ($d['matrix'] as $mrow) {
                                    $cv = $mrow['cells'][$r['slug']]['value'] ?? 0;
                                    if ($cv > 0) { $chOfCo[$mrow['canal']] = $cv; }
                                }
                                arsort($chOfCo);
                            @endphp
                            <tr class="border-b border-[#161a2c] hover:bg-white/[0.025]" data-tip>
                                <td class="py-2 flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }}
                                    <div class="tipc hidden">
                                        <div class="font-semibold text-white mb-1.5 flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }} <span class="text-gray-500 font-normal">· {{ $d['month_short'] }}</span></div>
                                        {!! $spark($serieCo, $r['color']) !!}
                                        <div class="mt-2 space-y-0.5">
                                            <div class="flex justify-between gap-6"><span class="text-gray-400">Faturamento</span><span class="text-gray-100">{{ $money($r['fat']) }}</span></div>
                                            <div class="flex justify-between gap-6"><span class="text-gray-400">Pedidos</span><span class="text-gray-100">{{ $int($r['ped']) }}</span></div>
                                            <div class="flex justify-between gap-6"><span class="text-gray-400">Ticket médio</span><span class="text-gray-100">{{ $money($r['ticket']) }}</span></div>
                                        </div>
                                        @if (count($chOfCo))
                                            <div class="mt-2 mb-0.5 text-[10px] uppercase tracking-wide text-gray-500">Canais</div>
                                            <div class="space-y-0.5">
                                                @foreach (array_slice($chOfCo, 0, 6, true) as $cn => $cv)
                                                    <div class="flex justify-between gap-6"><span class="text-gray-400">{{ $cn }}</span><span class="text-gray-100">{{ $money($cv) }}</span></div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right text-gray-200 whitespace-nowrap">{{ $money($r['fat']) }}</td>
                                <td class="text-right text-gray-400 hidden sm:table-cell">{{ $int($r['ped']) }}</td>
                                <td class="text-right text-gray-400 hidden sm:table-cell">{{ $money($r['ticket']) }}</td>
                                <td class="text-right">{!! $delta($r['delta']) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- POR CANAL --}}
            <div class="panel rounded-xl p-4" x-data="{ open: window.innerWidth >= 640 }">
                <button type="button" class="w-full flex items-center justify-between text-left" @click="if (window.innerWidth < 640) open = !open">
                    <span class="text-[11px] lbl uppercase text-gray-500">Por canal ({{ $d['month_short'] }})</span>
                    <svg class="w-4 h-4 text-gray-500 shrink-0 sm:hidden transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                </button>
                <div x-show="open" x-cloak class="mt-3">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase text-gray-500 border-b border-[#1e2235]">
                            <th class="text-left font-medium py-1.5">Canal</th>
                            <th class="text-right font-medium">Fatur.</th>
                            <th class="text-right font-medium">% mês</th>
                            <th class="text-right font-medium">Δ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($d['por_canal'] as $r)
                            <tr class="border-b border-[#161a2c]">
                                <td class="py-2 text-gray-300">{{ $r['canal'] }}</td>
                                <td class="text-right text-gray-200">{{ $money($r['fat']) }}</td>
                                <td class="text-right text-gray-400">{{ number_format($r['pct_mes'], 1, ',', '.') }}%</td>
                                <td class="text-right">{!! $delta($r['delta']) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-3 text-gray-500">Sem dados neste mês.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </section>

        {{-- ============ MATRIZ EMPRESA × CANAL ============ --}}
        <section class="panel rounded-xl p-4" x-data="{ open: window.innerWidth >= 640 }">
            <button type="button" class="w-full flex items-center justify-between text-left gap-3" @click="if (window.innerWidth < 640) open = !open">
                <span class="text-[11px] lbl uppercase text-gray-500">Empresa × Canal — Faturamento em {{ $d['month_short'] }}</span>
                <svg class="w-4 h-4 text-gray-500 shrink-0 sm:hidden transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" x-cloak class="mt-3">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-3">
                <span class="text-xs text-sky-400">↕ % do canal na empresa</span>
                <span class="text-xs text-amber-400">↔ % da empresa no canal</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase text-gray-500 border-b border-[#1e2235]">
                            <th class="text-left font-medium py-2">Canal</th>
                            @foreach ($d['companies'] as $co)
                                <th class="text-right font-medium">{{ $co['name'] }}</th>
                            @endforeach
                            <th class="text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($d['matrix'] as $row)
                            <tr class="border-b border-[#161a2c]">
                                <td class="py-2.5 text-gray-300">{{ $row['canal'] }}</td>
                                @foreach ($d['companies'] as $co)
                                    @php $c = $row['cells'][$co['slug']]; @endphp
                                    <td class="text-right py-2.5">
                                        <div class="text-gray-200">{{ $money($c['value']) }}</div>
                                        <div class="text-xs">
                                            <span class="text-sky-400">↕ {{ number_format($c['pct_na_empresa'], 1, ',', '.') }}%</span>
                                            <span class="ml-1.5 text-amber-400">↔ {{ number_format($c['pct_no_canal'], 1, ',', '.') }}%</span>
                                        </div>
                                    </td>
                                @endforeach
                                <td class="text-right py-2.5">
                                    <div class="text-white font-medium">{{ $money($row['total']) }}</div>
                                    <div class="text-xs text-sky-400">↕ {{ number_format($row['pct_total'], 1, ',', '.') }}% do total</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-gray-500">Sem dados neste mês.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>
        </section>

        <p class="text-center text-[11px] text-gray-700 pt-2">Dashboard de Faturamento · Tiny ERP v3</p>
    </div>

    {{-- Tooltip flutuante: o conteúdo vem do .tipc do elemento com data-tip --}}
    <div id="tip" class="fixed z-50 pointer-events-none opacity-0 transition-opacity duration-100 bg-[#10132a] border border-[#272c45] rounded-lg shadow-2xl p-3 text-xs text-gray-200"
         style="min-width:200px;max-width:320px;left:-9999px;top:-9999px"></div>

    <script>
        function liveReload(initialSecs) {
            return {
                secs: initialSecs,
                remaining: initialSecs,
                timer: null,
                init() {
                    const saved = localStorage.getItem('reload_secs');
                    if (saved !== null) this.secs = parseInt(saved);
                    this.remaining = this.secs;
                    this.tick();
                },
                tick() {
                    if (this.timer) clearInterval(this.timer);
                    if (this.secs <= 0) { this.remaining = 0; return; }
                    this.timer = setInterval(() => {
                        this.remaining--;
                        if (this.remaining <= 0) window.location.reload();
                    }, 1000);
                },
                setSecs() {
                    this.secs = parseInt(this.secs);
                    localStorage.setItem('reload_secs', this.secs);
                    document.cookie = 'reload_secs=' + this.secs + ';path=/;max-age=31536000';
                    this.remaining = this.secs;
                    this.tick();
                },
                countdownLabel() {
                    if (this.secs <= 0) return 'off';
                    const m = Math.floor(this.remaining / 60);
                    const s = (this.remaining % 60).toString().padStart(2, '0');
                    return m + ':' + s;
                },
            };
        }

        // ---- Tooltips ricos: card flutuante com o conteúdo do .tipc do [data-tip] ----
        // Desktop: segue o cursor (hover). Touch (iOS): toque abre/fecha o card,
        // ancorado ao elemento; tocar fora fecha. Sem hover, nada ficava acessível.
        (function () {
            const tip = document.getElementById('tip');
            if (!tip) return;
            let active = null;
            const show = (el) => {
                const c = el.querySelector('.tipc');
                if (!c) return;
                tip.innerHTML = c.innerHTML;
                tip.classList.remove('opacity-0');
                active = el;
            };
            const hide = () => { tip.classList.add('opacity-0'); tip.style.left = '-9999px'; active = null; };
            const atCursor = (e) => {
                const pad = 14, r = tip.getBoundingClientRect();
                let x = e.clientX + pad, y = e.clientY + pad;
                if (x + r.width > window.innerWidth) x = e.clientX - pad - r.width;
                if (y + r.height > window.innerHeight) y = e.clientY - pad - r.height;
                tip.style.left = Math.max(4, x) + 'px';
                tip.style.top = Math.max(4, y) + 'px';
            };
            const atElement = (el) => {
                const rc = el.getBoundingClientRect(), r = tip.getBoundingClientRect();
                let x = rc.left, y = rc.bottom + 8;
                if (x + r.width > window.innerWidth - 8) x = window.innerWidth - 8 - r.width;
                if (x < 8) x = 8;
                if (y + r.height > window.innerHeight - 8) y = rc.top - 8 - r.height; // acima se não couber
                if (y < 8) y = 8;
                tip.style.left = x + 'px';
                tip.style.top = y + 'px';
            };

            const hoverCapable = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
            if (hoverCapable) {
                document.addEventListener('mouseover', (e) => {
                    const el = e.target.closest('[data-tip]');
                    if (el && el !== active) { show(el); atCursor(e); }
                });
                document.addEventListener('mousemove', (e) => { if (active) atCursor(e); });
                document.addEventListener('mouseout', (e) => {
                    const el = e.target.closest('[data-tip]');
                    if (el && !el.contains(e.relatedTarget)) hide();
                });
            } else {
                document.addEventListener('click', (e) => {
                    const el = e.target.closest('[data-tip]');
                    if (el) {
                        if (active === el) { hide(); } else { show(el); atElement(el); }
                    } else if (active) {
                        hide();
                    }
                });
            }
        })();

        // ---- PWA: registra o service worker (habilita instalação na tela de início) ----
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
</body>
</html>
