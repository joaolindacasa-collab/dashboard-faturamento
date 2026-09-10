<?php

namespace Tests\Unit;

use App\Services\Tiny\ChannelNormalizer;
use Tests\TestCase;

class ChannelNormalizerTest extends TestCase
{
    public function test_canais_duplicados_normalizam_para_o_canal_unico(): void
    {
        // "Magalu Marketplace" e "Magalu" são o mesmo canal (alias no config).
        $this->assertSame('Magalu', ChannelNormalizer::normalize('Magalu Marketplace'));
        $this->assertSame('Magalu', ChannelNormalizer::normalize('magalu marketplace'));
        $this->assertSame('Magalu', ChannelNormalizer::normalize('MAGALU MARKETPLACE'));
        $this->assertSame('Magalu', ChannelNormalizer::normalize('Magalu'));

        // "Amazon Fba Classic" e "Amazon" também.
        $this->assertSame('Amazon', ChannelNormalizer::normalize('Amazon Fba Classic'));
        $this->assertSame('Amazon', ChannelNormalizer::normalize('amazon fba classic'));
        $this->assertSame('Amazon', ChannelNormalizer::normalize('Amazon'));
    }

    public function test_canal_desconhecido_vira_title_case_e_vazio_vira_sem_canal(): void
    {
        $this->assertSame('Loja Nova', ChannelNormalizer::normalize('loja nova'));
        $this->assertSame('Sem canal', ChannelNormalizer::normalize(''));
        $this->assertSame('Sem canal', ChannelNormalizer::normalize(null));
    }
}
