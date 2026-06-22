<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Sincronização incremental: a cada 5 min, das 8h às 22h, seg-sáb.
| Requer o scheduler do Laravel ativo (Task Scheduler do Windows rodando
| `php artisan schedule:run` a cada minuto — ver INSTALL/README).
*/
Schedule::command('tiny:sync --mode=incremental')
    ->everyFiveMinutes()
    ->between('8:00', '22:00')   // todos os dias (inclui domingo); marketplaces vendem 7 dias
    ->withoutOverlapping()
    ->onOneServer() // evita rodar em todas as réplicas no Laravel Cloud
    ->timezone(config('tiny.timezone', 'America/Sao_Paulo'));

/*
| Reconciliação diária do MÊS CORRENTE (1x/dia, de madrugada).
| O incremental só re-busca os últimos 2 dias (limitação da v3: filtro
| dataAlteracao dá HTTP 400), então mudança de status/cancelamento em pedido
| mais antigo que isso NÃO é capturada. O `--month` re-varre o mês inteiro com
| stale-delete (full scope), corrigindo esses casos.
|
| console.php é reavaliado a cada schedule:run, então `$mesCorrente` é sempre o
| mês atual no momento da execução. Roda às 3h30 (fora da janela do incremental
| e do horário de pico da API). Limite de 30 min do Command no Cloud: a varredura
| de UM mês é metade do `full`; se um dia estourar, fatiar por --from/--to.
*/
$mesCorrente = now(config('tiny.timezone', 'America/Sao_Paulo'))->format('Y-m');
Schedule::command("tiny:sync --month={$mesCorrente}")
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->timezone(config('tiny.timezone', 'America/Sao_Paulo'));
