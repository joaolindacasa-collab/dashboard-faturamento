<?php

namespace Tests\Unit;

use App\Services\Tiny\OrderSyncService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Cobre o parsing do campo `valor` do Tiny v3, que chega em formatos variados
 * (número, string US, string BR). Regressão: "1.234,56" era lido como 1,23.
 */
class OrderSyncParseMoneyTest extends TestCase
{
    private function parse($raw): float
    {
        $ref = new ReflectionClass(OrderSyncService::class);
        $obj = $ref->newInstanceWithoutConstructor();
        $m = $ref->getMethod('parseMoney');
        $m->setAccessible(true);

        return $m->invoke($obj, $raw);
    }

    /**
     * @dataProvider valores
     */
    public function test_parse_money(float $esperado, $entrada): void
    {
        $this->assertEqualsWithDelta($esperado, $this->parse($entrada), 0.001);
    }

    public static function valores(): array
    {
        return [
            'US decimal'                 => [1234.56, '1234.56'],
            'BR decimal simples'         => [1234.56, '1234,56'],
            'BR milhar + decimal'        => [1234.56, '1.234,56'],
            'US milhar + decimal'        => [1234.56, '1,234.56'],
            'BR com prefixo R$'          => [2500.00, 'R$ 2.500,00'],
            'BR milhao'                  => [1234567.89, '1.234.567,89'],
            'US milhao'                  => [1234567.89, '1,234,567.89'],
            'float nativo'               => [1234.56, 1234.56],
            'int nativo'                 => [1234.0, 1234],
            'string inteiro'             => [1234.0, '1234'],
            'centena'                    => [100.0, '100'],
            'vazio'                      => [0.0, ''],
            'null'                       => [0.0, null],
            'centavos BR'                => [0.99, '0,99'],
            'duas casas US'              => [12.50, '12.50'],
        ];
    }
}
