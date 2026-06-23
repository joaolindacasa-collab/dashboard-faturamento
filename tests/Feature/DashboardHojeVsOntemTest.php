<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\Tiny\DashboardAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante que "hoje vs ontem" compara hoje (parcial) contra ontem
 * PROPORCIONAL ao horário atual — e não contra o dia inteiro de ontem.
 */
class DashboardHojeVsOntemTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ontem_e_escalado_pela_fracao_do_dia(): void
    {
        // Meio-dia => 50% do dia decorrido.
        Carbon::setTestNow(Carbon::create(2026, 6, 23, 12, 0, 0, 'America/Sao_Paulo'));

        // Ontem (22/06): 1000 no total. Hoje (23/06): 400.
        Order::create(['company' => 'linda', 'tiny_order_id' => 'y1', 'order_date' => '2026-06-22', 'value' => 600, 'status_code' => '1']);
        Order::create(['company' => 'linda', 'tiny_order_id' => 'y2', 'order_date' => '2026-06-22', 'value' => 400, 'status_code' => '1']);
        Order::create(['company' => 'linda', 'tiny_order_id' => 't1', 'order_date' => '2026-06-23', 'value' => 400, 'status_code' => '1']);

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

        Order::create(['company' => 'linda', 'tiny_order_id' => 'y1', 'order_date' => '2026-06-22', 'value' => 1000, 'status_code' => '1']);
        Order::create(['company' => 'linda', 'tiny_order_id' => 't1', 'order_date' => '2026-06-23', 'value' => 1000, 'status_code' => '1']);

        $d = (new DashboardAggregator())->forMonth('2026-06');
        $hvo = $d['hoje_vs_ontem'];

        $this->assertSame(100, $hvo['day_pct']);
        $this->assertGreaterThan(999.0, $hvo['total']['ontem']);
    }
}
