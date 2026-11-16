<?php

namespace App\Http\Requests\Employer;

use App\Enums\JobApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query-string filters of the recruitment pipeline board.
 *
 * The request only carries read filters; it never writes. Resource-level
 * authorization is performed by the controller through
 * CompanyPolicy::viewPipeline().
 *
 * Filter values are normalised rather than rejected: the board is a GET page, so
 * a validation failure would redirect back to the very same URL and could loop.
 * Out-of-range values are therefore clamped server-side, which still guarantees
 * the client can never render more than the maximum number of cards per column.
 */
class RecruitmentPipelineRequest extends FormRequest
{
    /**
     * The number of cards rendered per column when no explicit limit is given.
     */
    public const DEFAULT_COLUMN_LIMIT = 25;

    /**
     * The hard maximum number of cards that may be rendered per column.
     */
    public const MAX_COLUMN_LIMIT = 100;

    /**
     * The maximum length of the candidate-name search term.
     */
    public const MAX_SEARCH_LENGTH = 100;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The board is a company-scoped read surface, so the actual permission check
     * lives in the controller's CompanyPolicy::viewPipeline() call.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company' => ['nullable', 'integer', 'min:1'],
            'job' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:'.self::MAX_SEARCH_LENGTH],
            'limit' => ['nullable', 'array'],
        ];
    }

    /**
     * Normalise the filter values before they are validated.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'company' => $this->normalizeId($this->input('company')),
            'job' => $this->normalizeId($this->input('job')),
            'q' => $this->normalizeSearch($this->input('q')),
            'limit' => is_array($this->input('limit')) ? $this->input('limit') : null,
        ]);
    }

    /**
     * Resolve the per-column card limits, falling back to the default limit.
     *
     * Values are clamped to the allowed range, so a client can never render more
     * than the maximum number of cards per column.
     *
     * @return array<string, int>
     */
    public function columnLimits(): array
    {
        $requested = $this->input('limit');
        $requested = is_array($requested) ? $requested : [];

        $limits = [];

        foreach (JobApplicationStatus::cases() as $status) {
            $value = $requested[$status->value] ?? null;

            $limits[$status->value] = is_numeric($value)
                ? min(self::MAX_COLUMN_LIMIT, max(1, (int) $value))
                : self::DEFAULT_COLUMN_LIMIT;
        }

        return $limits;
    }

    /**
     * The requested company id, if any.
     */
    public function companyId(): ?int
    {
        $company = $this->validated('company');

        return is_numeric($company) ? (int) $company : null;
    }

    /**
     * The requested job id, if any.
     */
    public function jobId(): ?int
    {
        $job = $this->validated('job');

        return is_numeric($job) ? (int) $job : null;
    }

    /**
     * The candidate-name search term, if any.
     */
    public function search(): ?string
    {
        $search = $this->validated('q');

        return is_string($search) && $search !== '' ? $search : null;
    }

    /**
     * Normalise an identifier filter to a positive integer, or null.
     */
    private function normalizeId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * Normalise the search term to a trimmed, length-bounded string.
     */
    private function normalizeSearch(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $search = trim($value);

        if ($search === '') {
            return null;
        }

        return mb_substr($search, 0, self::MAX_SEARCH_LENGTH);
    }
}
