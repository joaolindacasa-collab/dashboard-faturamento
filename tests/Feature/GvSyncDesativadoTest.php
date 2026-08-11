<?php

namespace Tests\Feature;

use App\Models\TinyToken;
use App\Services\Tiny\OrderSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Empresa com `sync => false` no config (ex.: GV, que parou de vender) deve ser
 * IGNORADA no fetch: nunca é buscada na API nem sofre stale-delete. Assim o saldo
 * já gravado permanece e continua aparecendo no dashboard.
 *
 * O perigo real que este teste trava: o stale-delete do modo full apaga os
 * pedidos do mês que não voltaram na varredura. Se a GV fosse varrida e a API
 * retornasse vazio, TODOS os pedidos dela sumiriam. O filtro de sync impede isso.
 */
class GvSyncDesativadoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mkOrder(string $company, string $id, string $date, float $value): void
    {
        DB::table('orders')->insert([
            'company' => $company, 'tiny_order_id' => $id, 'order_date' => $date,
            'value' => $value, 'status_code' => '1', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_empresa_sem_sync_nao_e_buscada_nem_stale_deleted(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 12, 0, 0, 'America/Sao_Paulo'));

        // Pedido já na base da GV (sync desativado) — seu saldo deve ser preservado.
        $this->mkOrder('gv', 'gv-1', '2026-06-05', 999);

        // Credenciais + tokens das 3 (no env de teste as TINY_*_CLIENT_* são nulas;
        // sem isso todas seriam puladas por "sem client_id/secret" e mascarariam o teste).
        foreach (['bella', 'linda', 'gv'] as $c) {
            config([
                "tiny.companies.{$c}.client_id" => 'cid-'.$c,
                "tiny.companies.{$c}.client_secret" => 'sec-'.$c,
            ]);
            TinyToken::create([
                'company' => $c, 'refresh_token' => 'r-'.$c, 'access_token' => 'a-'.$c,
                'access_expires_at' => now()->addHour(), 'refreshed_at' => now(),
            ]);
        }

        // GET /pedidos retorna UM pedido (id diferente dos já gravados). No modo
        // full isso ativa o stale-delete: quem for varrido perde os pedidos do mês
        // que não voltaram na varredura (li-1 e gv-1). Assim o teste distingue de
        // verdade quem foi varrido de quem foi pulado (resposta vazia não deletaria
        // nada e o teste passaria vacuamente).
        Http::fake([
            // refresh de token OAuth (se disparado) devolve um access_token válido
            '*accounts.tiny.com.br*' => Http::response(['access_token' => 'live', 'expires_in' => 3600], 200),
            // GET /pedidos: um pedido por dia
            '*' => Http::response(['itens' => [
                ['id' => 'scan-1', 'dataPedido' => '2026-06-05', 'valor' => 100, 'situacao' => 1, 'ecommerce' => ['nome' => 'Shopee']],
            ]], 200),
        ]);

        app(OrderSyncService::class)->sync('full');

        // Bella e Linda (sync ativo) foram varridas => receberam o pedido scan-1.
        $this->assertDatabaseHas('orders', ['company' => 'bella', 'tiny_order_id' => 'scan-1']);
        $this->assertDatabaseHas('orders', ['company' => 'linda', 'tiny_order_id' => 'scan-1']);

        // GV ('sync' => false) NÃO foi varrida: não recebeu scan-1 e o pedido antigo
        // dela permaneceu (sem fetch, sem stale-delete). Sem o filtro, a GV teria
        // recebido scan-1 e este teste falharia — pegando a regressão.
        $this->assertDatabaseMissing('orders', ['company' => 'gv', 'tiny_order_id' => 'scan-1']);
        $this->assertDatabaseHas('orders', ['company' => 'gv', 'tiny_order_id' => 'gv-1']);
    }
}
