<?php

namespace App\Console\Commands;

use App\Services\Tiny\TinyClient;
use Illuminate\Console\Command;

class TinyOAuthCommand extends Command
{
    protected $signature = 'tiny:oauth {company : bella | linda | gv}';

    protected $description = 'Bootstrap OAuth2 de uma empresa no Tiny v3 (gera o refresh_token inicial)';

    public function handle(TinyClient $client): int
    {
        $slug = $this->argument('company');

        try {
            $cfg = $client->companyConfig($slug);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $state = bin2hex(random_bytes(8));
        $url = $client->authorizeUrl($slug, $state);

        $this->newLine();
        $this->info("=== Bootstrap OAuth: {$cfg['name']} ===");
        $this->newLine();
        $this->line('PASSO 1: Abra esta URL no navegador (de preferência aba anônima):');
        $this->newLine();
        $this->line("  {$url}");
        $this->newLine();
        $this->line("PASSO 2: Faça login no Tiny DESTA empresa e clique em 'Autorizar'.");
        $this->newLine();
        $this->line('PASSO 3: O Tiny redireciona pra uma URL com ?code=ALGUMA_COISA&state='.$state);
        $this->line('         A página pode dar 404 — tudo bem. Copie só o valor do code.');
        $this->newLine();

        $code = trim((string) $this->ask('Cole o CODE aqui'));
        if ($code === '') {
            $this->error('Code vazio.');
            return self::FAILURE;
        }

        try {
            $client->exchangeCode($slug, $code);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("OK. Tokens da '{$slug}' salvos. Agora rode: php artisan tiny:sync --mode=full");
        return self::SUCCESS;
    }
}
