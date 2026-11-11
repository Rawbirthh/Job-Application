<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DismissReportRequest;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Models\Report;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Report::class);

        $status = $request->string('status')->toString();
        $subject = $request->string('subject')->toString();
        $reason = $request->string('reason')->toString();

        // Whitelist filters: anything unrecognised falls back to the queue defaults.
        $statusFilter = in_array($status, [...ReportStatus::values(), 'all'], true) ? $status : 'pending';
        $subjectFilter = in_array($subject, ['job', 'company', 'all'], true) ? $subject : 'all';
        $reasonFilter = in_array($reason, ReportReason::values(), true) ? $reason : 'all';

        $query = Report::query()
            ->with(['reporter:id,name', 'jobPosting:id,company_id,title,status', 'company:id,name,slug,is_active'])
            ->select([
                'reports.id',
                'reports.reporter_id',
                'reports.job_posting_id',
                'reports.company_id',
                'reports.reason',
                'reports.status',
                'reports.created_at',
            ])
            ->latest();

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($subjectFilter === 'job') {
            $query->whereNotNull('job_posting_id');
        } elseif ($subjectFilter === 'company') {
            $query->whereNotNull('company_id');
        }

        if ($reasonFilter !== 'all') {
            $query->where('reason', $reasonFilter);
        }

        $reports = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/reports/index', [
            'reports' => $reports->through(fn (Report $report): array => [
                'id' => $report->id,
                'subject_type' => $report->job_posting_id !== null ? 'job' : 'company',
                'subject_title' => $report->job_posting_id !== null
                    ? $report->jobPosting?->title
                    : $report->company?->name,
                'subject_status' => $report->job_posting_id !== null
                    ? $report->jobPosting?->status
                    : ($report->company?->is_active ? 'active' : 'inactive'),
                'reason' => $report->reason->value,
                'status' => $report->status->value,
                'created_at' => $report->created_at?->toIso8601String(),
                'reporter' => [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->name,
                ],
            ]),
            'filters' => [
                'status' => $statusFilter,
                'subject' => $subjectFilter,
                'reason' => $reasonFilter,
            ],
        ]);
    }

    public function show(Report $report): Response
    {
        $this->authorize('view', $report);

        $report->load('reporter:id,name,email');

        if ($report->job_posting_id !== null) {
            $report->load([
                'jobPosting:id,company_id,title,status',
                'jobPosting.company:id,name,slug,owner_user_id,is_active',
                'jobPosting.company.owner:id,name,email',
            ]);
        } else {
            $report->load([
                'company:id,name,slug,owner_user_id,is_active',
                'company.owner:id,name,email',
            ]);
        }

        $subject = $report->job_posting_id !== null
            ? [
                'type' => 'job',
                'id' => $report->jobPosting->id,
                'title' => $report->jobPosting->title,
                'status' => $report->jobPosting->status,
                'company' => $report->jobPosting->company ? [
                    'id' => $report->jobPosting->company->id,
                    'name' => $report->jobPosting->company->name,
                    'is_active' => $report->jobPosting->company->is_active,
                    'owner' => $report->jobPosting->company->owner ? [
                        'id' => $report->jobPosting->company->owner->id,
                        'name' => $report->jobPosting->company->owner->name,
                        'email' => $report->jobPosting->company->owner->email,
                    ] : null,
                ] : null,
            ]
            : [
                'type' => 'company',
                'id' => $report->company?->id,
                'title' => $report->company?->name,
                'status' => $report->company?->is_active ? 'active' : 'inactive',
                'owner' => $report->company?->owner ? [
                    'id' => $report->company->owner->id,
                    'name' => $report->company->owner->name,
                    'email' => $report->company->owner->email,
                ] : null,
            ];

        return Inertia::render('admin/reports/show', [
            'report' => [
                'id' => $report->id,
                'status' => $report->status->value,
                'reason' => $report->reason->value,
                'explanation' => $report->explanation,
                'resolution_action' => $report->resolution_action?->value,
                'resolution_message' => $report->resolution_message,
                'internal_note' => $report->internal_note,
                'resolved_at' => $report->resolved_at?->toIso8601String(),
                'created_at' => $report->created_at?->toIso8601String(),
                'reporter' => [
                    'id' => $report->reporter->id,
                    'name' => $report->reporter->name,
                    'email' => $report->reporter->email,
                ],
                'subject' => $subject,
            ],
        ]);
    }

    public function investigate(Report $report): RedirectResponse
    {
        $this->authorize('investigate', $report);

        if (! $report->status->isOpen()) {
            return back()->with('error', __('Only pending reports can be investigated.'));
        }

        if ($report->status !== ReportStatus::Pending) {
            return back()->with('error', __('This report is already under investigation.'));
        }

        $report->status = ReportStatus::Investigating;
        $report->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Report marked as under investigation.'),
        ]);

        return back();
    }

    public function resolve(ResolveReportRequest $request, Report $report, NotificationService $notificationService): RedirectResponse
    {
        $this->authorize('resolve', $report);

        if (! $report->status->isOpen()) {
            return back()->with('error', __('Only pending or investigating reports can be resolved.'));
        }

        /** @var User $user */
        $user = $request->user();

        $report = DB::transaction(function () use ($request, $report, $user): Report {
            $report->status = ReportStatus::Resolved;
            $report->resolution_action = $request->validated('resolution_action');
            $report->resolution_message = $request->validated('resolution_message');
            $report->internal_note = $request->validated('internal_note');
            $report->resolved_by_id = $user->id;
            $report->resolved_at = now();
            $report->open_marker = null;
            $report->save();

            return $report;
        });

        $notificationService->reportOutcome($report);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Report resolved.'),
        ]);

        return to_route('admin.reports.index');
    }

    public function dismiss(DismissReportRequest $request, Report $report, NotificationService $notificationService): RedirectResponse
    {
        $this->authorize('dismiss', $report);

        if (! $report->status->isOpen()) {
            return back()->with('error', __('Only pending or investigating reports can be dismissed.'));
        }

        /** @var User $user */
        $user = $request->user();

        $report = DB::transaction(function () use ($request, $report, $user): Report {
            $report->status = ReportStatus::Dismissed;
            $report->internal_note = $request->validated('internal_note');
            $report->resolved_by_id = $user->id;
            $report->resolved_at = now();
            $report->open_marker = null;
            $report->save();

            return $report;
        });

        $notificationService->reportOutcome($report);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Report dismissed.'),
        ]);

        return to_route('admin.reports.index');
    }
}
