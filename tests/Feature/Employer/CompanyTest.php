<?php

namespace Tests\Feature\Employer;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
        Storage::fake('public');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $company = Company::factory()->create();

        $this->get(route('employer.companies.index'))->assertRedirect(route('login'));
        $this->get(route('employer.companies.create'))->assertRedirect(route('login'));
        $this->get(route('employer.companies.show', $company))->assertRedirect(route('login'));
        $this->get(route('employer.companies.edit', $company))->assertRedirect(route('login'));
    }

    public function test_guest_writes_are_redirected_to_login(): void
    {
        $company = Company::factory()->create();

        $this->post(route('employer.companies.store'), ['name' => 'Acme'])
            ->assertRedirect(route('login'));

        $this->put(route('employer.companies.update', $company), ['name' => 'Acme'])
            ->assertRedirect(route('login'));
    }

    public function test_job_seeker_cannot_access_company_routes(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)->get(route('employer.companies.index'))->assertForbidden();
        $this->actingAs($user)->get(route('employer.companies.create'))->assertForbidden();
        $this->actingAs($user)->get(route('employer.companies.show', $company))->assertForbidden();
        $this->actingAs($user)->get(route('employer.companies.edit', $company))->assertForbidden();
    }

    public function test_job_seeker_cannot_write_companies(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)->post(route('employer.companies.store'), ['name' => 'Acme'])->assertForbidden();
        $this->actingAs($user)->put(route('employer.companies.update', $company), ['name' => 'Acme'])->assertForbidden();
    }

    public function test_non_approved_employer_cannot_access_company_routes(): void
    {
        $user = User::factory()->employer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)->get(route('employer.companies.index'))->assertForbidden();
        $this->actingAs($user)->get(route('employer.companies.create'))->assertForbidden();
        $this->actingAs($user)->get(route('employer.companies.show', $company))->assertForbidden();
        $this->actingAs($user)->get(route('employer.companies.edit', $company))->assertForbidden();
    }

    public function test_pending_and_suspended_employers_cannot_access_company_routes(): void
    {
        foreach ([
            User::factory()->pendingEmployerApplicant()->create(),
            User::factory()->suspendedEmployer()->create(),
        ] as $user) {
            $this->actingAs($user)->get(route('employer.companies.index'))->assertForbidden();
            $this->actingAs($user)->get(route('employer.companies.create'))->assertForbidden();
        }
    }

    public function test_approved_employer_can_create_company(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $response = $this->actingAs($user)->post(route('employer.companies.store'), [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'overview' => 'We build things.',
            'industry' => 'Tech',
            'website' => 'https://acme.example',
            'size_band' => '11-50',
            'location' => 'NYC',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('employer.companies.show', Company::first()));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Company created.',
        ]);

        $this->assertDatabaseHas('companies', [
            'owner_user_id' => $user->id,
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'is_active' => 1,
        ]);
    }

    public function test_forged_owner_user_id_is_ignored(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $this->actingAs($user)->post(route('employer.companies.store'), [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp-forged',
            'owner_user_id' => 999,
            'logo_path' => 'forged/path.png',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'owner_user_id' => $user->id,
            'slug' => 'acme-corp-forged',
        ]);
        $this->assertDatabaseMissing('companies', [
            'owner_user_id' => 999,
        ]);
        $this->assertDatabaseMissing('companies', [
            'logo_path' => 'forged/path.png',
        ]);
    }

    public function test_forged_owner_user_id_is_ignored_on_update(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)->put(route('employer.companies.update', $company), [
            'name' => 'Updated Name',
            'owner_user_id' => 999,
            'logo_path' => 'forged/path.png',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'owner_user_id' => $user->id,
            'name' => 'Updated Name',
        ]);
        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
            'logo_path' => 'forged/path.png',
        ]);
    }

    public function test_validation_errors_prevent_company_creation(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $cases = [
            ['name' => ''],
            ['name' => str_repeat('a', 151)],
            ['name' => 'Acme', 'slug' => str_repeat('a', 101)],
            ['name' => 'Acme', 'slug' => 'bad slug!'],
            ['name' => 'Acme', 'website' => 'not-a-url'],
            ['name' => 'Acme', 'overview' => str_repeat('a', 5001)],
            ['name' => 'Acme', 'industry' => str_repeat('a', 101)],
            ['name' => 'Acme', 'size_band' => str_repeat('a', 51)],
            ['name' => 'Acme', 'location' => str_repeat('a', 256)],
        ];

        foreach ($cases as $payload) {
            $this->actingAs($user)
                ->post(route('employer.companies.store'), $payload)
                ->assertSessionHasErrors();

            $this->assertDatabaseCount('companies', 0);
        }
    }

    public function test_validation_errors_report_expected_fields(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $this->actingAs($user)->post(route('employer.companies.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)->post(route('employer.companies.store'), ['name' => str_repeat('a', 151)])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)->post(route('employer.companies.store'), ['name' => 'Acme', 'slug' => 'bad slug!'])
            ->assertSessionHasErrors('slug');

        $this->actingAs($user)->post(route('employer.companies.store'), ['name' => 'Acme', 'website' => 'not-a-url'])
            ->assertSessionHasErrors('website');
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        Company::factory()->create(['slug' => 'taken']);

        $this->actingAs($user)
            ->post(route('employer.companies.store'), [
                'name' => 'Another',
                'slug' => 'taken',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseCount('companies', 1);
    }

    public function test_duplicate_slug_is_rejected_on_update_ignoring_current(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id, 'slug' => 'mine']);
        Company::factory()->create(['slug' => 'taken']);

        $this->actingAs($user)
            ->put(route('employer.companies.update', $company), [
                'name' => 'Mine',
                'slug' => 'taken',
            ])
            ->assertSessionHasErrors('slug');

        $this->actingAs($user)
            ->put(route('employer.companies.update', $company), [
                'name' => 'Mine',
                'slug' => 'mine',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_logo_validation_rejects_invalid_mime_and_oversize(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $this->actingAs($user)
            ->post(route('employer.companies.store'), [
                'name' => 'Acme',
                'logo' => UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertDatabaseCount('companies', 0);

        $this->actingAs($user)
            ->post(route('employer.companies.store'), [
                'name' => 'Acme',
                'logo' => UploadedFile::fake()->image('big.jpg')->size(3000),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_valid_logo_is_accepted_and_stored(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        foreach (['jpg', 'png', 'webp'] as $extension) {
            $companyCountBefore = Company::count();
            $logo = UploadedFile::fake()->image("logo.{$extension}", 10, 10);

            $this->actingAs($user)
                ->post(route('employer.companies.store'), [
                    'name' => "Acme {$extension}",
                    'logo' => $logo,
                ])
                ->assertSessionHasNoErrors();

            $company = Company::latest('id')->firstOrFail();
            $this->assertNotNull($company->logo_path);
            Storage::disk('public')->assertExists($company->logo_path);
            $this->assertSame($companyCountBefore + 1, Company::count());
        }
    }

    public function test_logo_replacement_and_removal_manage_files(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $logoA = UploadedFile::fake()->image('a.jpg', 10, 10);
        $this->actingAs($user)->post(route('employer.companies.store'), [
            'name' => 'Acme',
            'logo' => $logoA,
        ])->assertSessionHasNoErrors();

        $company = Company::firstOrFail();
        $oldPath = $company->logo_path;
        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);

        $logoB = UploadedFile::fake()->image('b.png', 10, 10);
        $this->actingAs($user)->put(route('employer.companies.update', $company), [
            'name' => 'Acme',
            'logo' => $logoB,
        ])->assertSessionHasNoErrors();

        $company->refresh();
        $newPath = $company->logo_path;
        $this->assertNotNull($newPath);
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);

        $this->actingAs($user)->put(route('employer.companies.update', $company), [
            'name' => 'Acme',
            'remove_logo' => true,
        ])->assertSessionHasNoErrors();

        $company->refresh();
        $this->assertNull($company->logo_path);
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_invalid_logo_on_update_leaves_existing_logo_intact(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $logo = UploadedFile::fake()->image('logo.jpg', 10, 10);
        $this->actingAs($user)->post(route('employer.companies.store'), [
            'name' => 'Acme',
            'logo' => $logo,
        ])->assertSessionHasNoErrors();

        $company = Company::firstOrFail();
        $existingPath = $company->logo_path;
        Storage::disk('public')->assertExists($existingPath);

        $this->actingAs($user)->put(route('employer.companies.update', $company), [
            'name' => 'Acme',
            'logo' => UploadedFile::fake()->create('doc.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('logo');

        $company->refresh();
        $this->assertSame($existingPath, $company->logo_path);
        Storage::disk('public')->assertExists($existingPath);

        $this->actingAs($user)->put(route('employer.companies.update', $company), [
            'name' => 'Acme',
            'logo' => UploadedFile::fake()->image('big.jpg')->size(3000),
        ])->assertSessionHasErrors('logo');

        $company->refresh();
        $this->assertSame($existingPath, $company->logo_path);
        Storage::disk('public')->assertExists($existingPath);
    }

    public function test_owner_can_update_company(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $response = $this->actingAs($user)->put(route('employer.companies.update', $company), [
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'is_active' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('employer.companies.show', $company));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Company updated.',
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'is_active' => 0,
        ]);
    }

    public function test_owner_can_view_and_edit_company(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('employer.companies.show', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/companies/show')
                ->where('company.id', $company->id)
            );

        $this->actingAs($user)
            ->get(route('employer.companies.edit', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/companies/edit')
                ->where('company.id', $company->id)
            );
    }

    public function test_non_owner_cannot_access_another_company(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $other = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->actingAs($other)->get(route('employer.companies.show', $company))->assertForbidden();
        $this->actingAs($other)->get(route('employer.companies.edit', $company))->assertForbidden();
        $this->actingAs($other)->put(route('employer.companies.update', $company), [
            'name' => 'Hacked',
        ])->assertForbidden();

        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
            'name' => 'Hacked',
        ]);
    }

    public function test_id_tampering_returns_forbidden_not_not_found(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $other = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->actingAs($other)->get(route('employer.companies.show', $company->id))->assertForbidden();
        $this->actingAs($other)->get(route('employer.companies.edit', $company->id))->assertForbidden();
        $this->actingAs($other)->put(route('employer.companies.update', $company->id), [
            'name' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_cross_owner_denial_does_not_leak_existence(): void
    {
        $owner = User::factory()->approvedEmployer()->create();
        $other = User::factory()->approvedEmployer()->create();
        $company = Company::factory()->create(['owner_user_id' => $owner->id]);

        $this->actingAs($other)->get(route('employer.companies.show', $company))->assertForbidden();
    }

    public function test_index_lists_only_owned_companies(): void
    {
        $user = User::factory()->approvedEmployer()->create();
        $other = User::factory()->approvedEmployer()->create();

        Company::factory()->create(['owner_user_id' => $user->id, 'name' => 'Mine']);
        Company::factory()->create(['owner_user_id' => $other->id, 'name' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('employer.companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.name', 'Mine')
            );
    }

    public function test_index_paginates_at_fifteen(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        Company::factory()->count(16)->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('employer.companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 15)
                ->where('companies.total', 16)
            );
    }

    public function test_index_empty_state(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $this->actingAs($user)
            ->get(route('employer.companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 0)
            );
    }

    public function test_slug_is_auto_generated_when_omitted(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $this->actingAs($user)->post(route('employer.companies.store'), [
            'name' => 'Acme Corp',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);
    }

    public function test_is_active_defaults_to_true(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $this->actingAs($user)->post(route('employer.companies.store'), [
            'name' => 'Acme Corp',
            'slug' => 'acme-corp-default-active',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'slug' => 'acme-corp-default-active',
            'is_active' => 1,
        ]);
    }
}
