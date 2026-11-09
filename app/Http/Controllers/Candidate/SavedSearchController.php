<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreSavedSearchRequest;
use App\Http\Requests\Candidate\UpdateSavedSearchRequest;
use App\Models\EmploymentType;
use App\Models\ExperienceLevel;
use App\Models\JobCategory;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a paginated listing of the candidate's saved searches.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', SavedSearch::class);

        /** @var User $user */
        $user = request()->user();

        $savedSearches = SavedSearch::query()
            ->where('user_id', $user->id)
            ->with([
                'category:id,name',
                'employmentType:id,name',
                'workplaceArrangement:id,name',
                'experienceLevel:id,name',
            ])
            ->select([
                'id',
                'name',
                'search',
                'category_id',
                'employment_type_id',
                'workplace_arrangement_id',
                'experience_level_id',
                'alerts_enabled',
                'alert_frequency',
                'last_alerted_at',
                'created_at',
            ])
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(function (SavedSearch $savedSearch): array {
                return [
                    'id' => $savedSearch->id,
                    'name' => $savedSearch->name,
                    'search' => $savedSearch->search,
                    'alerts_enabled' => $savedSearch->alerts_enabled,
                    'alert_frequency' => $savedSearch->alert_frequency->value,
                    'last_alerted_at' => $savedSearch->last_alerted_at?->toIso8601String(),
                    'created_at' => $savedSearch->created_at?->toIso8601String(),
                    'category' => $savedSearch->category ? [
                        'id' => $savedSearch->category->id,
                        'name' => $savedSearch->category->name,
                    ] : null,
                    'employment_type' => $savedSearch->employmentType ? [
                        'id' => $savedSearch->employmentType->id,
                        'name' => $savedSearch->employmentType->name,
                    ] : null,
                    'workplace_arrangement' => $savedSearch->workplaceArrangement ? [
                        'id' => $savedSearch->workplaceArrangement->id,
                        'name' => $savedSearch->workplaceArrangement->name,
                    ] : null,
                    'experience_level' => $savedSearch->experienceLevel ? [
                        'id' => $savedSearch->experienceLevel->id,
                        'name' => $savedSearch->experienceLevel->name,
                    ] : null,
                    'criteria' => $savedSearch->criteria(),
                ];
            });

        return Inertia::render('candidate/saved-searches/index', [
            'savedSearches' => $savedSearches,
            'filterOptions' => [
                'categories' => JobCategory::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
                'employmentTypes' => EmploymentType::query()->active()->select(['id', 'name'])->orderBy('sort_order')->get(),
                'workplaceArrangements' => WorkplaceArrangement::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
                'experienceLevels' => ExperienceLevel::query()->active()->select(['id', 'name'])->orderBy('sort_order')->get(),
            ],
        ]);
    }

    /**
     * Save the current public search criteria for the candidate.
     */
    public function store(StoreSavedSearchRequest $request): RedirectResponse
    {
        $this->authorize('create', SavedSearch::class);

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $savedSearch = SavedSearch::query()->create([
                'user_id' => $user->id,
                'name' => $request->validated('name'),
                'search' => $request->validated('search'),
                'category_id' => $request->validated('category_id'),
                'employment_type_id' => $request->validated('employment_type_id'),
                'workplace_arrangement_id' => $request->validated('workplace_arrangement_id'),
                'experience_level_id' => $request->validated('experience_level_id'),
                'alerts_enabled' => $request->boolean('alerts_enabled'),
                'alert_frequency' => $request->validated('alert_frequency') ?? 'daily',
            ]);

            $savedSearch->last_alerted_at = now();
            $savedSearch->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Saved search created.'),
        ]);

        return redirect()->route('candidate.saved-searches.index');
    }

    /**
     * Update a saved search for the candidate.
     */
    public function update(UpdateSavedSearchRequest $request, SavedSearch $savedSearch): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $owned = $user->savedSearches()
            ->where('id', $savedSearch->id)
            ->first();

        if ($owned === null) {
            abort(404);
        }

        $this->authorize('update', $owned);

        $owned->update([
            'name' => $request->validated('name'),
            'search' => $request->validated('search'),
            'category_id' => $request->validated('category_id'),
            'employment_type_id' => $request->validated('employment_type_id'),
            'workplace_arrangement_id' => $request->validated('workplace_arrangement_id'),
            'experience_level_id' => $request->validated('experience_level_id'),
            'alerts_enabled' => $request->boolean('alerts_enabled'),
            'alert_frequency' => $request->validated('alert_frequency') ?? 'daily',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Saved search updated.'),
        ]);

        return redirect()->route('candidate.saved-searches.index');
    }

    /**
     * Remove a saved search for the candidate.
     */
    public function destroy(string $savedSearch): RedirectResponse
    {
        /** @var User $user */
        $user = request()->user();

        $owned = SavedSearch::query()
            ->where('user_id', $user->id)
            ->where('id', (int) $savedSearch)
            ->first();

        if ($owned !== null) {
            $this->authorize('delete', $owned);
            $owned->delete();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Saved search deleted.'),
        ]);

        return redirect()->route('candidate.saved-searches.index');
    }
}
