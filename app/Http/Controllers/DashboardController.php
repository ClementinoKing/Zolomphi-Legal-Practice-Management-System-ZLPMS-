<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PracticeMonitoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PracticeMonitoringService $monitoring): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('dashboard', $monitoring->dashboard($user));
    }
}
