<?php

namespace Tests\Feature;

use App\Services\Tiny\DashboardAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Projeção do mês: hoje (dia parcial) conta como FRAÇÃO do dia, não como dia
 * inteiro, evitando subestimar a projeção no começo do dia. Há projeção total
 * (KPI) e projeção por empresa (painel projecao_mes), cada uma com Δ vs. o
 * total do mês anterior INTEIRO.
 *
 * Os pedidos são inseridos via query builder (não via Order::create) de
 * propósito: a coluna order_date é DATE no MySQL/Postgres (guarda só a data),
 * mas o sqlite dos testes não tem DATE real e o cast 'date' gravaria
 * '...00:00:00', quebrando o whereBetween. Inserindo a string YYYY-MM-DD
 * reproduzimos o comportamento do banco real.
 */
class DashboardProjecaoTest extends TestCase
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
            'company'       => $company,
            'tiny_order_id' => $id,
            'order_date'    => $date,
            'value'         => $value,
            'status_code'   => '1',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_projecao_total_conta_hoje_proporcional_ao_horario(): void
    {
        // Dia 10 de junho (mês de 30 dias), meio-dia => fração 0.5.
        // Dias efetivos = (10 - 1) + 0.5 = 9.5.
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 12, 0, 0, 'America/Sao_Paulo'));

        // 9 dias completos (01..09) com 100 cada = 900; hoje (10) parcial = 50.
        for ($dia = 1; $dia <= 9; $dia++) {
            $this->mkOrder('linda', 'd'.$dia, sprintf('2026-06-%02d', $dia), 100);
        }
        $this->mkOrder('linda', 'hoje', '2026-06-10', 50);

        $d = (new DashboardAggregator())->forMonth('2026-06');

        // grand = 950; dias efetivos = 9.5; projeção = 950 / 9.5 * 30 = 3000.
        // (Fórmula ingênua, hoje como dia cheio: 950 / 10 * 30 = 2850.)
        $this->assertSame(950.0, $d['kpis']['faturamento']['value']);
        $this->assertSame(3000.0, $d['kpis']['projecao']['value']);

        // Mesmo número aparece no total do painel projecao_mes.
        $this->assertSame(950.0, $d['projecao_mes']['total']['atual']);
        $this->assertSame(3000.0, $d['projecao_mes']['total']['projecao']);
    }

    public function test_projecao_por_empresa_com_delta_vs_mes_anterior(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 12, 0, 0, 'America/Sao_Paulo'));

        // Junho: linda 9x100 + 50 = 950 -> proj 3000; bella 9x200 + 100 = 1900 -> proj 6000.
        for ($dia = 1; $dia <= 9; $dia++) {
            $this->mkOrder('linda', 'l'.$dia, sprintf('2026-06-%02d', $dia), 100);
            $this->mkOrder('bella', 'b'.$dia, sprintf('2026-06-%02d', $dia), 200);
        }
        $this->mkOrder('linda', 'lhoje', '2026-06-10', 50);
        $this->mkOrder('bella', 'bhoje', '2026-06-10', 100);

        // Maio (mês anterior INTEIRO): só linda, total 3000.
        $this->mkOrder('linda', 'mai', '2026-05-15', 3000);

        $d = (new DashboardAggregator())->forMonth('2026-06');

        $rows = collect($d['projecao_mes']['rows'])->keyBy('slug');

        // linda: proj 3000 vs maio 3000 => delta 0.0.
        $this->assertSame(950.0, $rows['linda']['atual']);
        $this->assertSame(3000.0, $rows['linda']['projecao']);
        $this->assertSame(3000.0, $rows['linda']['mes_anterior']);
        $this->assertSame(0.0, $rows['linda']['delta']);

        // bella: proj 6000 vs maio 0 => delta null (sem base de comparação).
        $this->assertSame(1900.0, $rows['bella']['atual']);
        $this->assertSame(6000.0, $rows['bella']['projecao']);
        $this->assertSame(0.0, $rows['bella']['mes_anterior']);
        $this->assertNull($rows['bella']['delta']);

        // Total: atual 2850, proj 9000, vs maio 3000 => delta 200%.
        $this->assertSame(2850.0, $d['projecao_mes']['total']['atual']);
        $this->assertSame(9000.0, $d['projecao_mes']['total']['projecao']);
        $this->assertSame(3000.0, $d['projecao_mes']['total']['mes_anterior']);
        $this->assertSame(200.0, $d['projecao_mes']['total']['delta']);
    }

    public function test_serie_diaria_empilha_por_empresa(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 12, 0, 0, 'America/Sao_Paulo'));

        // linda: 100/dia (01..09) + 50 no dia 10; bella: 200/dia (01..09) + 100 no dia 10.
        for ($dia = 1; $dia <= 9; $dia++) {
            $this->mkOrder('linda', 'l'.$dia, sprintf('2026-06-%02d', $dia), 100);
            $this->mkOrder('bella', 'b'.$dia, sprintf('2026-06-%02d', $dia), 200);
        }
        $this->mkOrder('linda', 'lhoje', '2026-06-10', 50);
        $this->mkOrder('bella', 'bhoje', '2026-06-10', 100);

        $d = (new DashboardAggregator())->forMonth('2026-06');
        $fd = $d['faturamento_diario'];

        // Mês atual: vai do dia 1 ao dia de hoje (10).
        $this->assertCount(10, $fd['days']);
        // Maior dia = 300 (dias completos: linda 100 + bella 200).
        $this->assertSame(300.0, $fd['max']);

        $dia1 = collect($fd['days'])->firstWhere('dia', 1);
        $this->assertSame(100.0, $dia1['values']['linda']);
        $this->assertSame(200.0, $dia1['values']['bella']);
        $this->assertSame(300.0, $dia1['total']);

        $dia10 = collect($fd['days'])->firstWhere('dia', 10);
        $this->assertSame(50.0, $dia10['values']['linda']);
        $this->assertSame(100.0, $dia10['values']['bella']);
        $this->assertSame(150.0, $dia10['total']);
    }
}
