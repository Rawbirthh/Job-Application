<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectJobRequest;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Job::class);

        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $query = Job::query()
            ->with(['company:id,name,slug', 'category:id,name,slug'])
            ->latest();

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        } else {
            // Default queue shows pending_review
            if ($status === '') {
                $query->where('status', 'pending_review');
            }
        }

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $jobs = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/jobs/index', [
            'jobs' => $jobs->through(fn (Job $job): array => [
                'id' => $job->id,
                'title' => $job->title,
                'status' => $job->status,
                'created_at' => $job->created_at?->toIso8601String(),
                'company' => $job->company ? [
                    'id' => $job->company->id,
                    'name' => $job->company->name,
                ] : null,
                'category' => $job->category ? [
                    'id' => $job->category->id,
                    'name' => $job->category->name,
                ] : null,
            ]),
            'filters' => [
                'status' => $status !== '' ? $status : 'pending_review',
                'search' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function show(Job $job): Response
    {
        $this->authorize('moderate', $job);

        $job->load([
            'company:id,name,slug,owner_user_id',
            'company.owner:id,name,email',
            'category:id,name,slug',
            'employmentType:id,name,slug',
            'workplaceArrangement:id,name,slug',
            'experienceLevel:id,name,slug',
            'payPeriod:id,name,slug',
        ]);

        return Inertia::render('admin/jobs/show', [
            'job' => [
                'id' => $job->id,
                'title' => $job->title,
                'description' => $job->description,
                'responsibilities' => $job->responsibilities,
                'requirements' => $job->requirements,
                'status' => $job->status,
                'rejection_reason' => $job->rejection_reason,
                'salary_min' => $job->salary_min ? (float) $job->salary_min : null,
                'salary_max' => $job->salary_max ? (float) $job->salary_max : null,
                'salary_currency' => $job->salary_currency,
                'salary_negotiable' => $job->salary_negotiable,
                'location' => $job->location,
                'application_deadline' => $job->application_deadline?->toIso8601String(),
                'company' => $job->company ? [
                    'id' => $job->company->id,
                    'name' => $job->company->name,
                    'owner' => $job->company->owner ? [
                        'name' => $job->company->owner->name,
                        'email' => $job->company->owner->email,
                    ] : null,
                ] : null,
                'category' => $job->category?->name,
                'employment_type' => $job->employmentType?->name,
                'workplace_arrangement' => $job->workplaceArrangement?->name,
                'experience_level' => $job->experienceLevel?->name,
                'pay_period' => $job->payPeriod?->name,
            ],
        ]);
    }

    public function approve(Job $job): RedirectResponse
    {
        $this->authorize('moderate', $job);

        if ($job->status !== 'pending_review') {
            return back()->with('error', __('Only jobs pending review can be approved.'));
        }

        $job->status = 'published';
        $job->rejection_reason = null;
        $job->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job approved and published successfully.'),
        ]);

        return to_route('admin.jobs.index');
    }

    public function reject(RejectJobRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('moderate', $job);

        if ($job->status !== 'pending_review') {
            return back()->with('error', __('Only jobs pending review can be rejected.'));
        }

        $job->status = 'rejected';
        $job->rejection_reason = $request->validated('rejection_reason');
        $job->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job rejected with reason.'),
        ]);

        return to_route('admin.jobs.index');
    }

    public function close(Job $job): RedirectResponse
    {
        $this->authorize('close', $job);

        $job->status = 'closed';
        $job->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job closed successfully.'),
        ]);

        return back();
    }
}
