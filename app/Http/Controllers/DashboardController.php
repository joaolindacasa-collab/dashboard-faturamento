<?php

namespace App\Http\Controllers;

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

        return view('dashboard', [
            'monthOptions' => $monthOptions,
            'selected'     => $selected,
            'data'         => $data,
        ]);
    }
}
