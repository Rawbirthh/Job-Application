<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmploymentTypeRequest;
use App\Http\Requests\Admin\UpdateEmploymentTypeRequest;
use App\Models\EmploymentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmploymentTypeController extends Controller
{
    /**
     * Display a paginated list of employment types.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EmploymentType::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $employmentTypes = EmploymentType::query()
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('employment_types.name', 'like', $prefix)
                        ->orWhere('employment_types.slug', 'like', $prefix);
                });
            })
            ->orderBy('employment_types.name')
            ->orderBy('employment_types.id')
            ->paginate(20)
            ->withQueryString();

        $employmentTypes->through(fn (EmploymentType $employmentType): array => [
            'id' => $employmentType->id,
            'name' => $employmentType->name,
            'slug' => $employmentType->slug,
            'description' => $employmentType->description,
            'is_active' => $employmentType->is_active,
        ]);

        return Inertia::render('admin/employment-types/index', [
            'employmentTypes' => $employmentTypes,
            'filters' => ['search' => $search],
            'canManageEmploymentTypes' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::EmploymentTypesManage),
        ]);
    }

    /**
     * Store a newly created employment type.
     */
    public function store(StoreEmploymentTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', EmploymentType::class);

        EmploymentType::create($request->employmentTypeData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employment type created.'),
        ]);

        return to_route('admin.employment-types.index');
    }

    /**
     * Update the specified employment type.
     */
    public function update(UpdateEmploymentTypeRequest $request, string $employmentType): RedirectResponse
    {
        $employmentType = EmploymentType::findOrFail($employmentType);

        $this->authorize('update', $employmentType);

        $employmentType->update($request->employmentTypeData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employment type updated.'),
        ]);

        return to_route('admin.employment-types.index');
    }

    /**
     * Remove the specified employment type.
     */
    public function destroy(string $employmentType): RedirectResponse
    {
        $employmentType = EmploymentType::findOrFail($employmentType);

        $this->authorize('delete', $employmentType);

        $employmentType->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Employment type deleted.'),
        ]);

        return to_route('admin.employment-types.index');
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
