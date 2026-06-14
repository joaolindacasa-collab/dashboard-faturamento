<?php

namespace App\Services\Tiny;

use App\Models\TinyToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente da API v3 do Tiny ERP (REST/OAuth2).
 *
 * Equivalente em Laravel ao update_dashboard.py (parte de auth + fetch):
 *  - refresh_token rotation (o Tiny rotaciona a cada uso; salvamos o novo)
 *  - GET /pedidos paginado com retry/backoff em 429 (rate limit) e 5xx
 */
class TinyClient
{
    public function companies(): array
    {
        return config('tiny.companies', []);
    }

    /**
     * redirect_uri usado no OAuth. Usa o valor do .env se definido; senão
     * cai pra rota web de callback (útil no Cloud, onde a URL = APP_URL).
     */
    public function redirectUri(): string
    {
        return config('tiny.oauth.redirect_uri') ?: route('tiny.callback');
    }

    public function companyConfig(string $slug): array
    {
        $companies = $this->companies();
        if (! isset($companies[$slug])) {
            throw new RuntimeException("Empresa '{$slug}' não existe em config/tiny.php");
        }
        $c = $companies[$slug];
        if (empty($c['client_id']) || empty($c['client_secret'])) {
            throw new RuntimeException("Empresa '{$slug}' está sem client_id/client_secret no .env (TINY_".strtoupper($slug)."_CLIENT_ID / _SECRET).");
        }
        return $c;
    }

    /**
     * Monta a URL de autorização OAuth2 (usada pelo comando tiny:oauth).
     */
    public function authorizeUrl(string $slug, string $state): string
    {
        $c = $this->companyConfig($slug);
        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $c['client_id'],
            'redirect_uri'  => $this->redirectUri(),
            'scope'         => config('tiny.oauth.scope', 'openid'),
            'state'         => $state,
        ]);
        return rtrim(config('tiny.oauth.auth_url'), '?').'?'.$params;
    }

    /**
     * Troca o authorization code por tokens e persiste em tiny_tokens.
     */
    public function exchangeCode(string $slug, string $code): TinyToken
    {
        $c = $this->companyConfig($slug);
        $resp = Http::asForm()->acceptJson()->timeout(30)->post(config('tiny.oauth.token_url'), [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri(),
            'client_id'     => $c['client_id'],
            'client_secret' => $c['client_secret'],
        ]);

        if (! $resp->successful()) {
            throw new RuntimeException("Falha ao trocar code ({$resp->status()}): ".$resp->body());
        }
        $data = $resp->json();
        if (empty($data['refresh_token'])) {
            throw new RuntimeException('Resposta sem refresh_token: '.$resp->body());
        }

        return $this->storeTokens($slug, $data, $data['refresh_token']);
    }

    /**
     * Garante um access_token válido pra empresa, renovando via refresh_token
     * quando necessário. Persiste o refresh_token rotacionado.
     */
    public function accessTokenFor(string $slug): string
    {
        $c = $this->companyConfig($slug);
        $token = TinyToken::where('company', $slug)->first();
        if (! $token || ! $token->refresh_token) {
            throw new RuntimeException("Empresa '{$slug}' sem refresh_token. Rode: php artisan tiny:oauth {$slug}");
        }

        // Reusa access_token se ainda tem >60s de validade.
        if ($token->access_token && $token->access_expires_at && $token->access_expires_at->isFuture()
            && $token->access_expires_at->diffInSeconds(now()) > 60) {
            return $token->access_token;
        }

        $resp = Http::asForm()->acceptJson()->timeout(30)->post(config('tiny.oauth.token_url'), [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $token->refresh_token,
            'client_id'     => $c['client_id'],
            'client_secret' => $c['client_secret'],
        ]);

        if ($resp->status() === 400 || $resp->status() === 401) {
            throw new RuntimeException("Empresa '{$slug}': refresh falhou ({$resp->status()}). O refresh_token expirou/foi revogado. Rode: php artisan tiny:oauth {$slug}");
        }
        if (! $resp->successful()) {
            throw new RuntimeException("Empresa '{$slug}': refresh falhou ({$resp->status()}): ".$resp->body());
        }

        $data = $resp->json();
        if (empty($data['access_token'])) {
            throw new RuntimeException("Empresa '{$slug}': resposta sem access_token.");
        }

        // O Tiny pode rotacionar o refresh_token; se vier um novo, salvamos.
        $newRefresh = $data['refresh_token'] ?? $token->refresh_token;
        $this->storeTokens($slug, $data, $newRefresh);

        return $data['access_token'];
    }

    private function storeTokens(string $slug, array $data, string $refresh): TinyToken
    {
        $expiresAt = isset($data['expires_in'])
            ? Carbon::now()->addSeconds((int) $data['expires_in'])
            : null;

        return TinyToken::updateOrCreate(
            ['company' => $slug],
            [
                'refresh_token'     => $refresh,
                'access_token'      => $data['access_token'] ?? null,
                'access_expires_at' => $expiresAt,
                'scope'             => $data['scope'] ?? null,
                'refreshed_at'      => now(),
            ]
        );
    }

    /**
     * Busca todos os pedidos de um dia (dataInicial=dataFinal=dia), paginando.
     * Os dias são pequenos -> paginação rasa, resposta rápida. Mesmo desenho
     * do iter_pedidos do Python. Retorna array de pedidos brutos.
     */
    public function fetchOrdersForDay(string $slug, string $accessToken, string $dayYmd): array
    {
        $base = rtrim(config('tiny.api_base'), '/');
        $pageSize = config('tiny.page_size', 100);
        $offset = 0;
        $maxPages = 1000;
        $all = [];

        for ($page = 0; $page < $maxPages; $page++) {
            $url = $base.'/pedidos?'.http_build_query([
                'dataInicial' => $dayYmd,
                'dataFinal'   => $dayYmd,
                'limit'       => $pageSize,
                'offset'      => $offset,
            ]);

            $payload = $this->getJsonWithRetry($url, $accessToken);

            $itens = null;
            if (is_array($payload)) {
                foreach (['itens', 'data', 'pedidos', 'results'] as $key) {
                    if (isset($payload[$key]) && is_array($payload[$key])) {
                        $itens = $payload[$key];
                        break;
                    }
                }
                // resposta pode ser lista direta
                if ($itens === null && array_is_list($payload)) {
                    $itens = $payload;
                }
            }

            if (empty($itens)) {
                break;
            }

            foreach ($itens as $item) {
                $all[] = $item;
            }

            if (count($itens) < $pageSize) {
                break;
            }
            $offset += $pageSize;
            usleep(300_000); // ~3 req/s, abaixo do limite de 60/min
        }

        return $all;
    }

    /**
     * GET autenticado com retry: 429 -> espera 30/60/90s; 5xx -> backoff 2/4/8s.
     */
    private function getJsonWithRetry(string $url, string $accessToken, int $retries = 5): array
    {
        $lastBody = '';
        for ($attempt = 0; $attempt < $retries; $attempt++) {
            try {
                $resp = Http::withToken($accessToken)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => 'Dashboard-Faturamento-Laravel/1.0'])
                    ->timeout(45)
                    ->get($url);
            } catch (\Throwable $e) {
                sleep(2 ** ($attempt + 1));
                $lastBody = $e->getMessage();
                continue;
            }

            $status = $resp->status();
            if ($status === 429) {
                sleep(30 * ($attempt + 1));
                $lastBody = $resp->body();
                continue;
            }
            if ($status >= 500 && $status < 600) {
                sleep(2 ** ($attempt + 1));
                $lastBody = $resp->body();
                continue;
            }
            if ($status >= 400) {
                throw new RuntimeException("HTTP {$status} em {$url}: ".$resp->body());
            }
            return $resp->json() ?? [];
        }
        throw new RuntimeException("Falha após {$retries} tentativas em {$url}: {$lastBody}");
    }
}
