<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExperienceLevelRequest;
use App\Http\Requests\Admin\UpdateExperienceLevelRequest;
use App\Models\ExperienceLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExperienceLevelController extends Controller
{
    /**
     * Display a paginated list of experience levels.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ExperienceLevel::class);

        $actor = $request->user();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $prefix = $search === '' ? null : $this->escapeLike($search).'%';

        $experienceLevels = ExperienceLevel::query()
            ->when($prefix !== null, function (Builder $query) use ($prefix): void {
                $query->where(function (Builder $query) use ($prefix): void {
                    $query->where('experience_levels.name', 'like', $prefix)
                        ->orWhere('experience_levels.slug', 'like', $prefix);
                });
            })
            ->orderBy('experience_levels.sort_order')
            ->orderBy('experience_levels.name')
            ->orderBy('experience_levels.id')
            ->paginate(20)
            ->withQueryString();

        $experienceLevels->through(fn (ExperienceLevel $experienceLevel): array => [
            'id' => $experienceLevel->id,
            'name' => $experienceLevel->name,
            'slug' => $experienceLevel->slug,
            'description' => $experienceLevel->description,
            'sort_order' => $experienceLevel->sort_order,
            'is_active' => $experienceLevel->is_active,
        ]);

        return Inertia::render('admin/experience-levels/index', [
            'experienceLevels' => $experienceLevels,
            'filters' => ['search' => $search],
            'canManageExperienceLevels' => $actor instanceof User
                && $actor->hasPermission(PermissionSlug::ExperienceLevelsManage),
        ]);
    }

    /**
     * Store a newly created experience level.
     */
    public function store(StoreExperienceLevelRequest $request): RedirectResponse
    {
        $this->authorize('create', ExperienceLevel::class);

        ExperienceLevel::create($request->experienceLevelData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Experience level created.'),
        ]);

        return to_route('admin.experience-levels.index');
    }

    /**
     * Update the specified experience level.
     */
    public function update(UpdateExperienceLevelRequest $request, string $experienceLevel): RedirectResponse
    {
        $experienceLevel = ExperienceLevel::findOrFail($experienceLevel);

        $this->authorize('update', $experienceLevel);

        $experienceLevel->update($request->experienceLevelData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Experience level updated.'),
        ]);

        return to_route('admin.experience-levels.index');
    }

    /**
     * Remove the specified experience level.
     */
    public function destroy(string $experienceLevel): RedirectResponse
    {
        $experienceLevel = ExperienceLevel::findOrFail($experienceLevel);

        $this->authorize('delete', $experienceLevel);

        $experienceLevel->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Experience level deleted.'),
        ]);

        return to_route('admin.experience-levels.index');
    }

    /**
     * Escape LIKE wildcards so the search term is matched literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
