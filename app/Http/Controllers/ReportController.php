<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\StoreReportRequest;
use App\Models\Report;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Store a new report against a publicly visible job or an active employer.
     */
    public function store(StoreReportRequest $request, NotificationService $notificationService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $subject = $request->validated('subject');

        $explanation = $request->validated('explanation');

        if (! is_string($explanation)) {
            $explanation = null;
        }

        $attributes = [
            'reporter_id' => $user->id,
            'job_posting_id' => null,
            'company_id' => null,
            'reason' => (string) $request->validated('reason'),
            'explanation' => $explanation,
            'status' => ReportStatus::Pending->value,
        ];

        if ($subject === 'job') {
            $attributes['job_posting_id'] = (int) $request->validated('job_posting_id');
        } else {
            $attributes['company_id'] = (int) $request->validated('company_id');
        }

        $attributes['subject_key'] = Report::subjectKeyFor(
            $attributes['job_posting_id'],
            $attributes['company_id'],
        );

        $report = $this->createReport($attributes);

        $notificationService->reportSubmitted($report);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Report submitted for review.'),
        ]);

        return back();
    }

    /**
     * Insert the report behind a transactional, row-locked duplicate probe.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createReport(array $attributes): Report
    {
        try {
            return DB::transaction(function () use ($attributes): Report {
                $duplicate = Report::query()
                    ->select('id')
                    ->where('reporter_id', $attributes['reporter_id'])
                    ->where('job_posting_id', $attributes['job_posting_id'])
                    ->where('company_id', $attributes['company_id'])
                    ->open()
                    ->lockForUpdate()
                    ->first();

                if ($duplicate !== null) {
                    throw ValidationException::withMessages([
                        'report' => __('You already have an open report for this subject.'),
                    ]);
                }

                return Report::query()->create($attributes);
            });
        } catch (QueryException $e) {
            // The reports_open_dedupe unique index is the DB-level backstop for
            // concurrent submissions racing past the row-locked probe.
            if (! str_contains($e->getMessage(), 'reports_open_dedupe') && ! str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'report' => __('You already have an open report for this subject.'),
            ]);
        }
    }
}
