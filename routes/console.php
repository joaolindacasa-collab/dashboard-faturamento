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
    ->withoutOverlapping(15)     // lock expira em 15 min; sem isso o default é 24h e uma sync
                                 // morta no meio (hibernação/timeout) travaria os proximos runs por 1 dia
    ->onOneServer() // evita rodar em todas as réplicas no Laravel Cloud
    ->timezone(config('tiny.timezone', 'America/Sao_Paulo'));

/*
| Reconciliação diária do MÊS CORRENTE (1x/dia, de madrugada), POR EMPRESA.
| O incremental só re-busca os últimos 2 dias (limitação da v3: filtro
| dataAlteracao dá HTTP 400), então mudança de status/cancelamento em pedido
| mais antigo que isso NÃO é capturada. O `--month` re-varre o mês inteiro com
| stale-delete (full scope), corrigindo esses casos.
|
| Por que POR EMPRESA (e não um único `--month`): a varredura de um mês inteiro
| perto do fim do mês pode se aproximar do limite de 30 min de Command do Cloud
| e ser morta no meio. Rodando uma empresa por vez (cada ~1/3 do trabalho), cada
| run fica bem abaixo do limite. O stale-delete do OrderSyncService já é escopado
| por empresa, então rodar isolado é equivalente ao run combinado.
|
| Escalonado 03:30 / 03:40 / 03:50 (BRT): dentro da janela em que o keep-alive
| (GitHub Actions, ~03:00-03:55 BRT) mantém a app acordada pro scheduler disparar.
| console.php é reavaliado a cada schedule:run, então `$mesCorrente` é sempre o
| mês corrente no momento da execução.
*/
$mesCorrente = now(config('tiny.timezone', 'America/Sao_Paulo'))->format('Y-m');
$reconBaseMin = 3 * 60 + 30; // 03:30 BRT
foreach (array_keys(config('tiny.companies', [])) as $i => $slug) {
    $min = $reconBaseMin + $i * 10; // escalona de 10 em 10 min
    $at = sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    Schedule::command("tiny:sync --month={$mesCorrente} --company={$slug}")
        ->dailyAt($at)
        ->withoutOverlapping(30) // expira em 30 min (nunca fica preso ate o dia seguinte)
        ->onOneServer()
        ->timezone(config('tiny.timezone', 'America/Sao_Paulo'));
}

/*
| Alerta de sync parado: 1x/hora na janela ativa (9h-22h). Fora dela o
| incremental não roda, então a base "envelhecer" é esperado — checar de noite
| geraria falso positivo. O command tem cooldown próprio anti-spam.
*/
Schedule::command('tiny:sync-alert')
    ->hourly()
    ->between('9:00', '22:00')
    ->withoutOverlapping(10)     // check leve; expira rapido pra nunca ficar preso
    ->onOneServer()
    ->timezone(config('tiny.timezone', 'America/Sao_Paulo'));
