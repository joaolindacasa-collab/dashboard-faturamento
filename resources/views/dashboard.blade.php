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

    $d = $data;
@endphp

<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Faturamento · Live</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { background:#0a0b14; }
        .panel { background:#0f111e; border:1px solid #1e2235; }
        .lbl { letter-spacing:.08em; }
    </style>
</head>
<body class="text-gray-200 antialiased"
      x-data="liveReload({{ (int) (request()->cookie('reload_secs', 120)) }})" x-init="init()">

    <div class="max-w-[1400px] mx-auto px-4 py-4 space-y-4">

        {{-- ============ HEADER ============ --}}
        <header class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                    Faturamento <span class="text-rose-500">·</span> <span class="text-rose-400">Live</span>
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Bella · Linda · GV — {{ $d['month_label'] }} · até dia {{ $d['days_elapsed'] }} vs. mesmo período de {{ $d['prev_short'] }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                <span class="flex items-center gap-1.5 text-gray-400">
                    <span class="h-2 w-2 rounded-full bg-amber-400"></span> Gerado {{ $d['generated_at'] }}
                </span>
                <span class="text-gray-400">
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

                <div class="flex items-center gap-1">
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
        <section class="panel rounded-xl p-4">
            <div class="text-[11px] lbl uppercase text-gray-500 mb-1">Projeção do mês</div>
            <div class="text-[10px] text-gray-600 mb-4">Com base no ritmo diário atual · Δ vs. {{ $d['prev_short'] }} (mês inteiro)</div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                {{-- tabela de projeção --}}
                <div class="lg:col-span-2">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead>
                            <tr class="text-[11px] uppercase text-gray-500 border-b border-[#1e2235]">
                                <th class="text-left font-medium py-1.5">Empresa</th>
                                <th class="text-right font-medium pl-3">Atual</th>
                                <th class="text-right font-medium pl-3">Projeção</th>
                                <th class="text-right font-medium pl-3">{{ $d['prev_short'] }}</th>
                                <th class="text-right font-medium pl-3">Δ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($d['projecao_mes']['rows'] as $r)
                                <tr class="border-b border-[#161a2c]">
                                    <td class="py-2 flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }}
                                    </td>
                                    <td class="text-right text-gray-400 pl-3">{{ $money($r['atual']) }}</td>
                                    <td class="text-right text-gray-200 pl-3">{{ $money($r['projecao']) }}</td>
                                    <td class="text-right text-gray-500 pl-3">{{ $money($r['mes_anterior']) }}</td>
                                    <td class="text-right pl-3">{!! $delta($r['delta']) !!}</td>
                                </tr>
                            @endforeach
                            <tr class="font-semibold">
                                <td class="py-2 text-gray-400 uppercase text-xs">Total</td>
                                <td class="text-right text-gray-300 pl-3">{{ $money($d['projecao_mes']['total']['atual']) }}</td>
                                <td class="text-right text-white pl-3">{{ $money($d['projecao_mes']['total']['projecao']) }}</td>
                                <td class="text-right text-gray-400 pl-3">{{ $money($d['projecao_mes']['total']['mes_anterior']) }}</td>
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
                    <div class="flex items-center justify-between mb-2 gap-3 flex-wrap">
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
                                            $tip = 'Dia ' . $day['dia'] . ' · ' . $money($day['total']);
                                            foreach ($cos as $co) {
                                                $vv = $day['values'][$co['slug']] ?? 0;
                                                if ($vv > 0) {
                                                    $pp = $day['total'] > 0 ? round($vv / $day['total'] * 100) : 0;
                                                    $tip .= ' · ' . $co['name'] . ' ' . $money($vv) . ' (' . $pp . '%)';
                                                }
                                            }
                                        @endphp
                                        <div class="flex-1 flex flex-col justify-end h-full hover:opacity-90 transition-opacity" title="{{ $tip }}">
                                            <div class="flex flex-col-reverse rounded-t-sm overflow-hidden" style="height: {{ $barPct }}%">
                                                @foreach ($cos as $co)
                                                    @php
                                                        $v = $day['values'][$co['slug']] ?? 0;
                                                        $segPct = $day['total'] > 0 ? $v / $day['total'] * 100 : 0;
                                                        $segPx = $segPct / 100 * $barPct / 100 * $chartH;  // altura aprox. do bloco em px
                                                    @endphp
                                                    @if ($v > 0)
                                                        <div class="flex items-center justify-center overflow-hidden" style="height: {{ $segPct }}%; background: {{ $co['color'] }}">
                                                            @if ($segPx >= 13)
                                                                <span class="text-[9px] font-semibold leading-none text-white" style="text-shadow:0 1px 2px rgba(0,0,0,.55)">{{ number_format($segPct, 0) }}%</span>
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
                                    <div class="flex-1 text-center text-[8px] text-gray-500 tabular-nums">{{ $day['dia'] }}</div>
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
                <div class="text-[11px] lbl uppercase text-gray-500 mb-3">Por empresa ({{ $d['month_short'] }})</div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] uppercase text-gray-500 border-b border-[#1e2235]">
                            <th class="text-left font-medium py-1.5">Empresa</th>
                            <th class="text-right font-medium">Fatur.</th>
                            <th class="text-right font-medium">Ped.</th>
                            <th class="text-right font-medium">Ticket</th>
                            <th class="text-right font-medium">Δ vs. ant.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($d['por_empresa'] as $r)
                            <tr class="border-b border-[#161a2c]">
                                <td class="py-2 flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }}
                                </td>
                                <td class="text-right text-gray-200">{{ $money($r['fat']) }}</td>
                                <td class="text-right text-gray-400">{{ $int($r['ped']) }}</td>
                                <td class="text-right text-gray-400">{{ $money($r['ticket']) }}</td>
                                <td class="text-right">{!! $delta($r['delta']) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- POR CANAL --}}
            <div class="panel rounded-xl p-4">
                <div class="text-[11px] lbl uppercase text-gray-500 mb-3">Por canal ({{ $d['month_short'] }})</div>
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
        </section>

        {{-- ============ MATRIZ EMPRESA × CANAL ============ --}}
        <section class="panel rounded-xl p-4">
            <div class="flex flex-wrap items-center gap-x-4 mb-3">
                <span class="text-[11px] lbl uppercase text-gray-500">Empresa × Canal — Faturamento em {{ $d['month_short'] }}</span>
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
        </section>

        <p class="text-center text-[11px] text-gray-700 pt-2">Dashboard de Faturamento · Tiny ERP v3</p>
    </div>

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
    </script>
</body>
</html>
