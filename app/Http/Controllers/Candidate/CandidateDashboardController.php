<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CandidateDashboardController extends Controller
{
    /**
     * Render the candidate (job seeker) dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('candidate/dashboard');
    }
}
