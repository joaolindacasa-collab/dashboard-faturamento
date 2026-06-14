<?php

namespace App\Services\Tiny;

class ChannelNormalizer
{
    public static function normalize(?string $ecommerce): string
    {
        $unknown = config('tiny.unknown_channel', 'Sem canal');
        if (! $ecommerce) {
            return $unknown;
        }
        $key = mb_strtolower(trim($ecommerce));
        if ($key === '') {
            return $unknown;
        }
        $aliases = config('tiny.channel_aliases', []);
        if (isset($aliases[$key])) {
            return $aliases[$key];
        }
        // title case como fallback (ex.: "loja x" -> "Loja X")
        return mb_convert_case(trim($ecommerce), MB_CASE_TITLE, 'UTF-8');
    }
}
