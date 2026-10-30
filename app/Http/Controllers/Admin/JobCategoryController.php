<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJobCategoryRequest;
use App\Http\Requests\Admin\UpdateJobCategoryRequest;
use App\Models\JobCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobCategoryController extends Controller
{
    /**
     * Display a paginated list of job categories.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JobCategory::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $categories = JobCategory::query()
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('job_categories.name', 'like', $prefix)
                        ->orWhere('job_categories.slug', 'like', $prefix);
                });
            })
            ->orderBy('job_categories.name')
            ->orderBy('job_categories.id')
            ->paginate(20)
            ->withQueryString();

        $categories->through(fn (JobCategory $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'is_active' => $category->is_active,
        ]);

        return Inertia::render('admin/categories/index', [
            'categories' => $categories,
            'filters' => ['search' => $search],
            'canManageCategories' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::CategoriesManage),
        ]);
    }

    /**
     * Store a newly created job category.
     */
    public function store(StoreJobCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', JobCategory::class);

        JobCategory::create($request->categoryData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job category created.'),
        ]);

        return to_route('admin.categories.index');
    }

    /**
     * Update the specified job category.
     */
    public function update(UpdateJobCategoryRequest $request, string $category): RedirectResponse
    {
        $category = JobCategory::findOrFail($category);

        $this->authorize('update', $category);

        $category->update($request->categoryData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job category updated.'),
        ]);

        return to_route('admin.categories.index');
    }

    /**
     * Remove the specified job category.
     */
    public function destroy(string $category): RedirectResponse
    {
        $category = JobCategory::findOrFail($category);

        $this->authorize('delete', $category);

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Job category deleted.'),
        ]);

        return to_route('admin.categories.index');
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
