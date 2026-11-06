<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationHistoryController extends Controller
{
    /**
     * Display a paginated listing of the candidate's applications.
     */
    public function index(): Response
    {
        /** @var User $user */
        $user = request()->user();

        $applications = JobApplication::query()
            ->where('user_id', $user->id)
            ->with([
                'jobPosting' => function ($query): void {
                    $query->select([
                        'id',
                        'title',
                        'location',
                        'salary_min',
                        'salary_max',
                        'salary_currency',
                        'salary_negotiable',
                        'status',
                        'application_deadline',
                        'company_id',
                    ])
                        ->with([
                            'company' => function ($query): void {
                                $query->select(['id', 'name', 'slug', 'logo_path']);
                            },
                            'category:id,name,slug',
                            'employmentType:id,name,slug',
                            'workplaceArrangement:id,name,slug',
                            'experienceLevel:id,name,slug',
                            'payPeriod:id,name,slug',
                        ]);
                },
                'jobPosting.company:id,name,slug,logo_path',
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('candidate/applications/index', [
            'applications' => $applications->through(function (JobApplication $application): array {
                $job = $application->jobPosting;

                return [
                    'id' => $application->id,
                    'job_id' => $job->id,
                    'job_title' => $job->title,
                    'company' => $job->company ? [
                        'id' => $job->company->id,
                        'name' => $job->company->name,
                        'logo_path' => $job->company->logo_path,
                    ] : null,
                    'location' => $job->location,
                    'employment_type' => $job->employmentType ? $job->employmentType->name : null,
                    'workplace_arrangement' => $job->workplaceArrangement ? $job->workplaceArrangement->name : null,
                    'salary_min' => $job->salary_min,
                    'salary_max' => $job->salary_max,
                    'salary_currency' => $job->salary_currency,
                    'salary_negotiable' => $job->salary_negotiable,
                    'application_deadline' => $job->application_deadline?->toDateString(),
                    'status' => $application->status,
                    'applied_at' => $application->created_at?->toIso8601String(),
                    'cover_letter' => $application->cover_letter,
                ];
            }),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
                'prev_page_url' => $applications->previousPageUrl(),
                'next_page_url' => $applications->nextPageUrl(),
            ],
        ]);
    }

    public function show(JobApplication $application): Response
    {
        /** @var User $user */
        $user = request()->user();

        $this->authorize('view', $application);

        $application->load([
            'jobPosting.company',
            'jobPosting.employmentType',
            'jobPosting.workplaceArrangement',
            'jobPosting.experienceLevel',
            'jobPosting.payPeriod',
            'candidateResume',
        ]);

        $job = $application->jobPosting;
        $company = $job->company;

        return Inertia::render('candidate/applications/show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'applied_at' => $application->created_at?->toIso8601String(),
                'cover_letter' => $application->cover_letter,
                'resume' => $application->candidateResume ? [
                    'id' => $application->candidateResume->id,
                    'original_filename' => $application->candidateResume->original_filename,
                    'mime_type' => $application->candidateResume->mime_type,
                    'size' => $application->candidateResume->size,
                ] : null,
            ],
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
                'responsibilities' => $job->responsibilities,
                'requirements' => $job->requirements,
                'location' => $job->location,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'salary_currency' => $job->salary_currency,
                'salary_negotiable' => $job->salary_negotiable,
                'application_deadline' => $job->application_deadline?->toIso8601String(),
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'logo_path' => $company->logo_path,
                    'overview' => $company->overview,
                    'website' => $company->website,
                    'location' => $company->location,
                    'industry' => $company->industry,
                    'size_band' => $company->size_band,
                ] : null,
                'employment_type' => $job->employmentType ? ['name' => $job->employmentType->name] : null,
                'workplace_arrangement' => $job->workplaceArrangement ? ['name' => $job->workplaceArrangement->name] : null,
                'experience_level' => $job->experienceLevel ? ['name' => $job->experienceLevel->name] : null,
                'pay_period' => $job->payPeriod ? ['name' => $job->payPeriod->name] : null,
            ],
        ]);
    }
}