<?php

use App\Models\Company;
use App\Models\EmploymentType;
use App\Models\ExperienceLevel;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\JobSkill;
use App\Models\User;
use App\Models\WorkplaceArrangement;
use Database\Seeders\AuthorizationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->withoutVite();

    $this->employer = User::factory()->approvedEmployer()->create();
    $this->company = Company::factory()->create([
        'owner_user_id' => $this->employer->id,
        'is_active' => true,
    ]);
    $this->category = JobCategory::factory()->create();
    $this->employmentType = EmploymentType::factory()->create();
    $this->workplaceArrangement = WorkplaceArrangement::factory()->create();
    $this->experienceLevel = ExperienceLevel::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function jobPayload(array $overrides = []): array
{
    return [
        'title' => 'Senior Backend Engineer',
        'description' => 'We are looking for a backend engineer.',
        'category_id' => null,
        'employment_type_id' => null,
        ...$overrides,
    ];
}

test('an employer can list the skills a role needs', function (): void {
    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => ['PHP', 'Laravel', 'MySQL'],
        ]))
        ->assertRedirect();

    $job = Job::query()->latest('id')->firstOrFail();

    expect($job->skills)->toBe(['PHP', 'Laravel', 'MySQL']);

    $this->assertDatabaseHas('job_skills', [
        'job_posting_id' => $job->id,
        'skill' => 'php',
    ]);
    $this->assertDatabaseHas('job_skills', [
        'job_posting_id' => $job->id,
        'skill' => 'laravel',
    ]);
});

test('a job may be created without any skills', function (): void {
    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
        ]))
        ->assertRedirect();

    $job = Job::query()->latest('id')->firstOrFail();

    expect($job->skills)->toBe([]);

    $this->assertDatabaseCount('job_skills', 0);
});

test('changing the skills rebuilds the canonical index', function (): void {
    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => ['PHP', 'Laravel'],
        ]));

    $job = Job::query()->latest('id')->firstOrFail();

    $this->actingAs($this->employer)
        ->put(route('employer.jobs.update', $job), jobPayload([
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => ['Rust'],
        ]))
        ->assertRedirect(route('employer.jobs.edit', $job));

    $this->assertDatabaseMissing('job_skills', [
        'job_posting_id' => $job->id,
        'skill' => 'php',
    ]);
    $this->assertDatabaseHas('job_skills', [
        'job_posting_id' => $job->id,
        'skill' => 'rust',
    ]);
    $this->assertSame(1, JobSkill::query()->where('job_posting_id', $job->id)->count());
});

test('clearing the skills removes the canonical index', function (): void {
    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => ['PHP'],
        ]));

    $job = Job::query()->latest('id')->firstOrFail();

    $this->actingAs($this->employer)
        ->put(route('employer.jobs.update', $job), jobPayload([
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => [],
        ]))
        ->assertRedirect(route('employer.jobs.edit', $job));

    $this->assertDatabaseCount('job_skills', 0);
});

test('equivalent skill spellings collapse into one canonical entry', function (): void {
    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => ['Node JS', 'nodejs', 'NodeJS'],
        ]));

    $job = Job::query()->latest('id')->firstOrFail();

    $this->assertSame(1, JobSkill::query()->where('job_posting_id', $job->id)->count());
    $this->assertDatabaseHas('job_skills', [
        'job_posting_id' => $job->id,
        'skill' => 'node.js',
    ]);
});

test('the skill list is validated', function (): void {
    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => array_fill(0, 31, 'PHP'),
        ]))
        ->assertSessionHasErrors('skills');

    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => [str_repeat('a', 51)],
        ]))
        ->assertSessionHasErrors('skills.0');
});

test('the job editor exposes the stored skills', function (): void {
    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => ['PHP', 'Laravel'],
        ]));

    $job = Job::query()->latest('id')->firstOrFail();

    $this->actingAs($this->employer)
        ->get(route('employer.jobs.edit', $job))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('employer/jobs/edit')
            ->where('job.skills', ['PHP', 'Laravel'])
        );
});

test('another employer cannot add skills to a job they do not own', function (): void {
    $this->actingAs($this->employer)
        ->post(route('employer.jobs.store'), jobPayload([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => ['PHP'],
        ]));

    $job = Job::query()->latest('id')->firstOrFail();

    $otherEmployer = User::factory()->approvedEmployer()->create();
    $otherCompany = Company::factory()->create([
        'owner_user_id' => $otherEmployer->id,
        'is_active' => true,
    ]);

    $this->actingAs($otherEmployer)
        ->put(route('employer.jobs.update', $job), jobPayload([
            'company_id' => $otherCompany->id,
            'category_id' => $this->category->id,
            'employment_type_id' => $this->employmentType->id,
            'skills' => ['Rust'],
        ]))
        ->assertForbidden();

    $this->assertDatabaseHas('job_skills', [
        'job_posting_id' => $job->id,
        'skill' => 'php',
    ]);
    $this->assertDatabaseMissing('job_skills', [
        'job_posting_id' => $job->id,
        'skill' => 'rust',
    ]);
});
