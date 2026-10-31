<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWorkplaceArrangementRequest;
use App\Http\Requests\Admin\UpdateWorkplaceArrangementRequest;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkplaceArrangementController extends Controller
{
    /**
     * Display a paginated list of workplace arrangements.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkplaceArrangement::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $workplaceArrangements = WorkplaceArrangement::query()
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('workplace_arrangements.name', 'like', $prefix)
                        ->orWhere('workplace_arrangements.slug', 'like', $prefix);
                });
            })
            ->orderBy('workplace_arrangements.sort_order')
            ->orderBy('workplace_arrangements.name')
            ->orderBy('workplace_arrangements.id')
            ->paginate(20)
            ->withQueryString();

        $workplaceArrangements->through(fn (WorkplaceArrangement $workplaceArrangement): array => [
            'id' => $workplaceArrangement->id,
            'name' => $workplaceArrangement->name,
            'slug' => $workplaceArrangement->slug,
            'description' => $workplaceArrangement->description,
            'sort_order' => $workplaceArrangement->sort_order,
            'is_active' => $workplaceArrangement->is_active,
        ]);

        return Inertia::render('admin/workplace-arrangements/index', [
            'workplaceArrangements' => $workplaceArrangements,
            'filters' => ['search' => $search],
            'canManageWorkplaceArrangements' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::WorkplaceArrangementsManage),
        ]);
    }

    /**
     * Store a newly created workplace arrangement.
     */
    public function store(StoreWorkplaceArrangementRequest $request): RedirectResponse
    {
        $this->authorize('create', WorkplaceArrangement::class);

        WorkplaceArrangement::create($request->workplaceArrangementData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Workplace arrangement created.'),
        ]);

        return to_route('admin.workplace-arrangements.index');
    }

    /**
     * Update the specified workplace arrangement.
     */
    public function update(UpdateWorkplaceArrangementRequest $request, string $workplaceArrangement): RedirectResponse
    {
        $workplaceArrangement = WorkplaceArrangement::findOrFail($workplaceArrangement);

        $this->authorize('update', $workplaceArrangement);

        $workplaceArrangement->update($request->workplaceArrangementData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Workplace arrangement updated.'),
        ]);

        return to_route('admin.workplace-arrangements.index');
    }

    /**
     * Remove the specified workplace arrangement.
     */
    public function destroy(string $workplaceArrangement): RedirectResponse
    {
        $workplaceArrangement = WorkplaceArrangement::findOrFail($workplaceArrangement);

        $this->authorize('delete', $workplaceArrangement);

        $workplaceArrangement->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Workplace arrangement deleted.'),
        ]);

        return to_route('admin.workplace-arrangements.index');
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
