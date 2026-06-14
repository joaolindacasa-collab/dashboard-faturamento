<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">Status do sistema</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="p-3 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-sm">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="panel rounded-xl p-5">
                    <div class="text-sm text-gray-500">Última sync OK</div>
                    <div class="mt-1 text-lg font-bold text-white">
                        {{ $lastOk?->finished_at?->timezone(config('tiny.timezone'))?->format('d/m/Y H:i') ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ $lastOk?->mode ? 'modo: '.$lastOk->mode : 'nenhuma ainda' }}</div>
                </div>
                <div class="panel rounded-xl p-5">
                    <div class="text-sm text-gray-500">Última execução</div>
                    <div class="mt-1 text-lg font-bold {{ $lastAny?->status === 'error' ? 'text-rose-400' : 'text-white' }}">
                        {{ $lastAny ? strtoupper($lastAny->status) : '—' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ $lastAny?->created_at?->diffForHumans() ?? '' }}</div>
                </div>
                <div class="panel rounded-xl p-5">
                    <div class="text-sm text-gray-500">Total de pedidos na base</div>
                    <div class="mt-1 text-lg font-bold text-white">{{ number_format($totalOrders, 0, ',', '.') }}</div>
                </div>
            </div>

            <div class="panel rounded-xl p-5">
                <h3 class="text-[11px] uppercase tracking-wide text-gray-500 mb-3">Empresas / conexão OAuth</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-[#1e2235]">
                            <th class="py-2 font-medium">Empresa</th>
                            <th class="py-2 font-medium">Conectada</th>
                            <th class="py-2 font-medium">Último refresh</th>
                            <th class="py-2 font-medium text-right">Pedidos</th>
                            <th class="py-2 font-medium text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($companies as $c)
                            <tr class="border-b border-[#161a2c] last:border-0">
                                <td class="py-2.5 text-gray-200">{{ $c['name'] }} <span class="text-gray-500">({{ $c['slug'] }})</span></td>
                                <td class="py-2.5">
                                    @if ($c['connected'])
                                        <span class="text-emerald-400">● conectada</span>
                                    @else
                                        <span class="text-rose-400">● não conectada</span>
                                    @endif
                                </td>
                                <td class="py-2.5 text-gray-400">{{ $c['refreshed_at']?->timezone(config('tiny.timezone'))?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="py-2.5 text-right font-medium text-gray-200">{{ number_format($c['orders'], 0, ',', '.') }}</td>
                                <td class="py-2.5 text-right">
                                    @if (auth()->user()->isAdmin())
                                        <a href="{{ route('tiny.connect', $c['slug']) }}"
                                           class="inline-block px-3 py-1 rounded-md text-white text-xs {{ $c['connected'] ? 'bg-[#272c45] hover:bg-[#323853]' : 'bg-indigo-600 hover:bg-indigo-700' }}">
                                            {{ $c['connected'] ? 'Reconectar' : 'Conectar' }}
                                        </a>
                                    @else
                                        <span class="text-gray-500">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($recentErrors->isNotEmpty())
                <div class="panel rounded-xl p-5">
                    <h3 class="text-[11px] uppercase tracking-wide text-rose-400 mb-3">Erros recentes</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach ($recentErrors as $err)
                            <li class="text-gray-400">
                                <span class="text-gray-600">{{ $err->created_at?->format('d/m H:i') }}</span> —
                                {{ \Illuminate\Support\Str::limit($err->message, 200) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
