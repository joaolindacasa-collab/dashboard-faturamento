<?php

namespace Tests\Feature;

use App\Services\Tiny\DashboardAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Hoje vs ontem": hoje (parcial) vs ontem PROPORCIONAL ao horário atual.
 * Projeção: hoje conta como fração do dia, não como dia inteiro.
 *
 * Os pedidos são inseridos via query builder (não via Order::create) de
 * propósito: a coluna order_date é DATE no MySQL/Postgres (guarda só a data),
 * mas o sqlite dos testes não tem DATE real e o cast 'date' gravaria
 * '...00:00:00', quebrando o whereBetween. Inserindo a string YYYY-MM-DD
 * reproduzimos o comportamento do banco real.
 */
class DashboardHojeVsOntemTest extends TestCase
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

    public function test_ontem_e_escalado_pela_fracao_do_dia(): void
    {
        // Meio-dia => 50% do dia decorrido.
        Carbon::setTestNow(Carbon::create(2026, 6, 23, 12, 0, 0, 'America/Sao_Paulo'));

        // Ontem (22/06): 1000 no total. Hoje (23/06): 400.
        $this->mkOrder('linda', 'y1', '2026-06-22', 600);
        $this->mkOrder('linda', 'y2', '2026-06-22', 400);
        $this->mkOrder('linda', 't1', '2026-06-23', 400);

        $d = (new DashboardAggregator())->forMonth('2026-06');
        $hvo = $d['hoje_vs_ontem'];

        $this->assertSame(50, $hvo['day_pct']);
        $this->assertSame('12:00', $hvo['time_now']);
        // ontem proporcional = 1000 * 0.5 = 500 (não 1000).
        $this->assertSame(500.0, $hvo['total']['ontem']);
        $this->assertSame(400.0, $hvo['total']['hoje']);
        // delta = (400 - 500) / 500 = -20%.
        $this->assertSame(-20.0, $hvo['total']['delta']);
    }

    public function test_dia_inteiro_continua_sem_vies_no_fim_do_dia(): void
    {
        // 23:59:59 => ~100% do dia => ontem praticamente cheio.
        Carbon::setTestNow(Carbon::create(2026, 6, 23, 23, 59, 59, 'America/Sao_Paulo'));

        $this->mkOrder('linda', 'y1', '2026-06-22', 1000);
        $this->mkOrder('linda', 't1', '2026-06-23', 1000);

        $d = (new DashboardAggregator())->forMonth('2026-06');
        $hvo = $d['hoje_vs_ontem'];

        $this->assertSame(100, $hvo['day_pct']);
        $this->assertGreaterThan(999.0, $hvo['total']['ontem']);
    }

    public function test_projecao_conta_hoje_proporcional_ao_horario(): void
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
        // (Fórmula antiga, hoje como dia cheio: 950 / 10 * 30 = 2850.)
        $this->assertSame(950.0, $d['kpis']['faturamento']['value']);
        $this->assertSame(3000.0, $d['kpis']['projecao']['value']);
    }
}
