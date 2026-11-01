<?php

use App\Http\Controllers\Candidate\CandidateDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employer\EmployerDashboardController;
use App\Http\Controllers\Employer\EmployerOnboardingController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');

Route::middleware(['auth'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('employer')->name('employer.')->group(function (): void {
        Route::get('onboarding', [EmployerOnboardingController::class, 'index'])
            ->name('onboarding.index');

        Route::post('onboarding', [EmployerOnboardingController::class, 'store'])
            ->middleware(['throttle:10,1'])
            ->name('onboarding.store');

        Route::get('dashboard', EmployerDashboardController::class)
            ->middleware(['role:employer', 'employer.approved'])
            ->name('dashboard');
    });

    Route::prefix('candidate')->name('candidate.')->middleware(['role:job_seeker'])->group(function (): void {
        Route::get('dashboard', CandidateDashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
