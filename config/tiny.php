<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API v3 do Tiny ERP
    |--------------------------------------------------------------------------
    | Equivalente ao tiny-config.json do projeto Python. As credenciais
    | (client_id/client_secret) vêm do .env pra não irem versionadas.
    */

    'api_base' => env('TINY_API_BASE', 'https://api.tiny.com.br/public-api/v3'),

    'oauth' => [
        'auth_url'  => env('TINY_OAUTH_AUTH_URL', 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/auth'),
        'token_url' => env('TINY_OAUTH_TOKEN_URL', 'https://accounts.tiny.com.br/realms/tiny/protocol/openid-connect/token'),
        // Mesmo redirect_uri registrado nas apps Tiny. A página pode dar 404 ao
        // redirecionar — só precisamos do ?code= da barra de endereços.
        'redirect_uri' => env('TINY_OAUTH_REDIRECT_URI', 'https://163-176-145-105.sslip.io/oauth/callback'),
        'scope' => env('TINY_OAUTH_SCOPE', 'openid'),
    ],

    'timezone' => env('TINY_TIMEZONE', 'America/Sao_Paulo'),

    'page_size' => (int) env('TINY_PAGE_SIZE', 100),

    'incremental_lookback_days' => (int) env('TINY_INCREMENTAL_LOOKBACK_DAYS', 2),

    /*
    | Códigos de situação da v3 que contam como "faturamento".
    | Mapeamento (confirmado 25/05/2026): 0=Aberta 1=Faturada 2=Cancelada
    | 3=Aprovada 4=Preparando Envio 5=Enviada 6=Entregue 7=Pronto Envio
    | 8=Dados Incompletos 9=Não Entregue. Faturamento = 1,3,4,5,6,7,9.
    */
    'status_filter' => ['1', '3', '4', '5', '6', '7', '9'],

    'status_names' => [
        '0' => 'Aberta',
        '1' => 'Faturada',
        '2' => 'Cancelada',
        '3' => 'Aprovada',
        '4' => 'Preparando Envio',
        '5' => 'Enviada',
        '6' => 'Entregue',
        '7' => 'Pronto Envio',
        '8' => 'Dados Incompletos',
        '9' => 'Não Entregue',
    ],

    /*
    | Empresas. As 3 contas Tiny. As credenciais saem do .env.
    */
    'companies' => [
        'bella' => [
            'name'  => 'Bella Primavera',
            'color' => '#7c5cff',
            'client_id'     => env('TINY_BELLA_CLIENT_ID'),
            'client_secret' => env('TINY_BELLA_CLIENT_SECRET'),
        ],
        'linda' => [
            'name'  => 'Linda Casa',
            'color' => '#22d3ee',
            'client_id'     => env('TINY_LINDA_CLIENT_ID'),
            'client_secret' => env('TINY_LINDA_CLIENT_SECRET'),
        ],
        'gv' => [
            'name'  => 'GV Casa Shop',
            'color' => '#ffb84d',
            'client_id'     => env('TINY_GV_CLIENT_ID'),
            'client_secret' => env('TINY_GV_CLIENT_SECRET'),
        ],
    ],

    'known_channels' => ['Mercado Livre', 'Shopee', 'Magalu', 'Amazon', 'Yampi'],

    'unknown_channel' => 'Sem canal',

    'channel_aliases' => [
        'mercado livre' => 'Mercado Livre',
        'mercadolivre' => 'Mercado Livre',
        'mlb' => 'Mercado Livre',
        'ml_casa hoenning' => 'Mercado Livre',
        'mercado livre fulfillment' => 'Mercado Livre',
        'shopee' => 'Shopee',
        'magalu' => 'Magalu',
        'magazine luiza' => 'Magalu',
        'amazon' => 'Amazon',
        'yampi' => 'Yampi',
        'linda casa' => 'Yampi',
        'loja virtual' => 'Yampi',
        'site' => 'Yampi',
        'site proprio' => 'Yampi',
        'tray' => 'Yampi',
        'nuvemshop' => 'Yampi',
        'shopify' => 'Yampi',
        'woocommerce' => 'Yampi',
    ],
];
