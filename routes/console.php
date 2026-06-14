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
