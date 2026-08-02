<?php

namespace App\Http\Controllers;

use App\Services\PracticeMonitoringService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(PracticeMonitoringService $monitoring): Response
    {
        return Inertia::render('dashboard', $monitoring->dashboard());
    }
}
