<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class CandidateDashboardController extends Controller
{
    /**
     * Render the candidate (job seeker) dashboard.
     */
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = request()->user();

        return Inertia::render('candidate/dashboard', [
            'applicationCount' => JobApplication::query()->where('user_id', $user->id)->count(),
        ]);
    }
}
