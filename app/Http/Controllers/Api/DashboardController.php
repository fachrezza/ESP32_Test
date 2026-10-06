<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * GET /dashboard
     * Halaman monitoring mesin (data realtime via Livewire component mesin-monitor).
     */
    public function index(): View
    {
        return view('dashboard');
    }
}
