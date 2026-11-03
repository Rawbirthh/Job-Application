<?php

use App\Http\Controllers\Candidate\CandidateDashboardController;
use App\Http\Controllers\Candidate\CandidateProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employer\CompanyController;
use App\Http\Controllers\Employer\EmployerDashboardController;
use App\Http\Controllers\Employer\EmployerOnboardingController;
use App\Http\Controllers\Employer\JobController;
use App\Http\Controllers\PublicJobController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');
Route::get('/jobs', [PublicJobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}', [PublicJobController::class, 'show'])->name('jobs.show');

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

        Route::prefix('companies')->name('companies.')->middleware(['role:employer', 'employer.approved'])->group(function (): void {
            Route::get('/', [CompanyController::class, 'index'])->name('index');
            Route::get('create', [CompanyController::class, 'create'])->name('create');
            Route::post('/', [CompanyController::class, 'store'])
                ->middleware(['throttle:20,1'])
                ->name('store');
            Route::get('{company}', [CompanyController::class, 'show'])->name('show');
            Route::get('{company}/edit', [CompanyController::class, 'edit'])->name('edit');
            Route::put('{company}', [CompanyController::class, 'update'])->name('update');
        });

        Route::prefix('jobs')->name('jobs.')->middleware(['role:employer', 'employer.approved'])->group(function (): void {
            Route::get('/', [JobController::class, 'index'])->name('index');
            Route::get('create', [JobController::class, 'create'])->name('create');
            Route::post('/', [JobController::class, 'store'])
                ->middleware(['throttle:20,1'])
                ->name('store');
            Route::get('{job}/edit', [JobController::class, 'edit'])->name('edit');
            Route::put('{job}', [JobController::class, 'update'])
                ->middleware(['throttle:20,1'])
                ->name('update');
            Route::post('{job}/submit', [JobController::class, 'submit'])
                ->middleware(['throttle:20,1'])
                ->name('submit');
            Route::post('{job}/close', [JobController::class, 'close'])
                ->middleware(['throttle:20,1'])
                ->name('close');
        });
    });

    Route::prefix('candidate')->name('candidate.')->middleware(['role:job_seeker'])->group(function (): void {
        Route::get('dashboard', CandidateDashboardController::class)->name('dashboard');
        Route::get('profile', [CandidateProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [CandidateProfileController::class, 'update'])
            ->middleware(['throttle:20,1'])
            ->name('profile.update');
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
