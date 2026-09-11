<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     *
     * Placeholder for Giai đoạn 1 — real metrics land in Giai đoạn 6.
     */
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
