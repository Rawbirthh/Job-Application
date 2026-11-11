<?php

namespace App\Http\Requests;

use App\Enums\EmployerStatus;
use App\Enums\ReportReason;
use App\Models\Company;
use App\Models\Job;
use App\Models\Report;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportRequest extends FormRequest
{
    /**
     * The maximum number of open reports a single reporter may hold.
     */
    private const OPEN_REPORT_CAP = 10;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', Report::class);
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'subject' => is_string($this->subject) ? Str::trim($this->subject) : '',
            'reason' => is_string($this->reason) ? Str::trim($this->reason) : '',
            'explanation' => is_string($this->explanation) ? Str::trim($this->explanation) : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', Rule::in(['job', 'company'])],
            'job_posting_id' => [
                'required_if:subject,job',
                'integer',
                'exists:job_postings,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var Job|null $job */
                    $job = Job::query()->with('company.owner')->find($value);

                    // Mirror the public job visibility gate exactly (A3, IDOR guard):
                    // published job, existing active company, approved owner.
                    if (
                        ! $job ||
                        $job->status !== 'published' ||
                        ! $job->company ||
                        ! $job->company->is_active ||
                        $job->company->owner->status !== EmployerStatus::Approved
                    ) {
                        $fail(__('Only publicly visible jobs can be reported.'));
                    }
                },
            ],
            'company_id' => [
                'required_if:subject,company',
                'integer',
                'exists:companies,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var Company|null $company */
                    $company = Company::query()->find($value);

                    if (! $company || ! $company->is_active) {
                        $fail(__('Only active employers can be reported.'));
                    }
                },
            ],
            'reason' => ['required', Rule::in(ReportReason::values())],
            'explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if (! $user instanceof User) {
                return;
            }

            $subject = $this->input('subject');

            if ($subject === 'job') {
                $duplicateQuery = Report::query()
                    ->open()
                    ->where('reporter_id', $user->id)
                    ->where('job_posting_id', $this->integer('job_posting_id'))
                    ->whereNull('company_id');
            } elseif ($subject === 'company') {
                $duplicateQuery = Report::query()
                    ->open()
                    ->where('reporter_id', $user->id)
                    ->where('company_id', $this->integer('company_id'))
                    ->whereNull('job_posting_id');
            } else {
                return;
            }

            if ($duplicateQuery->exists()) {
                $validator->errors()->add('report', __('You already have an open report for this subject.'));
            }

            $openCount = Report::query()
                ->open()
                ->where('reporter_id', $user->id)
                ->count();

            if ($openCount >= self::OPEN_REPORT_CAP) {
                $validator->errors()->add(
                    'reports',
                    __('You can have up to :count open reports at a time. Wait for one to be reviewed before reporting again.', [
                        'count' => self::OPEN_REPORT_CAP,
                    ])
                );
            }
        });
    }
}
