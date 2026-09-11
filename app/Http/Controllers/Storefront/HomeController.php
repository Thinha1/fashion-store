<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the storefront home page.
     *
     * Placeholder for Giai đoạn 1 — real catalog content lands in Giai đoạn 3.
     */
    public function __invoke(): View
    {
        return view('storefront.home');
    }
}
