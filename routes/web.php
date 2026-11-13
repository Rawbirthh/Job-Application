<?php

use App\Http\Controllers\Candidate\ApplicationHistoryController;
use App\Http\Controllers\Candidate\CandidateDashboardController;
use App\Http\Controllers\Candidate\CandidateProfileController;
use App\Http\Controllers\Candidate\CandidateResumeController;
use App\Http\Controllers\Candidate\InterviewController as CandidateInterviewController;
use App\Http\Controllers\Candidate\JobApplicationController;
use App\Http\Controllers\Candidate\RecommendationController;
use App\Http\Controllers\Candidate\SavedJobController;
use App\Http\Controllers\Candidate\SavedSearchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employer\AnalyticsController as EmployerAnalyticsController;
use App\Http\Controllers\Employer\CompanyController;
use App\Http\Controllers\Employer\CompanyMemberController;
use App\Http\Controllers\Employer\EmployerDashboardController;
use App\Http\Controllers\Employer\EmployerJobApplicationController;
use App\Http\Controllers\Employer\EmployerOnboardingController;
use App\Http\Controllers\Employer\InterviewController as EmployerInterviewController;
use App\Http\Controllers\Employer\JobController;
use App\Http\Controllers\Employer\JobScreeningQuestionController;
use App\Http\Controllers\Employer\ScreeningQuestionController;
use App\Http\Controllers\Employer\ScreeningTemplateController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicJobController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'home')->name('home');
Route::get('/jobs', [PublicJobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}', [PublicJobController::class, 'show'])->name('jobs.show');

Route::middleware(['auth'])->group(function (): void {
    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->middleware(['throttle:30,1'])
        ->name('notifications.read-all');

    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->middleware(['throttle:30,1'])
        ->name('notifications.read');

    Route::post('reports', [ReportController::class, 'store'])
        ->middleware(['throttle:10,1'])
        ->name('reports.store');

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

        // Feature 32: employer-scoped analytics
        Route::get('analytics', [EmployerAnalyticsController::class, 'index'])
            ->middleware(['role:employer', 'employer.approved'])
            ->name('analytics.index');

        Route::prefix('companies')->name('companies.')->middleware(['role:employer', 'employer.approved'])->group(function (): void {
            Route::get('/', [CompanyController::class, 'index'])->name('index');
            Route::get('create', [CompanyController::class, 'create'])->name('create');
            Route::post('/', [CompanyController::class, 'store'])
                ->middleware(['throttle:20,1'])
                ->name('store');
            Route::get('{company}', [CompanyController::class, 'show'])->name('show');
            Route::get('{company}/edit', [CompanyController::class, 'edit'])->name('edit');
            Route::put('{company}', [CompanyController::class, 'update'])->name('update');

            Route::get('{company}/members', [CompanyMemberController::class, 'index'])->name('members.index');
            Route::post('{company}/members', [CompanyMemberController::class, 'store'])
                ->middleware(['throttle:20,1'])
                ->name('members.store');
            Route::patch('{company}/members/{member}', [CompanyMemberController::class, 'update'])
                ->middleware(['throttle:20,1'])
                ->name('members.update');
            Route::delete('{company}/members/{member}', [CompanyMemberController::class, 'destroy'])
                ->middleware(['throttle:20,1'])
                ->name('members.destroy');
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
            Route::get('{job}/applicants', [EmployerJobApplicationController::class, 'index'])
                ->name('applicants');

            Route::get('{job}/screening-questions', [JobScreeningQuestionController::class, 'index'])
                ->name('screening-questions.index');
            Route::post('{job}/screening-questions', [JobScreeningQuestionController::class, 'store'])
                ->middleware(['throttle:20,1'])
                ->name('screening-questions.store');
            Route::post('{job}/screening-questions/apply-template', [JobScreeningQuestionController::class, 'applyTemplate'])
                ->middleware(['throttle:20,1'])
                ->name('screening-questions.apply-template');
            Route::post('{job}/screening-questions/reorder', [JobScreeningQuestionController::class, 'reorder'])
                ->middleware(['throttle:20,1'])
                ->name('screening-questions.reorder');
            Route::put('screening-questions/{question}', [JobScreeningQuestionController::class, 'update'])
                ->middleware(['throttle:20,1'])
                ->name('screening-questions.update');
            Route::delete('screening-questions/{question}', [JobScreeningQuestionController::class, 'destroy'])
                ->middleware(['throttle:20,1'])
                ->name('screening-questions.destroy');
        });

        Route::middleware(['role:employer', 'employer.approved'])->group(function (): void {
            // Feature 27: screening question bank
            Route::get('screening-questions', [ScreeningQuestionController::class, 'index'])
                ->name('screening-questions.index');
            Route::post('screening-questions', [ScreeningQuestionController::class, 'store'])
                ->middleware(['throttle:20,1'])
                ->name('screening-questions.store');
            Route::put('screening-questions/{question}', [ScreeningQuestionController::class, 'update'])
                ->middleware(['throttle:20,1'])
                ->name('screening-questions.update');
            Route::delete('screening-questions/{question}', [ScreeningQuestionController::class, 'destroy'])
                ->middleware(['throttle:20,1'])
                ->name('screening-questions.destroy');

            // Feature 27: screening templates
            Route::get('screening-templates', [ScreeningTemplateController::class, 'index'])
                ->name('screening-templates.index');
            Route::post('screening-templates', [ScreeningTemplateController::class, 'store'])
                ->middleware(['throttle:20,1'])
                ->name('screening-templates.store');
            Route::get('screening-templates/{template}', [ScreeningTemplateController::class, 'show'])
                ->name('screening-templates.show');
            Route::put('screening-templates/{template}', [ScreeningTemplateController::class, 'update'])
                ->middleware(['throttle:20,1'])
                ->name('screening-templates.update');
            Route::delete('screening-templates/{template}', [ScreeningTemplateController::class, 'destroy'])
                ->middleware(['throttle:20,1'])
                ->name('screening-templates.destroy');

            Route::get('applications/{application}', [EmployerJobApplicationController::class, 'show'])
                ->name('applications.show');
            Route::post('applications/{application}/status', [EmployerJobApplicationController::class, 'updateStatus'])
                ->middleware(['throttle:30,1'])
                ->name('applications.update-status');
            Route::get('applications/{application}/resume', [EmployerJobApplicationController::class, 'downloadResume'])
                ->name('applications.resume.download');

            // Feature 26: Interview scheduling
            Route::post('applications/{application}/interviews', [EmployerInterviewController::class, 'store'])
                ->middleware(['throttle:20,1'])
                ->name('applications.interviews.store');
            Route::put('interviews/{interview}', [EmployerInterviewController::class, 'update'])
                ->middleware(['throttle:20,1'])
                ->name('interviews.update');
            Route::post('interviews/{interview}/cancel', [EmployerInterviewController::class, 'cancel'])
                ->middleware(['throttle:20,1'])
                ->name('interviews.cancel');
        });
    });

    Route::prefix('candidate')->name('candidate.')->middleware(['role:job_seeker'])->group(function (): void {
        Route::get('dashboard', CandidateDashboardController::class)->name('dashboard');
        Route::get('profile', [CandidateProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [CandidateProfileController::class, 'update'])
            ->middleware(['throttle:20,1'])
            ->name('profile.update');
        Route::get('profile/resumes', [CandidateResumeController::class, 'index'])->name('resumes.index');
        Route::post('profile/resume', [CandidateResumeController::class, 'upload'])
            ->middleware(['throttle:10,1'])
            ->name('resumes.upload');
        Route::get('resume/{resume}/download', [CandidateResumeController::class, 'download'])
            ->name('resume.download');
        Route::delete('profile/resume/{resume}', [CandidateResumeController::class, 'delete'])
            ->name('resumes.delete');

        Route::get('recommendations', [RecommendationController::class, 'index'])
            ->name('recommendations.index');

        Route::get('saved-jobs', [SavedJobController::class, 'index'])->name('saved-jobs.index');
        Route::post('saved-jobs', [SavedJobController::class, 'store'])
            ->middleware(['throttle:30,1'])
            ->name('saved-jobs.store');
        Route::delete('saved-jobs/{jobPosting}', [SavedJobController::class, 'destroy'])
            ->name('saved-jobs.destroy');

        Route::get('saved-searches', [SavedSearchController::class, 'index'])
            ->name('saved-searches.index');
        Route::post('saved-searches', [SavedSearchController::class, 'store'])
            ->middleware(['throttle:20,1'])
            ->name('saved-searches.store');
        Route::put('saved-searches/{savedSearch}', [SavedSearchController::class, 'update'])
            ->middleware(['throttle:20,1'])
            ->name('saved-searches.update');
        Route::delete('saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])
            ->name('saved-searches.destroy');

        Route::get('jobs/{job}/apply', [JobApplicationController::class, 'create'])->name('applications.create');
        Route::post('jobs/{job}/apply', [JobApplicationController::class, 'store'])
            ->middleware(['throttle:20,1'])
            ->name('applications.store');

        // Feature 22: Candidate application history and status view
        Route::get('applications', [ApplicationHistoryController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [ApplicationHistoryController::class, 'show'])->name('applications.show');
        Route::post('applications/{application}/withdraw', [ApplicationHistoryController::class, 'withdraw'])
            ->middleware(['throttle:20,1'])
            ->name('applications.withdraw');

        // Feature 26: Interview responses
        Route::post('interviews/{interview}/respond', [CandidateInterviewController::class, 'respond'])
            ->middleware(['throttle:20,1'])
            ->name('interviews.respond');
    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
