<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSalaryBandRequest;
use App\Http\Requests\Admin\UpdateSalaryBandRequest;
use App\Models\PayPeriod;
use App\Models\SalaryBand;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryBandController extends Controller
{
    /**
     * Display a paginated list of salary bands.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SalaryBand::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'pay_period_id' => ['nullable', 'integer', 'exists:pay_periods,id'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $payPeriodId = $validated['pay_period_id'] ?? null;
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $salaryBands = SalaryBand::query()
            ->with('payPeriod:id,name,slug,is_active')
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('salary_bands.label', 'like', $prefix)
                        ->orWhere('salary_bands.currency', 'like', $prefix);
                });
            })
            ->when($payPeriodId !== null, function (Builder $query) use ($payPeriodId): void {
                $query->where('salary_bands.pay_period_id', $payPeriodId);
            })
            ->orderBy('salary_bands.currency')
            ->orderBy('salary_bands.pay_period_id')
            ->orderBy('salary_bands.lower_bound')
            ->orderBy('salary_bands.id')
            ->paginate(20)
            ->withQueryString();

        $salaryBands->through(fn (SalaryBand $band): array => [
            'id' => $band->id,
            'label' => $band->label,
            'currency' => $band->currency,
            'lower_bound' => (string) $band->lower_bound,
            'upper_bound' => (string) $band->upper_bound,
            'is_active' => $band->is_active,
            'pay_period' => $band->payPeriod ? [
                'id' => $band->payPeriod->id,
                'name' => $band->payPeriod->name,
                'slug' => $band->payPeriod->slug,
                'is_active' => $band->payPeriod->is_active,
            ] : null,
        ]);

        // Pay period options for the form/filter
        $payPeriods = PayPeriod::query()
            ->select(['id', 'name', 'slug', 'is_active', 'sort_order'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (PayPeriod $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'is_active' => $p->is_active,
            ])->all();

        return Inertia::render('admin/salary-bands/index', [
            'salaryBands' => $salaryBands,
            'payPeriods' => $payPeriods,
            'filters' => [
                'search' => $search,
                'pay_period_id' => $payPeriodId,
            ],
            'canManageSalaryBands' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::SalaryBandsManage),
        ]);
    }

    /**
     * Store a newly created salary band.
     */
    public function store(StoreSalaryBandRequest $request): RedirectResponse
    {
        $this->authorize('create', SalaryBand::class);

        SalaryBand::create($request->salaryBandData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Salary band created.'),
        ]);

        return to_route('admin.salary-bands.index');
    }

    /**
     * Update the specified salary band.
     */
    public function update(UpdateSalaryBandRequest $request, string $salaryBand): RedirectResponse
    {
        $salaryBand = SalaryBand::findOrFail($salaryBand);

        $this->authorize('update', $salaryBand);

        $salaryBand->update($request->salaryBandData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Salary band updated.'),
        ]);

        return to_route('admin.salary-bands.index');
    }

    /**
     * Remove the specified salary band.
     */
    public function destroy(string $salaryBand): RedirectResponse
    {
        $salaryBand = SalaryBand::findOrFail($salaryBand);

        $this->authorize('delete', $salaryBand);

        $salaryBand->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Salary band deleted.'),
        ]);

        return to_route('admin.salary-bands.index');
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
