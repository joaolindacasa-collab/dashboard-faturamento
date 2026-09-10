<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use App\Services\Tiny\DashboardAggregator;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardAggregator $agg)
    {
        $months = $agg->availableMonths();
        $selected = $request->query('month');

        if (! $selected || ! in_array($selected, $months, true)) {
            $selected = Carbon::now($agg->timezone())->format('Y-m');
        }

        $currentKey = Carbon::now($agg->timezone())->format('Y-m');
        $monthOptions = [];
        foreach ($months as $mk) {
            $monthOptions[$mk] = $agg->monthLabel($mk) . ($mk === $currentKey ? ' (atual)' : '');
        }

        $data = $agg->forMonth($selected);

        // Frescor da sincronização: idade da última sync OK concluída (indicador no header).
        $lastOk = SyncLog::where('status', 'ok')->whereNotNull('finished_at')->latest('finished_at')->first();
        $sync = [
            'age_min' => $lastOk?->finished_at ? (int) round($lastOk->finished_at->diffInMinutes(now())) : null,
            'at'      => $lastOk?->finished_at?->timezone($agg->timezone())->format('d/m H:i'),
        ];

        return view('dashboard', [
            'monthOptions' => $monthOptions,
            'selected'     => $selected,
            'data'         => $data,
            'sync'         => $sync,
        ]);
    }
}
