<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportIndexRequest;
use App\Models\User;
use App\Services\PracticeMonitoringService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(ReportIndexRequest $request, PracticeMonitoringService $monitoring): Response
    {
        $startDate = Carbon::parse($request->validated('start_date', now()->startOfMonth()->toDateString()))->startOfDay();
        $endDate = Carbon::parse($request->validated('end_date', now()->endOfMonth()->toDateString()))->endOfDay();

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('reports/index', [
            ...$monitoring->reports($user, $startDate, $endDate),
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }
}
