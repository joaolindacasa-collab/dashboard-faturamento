<?php

namespace App\Console\Commands;

use App\Mail\SyncStoppedAlert;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alerta de sync parado: dispara e-mail se não houver uma sync OK há mais de
 * `tiny.alert.max_age_hours`. Pensado para rodar agendado só na janela ativa
 * (9h-22h) — fora dela o incremental não roda e a base "envelhecer" é normal.
 *
 * Anti-spam: depois de enviar, fica em cooldown por `tiny.alert.cooldown_hours`
 * (chave no cache) para não mandar um e-mail a cada execução durante uma queda.
 */
class TinySyncAlertCommand extends Command
{
    protected $signature = 'tiny:sync-alert {--force : envia ignorando o cooldown}';

    protected $description = 'Envia e-mail se não houver sync OK recente (sync parado/quebrado).';

    private const COOLDOWN_KEY = 'tiny:sync-alert:last-sent';

    public function handle(): int
    {
        if (! config('tiny.alert.enabled', true)) {
            $this->info('Alerta desabilitado (tiny.alert.enabled=false).');
            return self::SUCCESS;
        }

        $maxAgeHours = max(1, (int) config('tiny.alert.max_age_hours', 2));
        $tz = config('tiny.timezone', 'America/Sao_Paulo');
        $now = Carbon::now($tz);

        $lastOk = SyncLog::where('status', 'ok')->latest('finished_at')->first();
        $finishedAt = $lastOk?->finished_at;

        $ageHours = $finishedAt ? $finishedAt->copy()->diffInMinutes($now) / 60 : null;
        $stale = $ageHours === null || $ageHours > $maxAgeHours;

        if (! $stale) {
            $this->info(sprintf('OK: última sync há %.1fh (limite %dh).', $ageHours, $maxAgeHours));
            return self::SUCCESS;
        }

        $idade = $finishedAt
            ? sprintf('%.1f horas atrás (%s)', $ageHours, $finishedAt->setTimezone($tz)->format('d/m H:i'))
            : 'nunca houve uma sync OK registrada';

        // cooldown anti-spam
        $lastSent = Cache::get(self::COOLDOWN_KEY);
        $cooldownHours = max(0, (int) config('tiny.alert.cooldown_hours', 6));
        if (! $this->option('force') && $lastSent && $cooldownHours > 0) {
            $sentAgo = Carbon::parse($lastSent)->diffInMinutes($now) / 60;
            if ($sentAgo < $cooldownHours) {
                $this->warn(sprintf('Sync parado (%s), mas em cooldown (alerta enviado há %.1fh < %dh).', $idade, $sentAgo, $cooldownHours));
                return self::SUCCESS;
            }
        }

        $recipients = $this->recipients();
        if (empty($recipients)) {
            $this->error('Sync parado, mas sem destinatário (defina TINY_ALERT_EMAIL ou crie um usuário admin).');
            Log::warning('[tiny:sync-alert] sync parado e sem destinatário de e-mail.');
            return self::FAILURE;
        }

        $appName = config('app.name', 'Dashboard');
        $subject = "[{$appName}] Sync do Tiny parado";
        $body = "A sincronização de pedidos do Tiny parece parada.\n\n"
            . "Última sync OK: {$idade}.\n"
            . "Limite configurado: {$maxAgeHours} hora(s).\n"
            . 'Verificado em: ' . $now->format('d/m/Y H:i') . " ({$tz}).\n\n"
            . "Verifique o painel de status (/sync-status), as credenciais do Tiny e o scheduler.";

        Mail::to($recipients)->send(new SyncStoppedAlert($subject, $body));

        Cache::put(self::COOLDOWN_KEY, $now->toIso8601String(), now()->addHours(max(1, $cooldownHours)));

        $this->info('Alerta enviado para: ' . implode(', ', $recipients) . " ({$idade}).");
        Log::warning("[tiny:sync-alert] sync parado ({$idade}); alerta enviado para " . implode(', ', $recipients) . '.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        $configured = config('tiny.alert.email');
        if ($configured) {
            return array_values(array_filter(array_map('trim', explode(',', $configured))));
        }

        return User::where('is_admin', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();
    }
}
