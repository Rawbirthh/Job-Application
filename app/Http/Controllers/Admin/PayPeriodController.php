<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePayPeriodRequest;
use App\Http\Requests\Admin\UpdatePayPeriodRequest;
use App\Models\PayPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayPeriodController extends Controller
{
    /**
     * Display a paginated list of pay periods.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayPeriod::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $payPeriods = PayPeriod::query()
            ->withCount('salaryBands')
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('pay_periods.name', 'like', $prefix)
                        ->orWhere('pay_periods.slug', 'like', $prefix);
                });
            })
            ->orderBy('pay_periods.sort_order')
            ->orderBy('pay_periods.name')
            ->orderBy('pay_periods.id')
            ->paginate(20)
            ->withQueryString();

        $payPeriods->through(fn (PayPeriod $payPeriod): array => [
            'id' => $payPeriod->id,
            'name' => $payPeriod->name,
            'slug' => $payPeriod->slug,
            'description' => $payPeriod->description,
            'sort_order' => $payPeriod->sort_order,
            'is_active' => $payPeriod->is_active,
            'bands_count' => $payPeriod->salary_bands_count,
        ]);

        return Inertia::render('admin/pay-periods/index', [
            'payPeriods' => $payPeriods,
            'filters' => ['search' => $search],
            'canManagePayPeriods' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::PayPeriodsManage),
        ]);
    }

    /**
     * Store a newly created pay period.
     */
    public function store(StorePayPeriodRequest $request): RedirectResponse
    {
        $this->authorize('create', PayPeriod::class);

        PayPeriod::create($request->payPeriodData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pay period created.'),
        ]);

        return to_route('admin.pay-periods.index');
    }

    /**
     * Update the specified pay period.
     */
    public function update(UpdatePayPeriodRequest $request, string $payPeriod): RedirectResponse
    {
        $payPeriod = PayPeriod::findOrFail($payPeriod);

        $this->authorize('update', $payPeriod);

        $payPeriod->update($request->payPeriodData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pay period updated.'),
        ]);

        return to_route('admin.pay-periods.index');
    }

    /**
     * Remove the specified pay period.
     */
    public function destroy(string $payPeriod): RedirectResponse
    {
        $payPeriod = PayPeriod::findOrFail($payPeriod);

        $this->authorize('delete', $payPeriod);

        if ($payPeriod->salaryBands()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Cannot delete pay period because it is referenced by one or more salary bands. Deactivate it instead.'),
            ]);

            return to_route('admin.pay-periods.index');
        }

        $payPeriod->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Pay period deleted.'),
        ]);

        return to_route('admin.pay-periods.index');
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
