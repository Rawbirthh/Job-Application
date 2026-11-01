<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreCompanyRequest;
use App\Http\Requests\Employer\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Company::class);

        /** @var User $user */
        $user = request()->user();

        $companies = Company::query()
            ->forOwner($user)
            ->select(['id', 'name', 'slug', 'is_active', 'logo_path', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        $companies->through(fn (Company $company): array => [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'is_active' => $company->is_active,
            'logo_url' => $company->logo_url,
            'created_at' => $company->created_at?->toIso8601String(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ]);

        return Inertia::render('employer/companies/index', [
            'companies' => $companies,
            'canCreate' => true,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Company::class);

        return Inertia::render('employer/companies/create');
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $this->authorize('create', Company::class);

        /** @var User $user */
        $user = $request->user();

        $data = $request->companyData();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug((string) $data['name']);
        }

        $company = new Company($data);
        $company->owner_user_id = $user->id;

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('companies/logos', 'public');
            if ($path !== false) {
                $company->logo_path = $path;
            }
        }

        $company->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Company created.'),
        ]);

        return to_route('employer.companies.show', $company);
    }

    public function show(Company $company): Response
    {
        $this->authorize('view', $company);

        return Inertia::render('employer/companies/show', [
            'company' => $this->companyPayload($company),
            'isOwner' => true,
        ]);
    }

    public function edit(Company $company): Response
    {
        $this->authorize('update', $company);

        return Inertia::render('employer/companies/edit', [
            'company' => $this->companyPayload($company),
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $data = $request->companyData();

        // Keep slug stable when not explicitly provided.
        if (empty($data['slug'])) {
            unset($data['slug']);
        }

        if ($request->boolean('remove_logo')) {
            if ($company->logo_path !== null) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $company->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo_path !== null && ! $request->boolean('remove_logo')) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $path = $request->file('logo')->store('companies/logos', 'public');
            if ($path !== false) {
                $company->logo_path = $path;
            }
        }

        $company->fill($data);
        $company->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Company updated.'),
        ]);

        return to_route('employer.companies.show', $company);
    }

    /**
     * @return array<string, mixed>
     */
    private function companyPayload(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'overview' => $company->overview,
            'industry' => $company->industry,
            'website' => $company->website,
            'size_band' => $company->size_band,
            'location' => $company->location,
            'logo_path' => $company->logo_path,
            'logo_url' => $company->logo_url,
            'is_active' => $company->is_active,
            'created_at' => $company->created_at?->toIso8601String(),
            'updated_at' => $company->updated_at?->toIso8601String(),
        ];
    }
}
