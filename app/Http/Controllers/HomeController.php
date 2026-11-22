<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('home', [
            // Keep this lazy: evaluating the counts at route registration time
            // would query the database before migrations in tests.
            'stats' => fn (): array => [
                'openRoles' => Job::published()->count(),
                'hiringCompanies' => Job::published()->distinct()->count('company_id'),
            ],
        ]);
    }
}
