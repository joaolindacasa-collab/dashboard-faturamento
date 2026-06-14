<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SyncLog;
use App\Models\TinyToken;

class StatusController extends Controller
{
    public function index()
    {
        $lastOk = SyncLog::where('status', 'ok')->latest('finished_at')->first();
        $lastAny = SyncLog::latest('id')->first();
        $recentErrors = SyncLog::where('status', 'error')->latest('id')->limit(5)->get();

        $tokens = TinyToken::all()->keyBy('company');
        $companies = collect(config('tiny.companies', []))->map(function ($cfg, $slug) use ($tokens) {
            $tok = $tokens->get($slug);
            return [
                'slug'         => $slug,
                'name'         => $cfg['name'],
                'connected'    => (bool) $tok,
                'refreshed_at' => $tok?->refreshed_at,
                'orders'       => Order::where('company', $slug)->count(),
            ];
        })->values();

        return view('status', [
            'lastOk'        => $lastOk,
            'lastAny'       => $lastAny,
            'recentErrors'  => $recentErrors,
            'companies'     => $companies,
            'totalOrders'   => Order::count(),
        ]);
    }
}
