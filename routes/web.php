<?php

use App\Http\Controllers\Candidate\CandidateDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employer\EmployerDashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');

Route::middleware(['auth'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('employer')->name('employer.')->middleware(['role:employer'])->group(function (): void {
        Route::get('dashboard', EmployerDashboardController::class)->name('dashboard');
    });

    Route::prefix('candidate')->name('candidate.')->middleware(['role:job_seeker'])->group(function (): void {
        Route::get('dashboard', CandidateDashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
