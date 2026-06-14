<?php

namespace App\Http\Controllers;

use App\Services\Tiny\TinyClient;
use Illuminate\Http\Request;

/**
 * Fluxo OAuth2 do Tiny via navegador (alternativa ao comando tiny:oauth,
 * que é interativo e não roda no Laravel Cloud).
 *
 * connect  -> redireciona pro Tiny pra autorizar
 * callback -> recebe ?code, troca por tokens e salva no banco
 */
class TinyOAuthController extends Controller
{
    public function connect(Request $request, string $company, TinyClient $client)
    {
        try {
            $client->companyConfig($company); // valida que existe e tem credenciais
        } catch (\Throwable $e) {
            return redirect()->route('status')->with('status', $e->getMessage());
        }

        $state = bin2hex(random_bytes(8));
        $request->session()->put('tiny_oauth_state', $state);
        $request->session()->put('tiny_oauth_company', $company);

        return redirect()->away($client->authorizeUrl($company, $state));
    }

    public function callback(Request $request, TinyClient $client)
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $company = $request->session()->pull('tiny_oauth_company');
        $expectedState = $request->session()->pull('tiny_oauth_state');

        if (! $code) {
            return redirect()->route('status')->with('status', 'Autorização cancelada ou sem código.');
        }
        if (! $state || $state !== $expectedState) {
            return redirect()->route('status')->with('status', 'State inválido (proteção CSRF). Tente conectar novamente.');
        }
        if (! $company) {
            return redirect()->route('status')->with('status', 'Sessão expirou. Tente conectar novamente.');
        }

        try {
            $client->exchangeCode($company, $code);
        } catch (\Throwable $e) {
            return redirect()->route('status')->with('status', "Falha ao conectar '{$company}': ".$e->getMessage());
        }

        return redirect()->route('status')->with('status', "Empresa '{$company}' conectada! Rode o primeiro sync (tiny:sync --mode=full) ou aguarde o agendamento.");
    }
}
