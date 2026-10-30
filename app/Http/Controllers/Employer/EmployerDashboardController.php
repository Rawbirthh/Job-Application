<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class EmployerDashboardController extends Controller
{
    /**
     * Render the employer dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('employer/dashboard');
    }
}
