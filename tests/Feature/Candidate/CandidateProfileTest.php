<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class CandidateProfileTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'headline' => 'Senior Software Engineer',
            'summary' => 'Builds things.',
            'location' => 'New York, NY',
            'skills' => ['PHP', 'Laravel'],
            'educations' => [
                [
                    'institution' => 'MIT',
                    'degree' => 'BSc',
                    'field_of_study' => 'Computer Science',
                    'start_date' => '2015-09-01',
                    'end_date' => '2019-06-01',
                    'description' => 'Studied CS.',
                ],
            ],
            'experiences' => [
                [
                    'company_name' => 'Acme Corp',
                    'job_title' => 'Developer',
                    'location' => 'NYC',
                    'start_date' => '2019-07-01',
                    'end_date' => '2022-01-01',
                    'is_current' => false,
                    'description' => 'Built apps.',
                ],
                [
                    'company_name' => 'Beta Inc',
                    'job_title' => 'Senior Developer',
                    'location' => null,
                    'start_date' => '2022-02-01',
                    'end_date' => null,
                    'is_current' => true,
                    'description' => null,
                ],
            ],
        ], $overrides);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('candidate.profile.edit'))->assertRedirect(route('login'));
        $this->put(route('candidate.profile.update'), [])->assertRedirect(route('login'));
    }

    public function test_platform_admin_without_job_seeker_gets_403(): void
    {
        $user = User::factory()->platformAdmin()->create();

        $this->actingAs($user)->get(route('candidate.profile.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('candidate.profile.update'), $this->validPayload())->assertForbidden();
    }

    public function test_job_seeker_can_view_edit_page(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->get(route('candidate.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/profile/edit')
                ->where('profile.headline', null)
                ->where('profile.skills', [])
                ->has('profile.educations', 0)
                ->has('profile.experiences', 0)
            );
    }

    public function test_approved_employer_retaining_job_seeker_can_manage_profile(): void
    {
        $user = User::factory()->approvedEmployer()->create();

        $this->actingAs($user)
            ->get(route('candidate.profile.edit'))
            ->assertOk();

        $this->actingAs($user)
            ->put(route('candidate.profile.update'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('candidate.profile.edit'));
    }

    public function test_job_seeker_can_create_profile_with_all_fields(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $response = $this->actingAs($user)->put(route('candidate.profile.update'), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('candidate.profile.edit'));
        $response->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Profile updated.',
        ]);

        $profile = CandidateProfile::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertDatabaseHas('candidate_profiles', [
            'id' => $profile->id,
            'user_id' => $user->id,
            'headline' => 'Senior Software Engineer',
            'summary' => 'Builds things.',
            'location' => 'New York, NY',
        ]);
        $this->assertSame(['PHP', 'Laravel'], $profile->skills);
        $this->assertDatabaseCount('candidate_educations', 1);
        $this->assertDatabaseCount('candidate_experiences', 2);

        $this->assertDatabaseHas('candidate_educations', [
            'candidate_profile_id' => $profile->id,
            'institution' => 'MIT',
            'degree' => 'BSc',
            'field_of_study' => 'Computer Science',
        ]);
        $this->assertDatabaseHas('candidate_experiences', [
            'candidate_profile_id' => $profile->id,
            'company_name' => 'Acme Corp',
            'job_title' => 'Developer',
            'is_current' => 0,
        ]);
        $this->assertDatabaseHas('candidate_experiences', [
            'candidate_profile_id' => $profile->id,
            'company_name' => 'Beta Inc',
            'job_title' => 'Senior Developer',
            'is_current' => 1,
            'end_date' => null,
        ]);

        $this->actingAs($user)
            ->get(route('candidate.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/profile/edit')
                ->where('profile.headline', 'Senior Software Engineer')
                ->where('profile.summary', 'Builds things.')
                ->where('profile.location', 'New York, NY')
                ->where('profile.skills', ['PHP', 'Laravel'])
                ->has('profile.educations', 1)
                ->where('profile.educations.0.institution', 'MIT')
                ->where('profile.educations.0.degree', 'BSc')
                ->has('profile.experiences', 2)
                ->where('profile.experiences.0.company_name', 'Acme Corp')
                ->where('profile.experiences.1.is_current', true)
                ->where('profile.experiences.1.end_date', null)
            );
    }

    public function test_forged_user_id_is_ignored(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();

        $payload = $this->validPayload();
        $payload['user_id'] = $other->id;
        $payload['id'] = 999;
        $payload['owner_user_id'] = 999;

        $this->actingAs($user)
            ->put(route('candidate.profile.update'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('candidate.profile.edit'));

        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $user->id,
            'headline' => 'Senior Software Engineer',
        ]);
        $this->assertDatabaseMissing('candidate_profiles', [
            'user_id' => $other->id,
        ]);
    }

    public function test_cross_user_data_is_not_visible(): void
    {
        $owner = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();

        $this->actingAs($owner)->put(route('candidate.profile.update'), $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->actingAs($other)
            ->get(route('candidate.profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/profile/edit')
                ->where('profile.headline', null)
                ->where('profile.skills', [])
                ->has('profile.educations', 0)
                ->has('profile.experiences', 0)
            );

        // Updating as the other user must not touch the owner's profile.
        $this->actingAs($other)->put(route('candidate.profile.update'), [
            'headline' => 'Hacked Headline',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('candidate_profiles', [
            'user_id' => $owner->id,
            'headline' => 'Senior Software Engineer',
        ]);
    }

    public function test_update_replaces_entries(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)->put(route('candidate.profile.update'), $this->validPayload())
            ->assertSessionHasNoErrors();

        $profile = CandidateProfile::query()->where('user_id', $user->id)->firstOrFail();
        $firstEducationId = $profile->educations()->firstOrFail()->id;
        $firstExperienceCompany = 'Acme Corp';

        $replacement = [
            'headline' => 'Senior Software Engineer',
            'skills' => ['PHP'],
            'educations' => [
                ['institution' => 'Stanford', 'degree' => 'MSc', 'field_of_study' => null, 'start_date' => null, 'end_date' => null, 'description' => null],
            ],
            'experiences' => [
                ['company_name' => 'Gamma LLC', 'job_title' => 'Lead', 'location' => null, 'start_date' => null, 'end_date' => null, 'is_current' => false, 'description' => null],
            ],
        ];

        $this->actingAs($user)->put(route('candidate.profile.update'), $replacement)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('candidate_educations', ['id' => $firstEducationId]);
        $this->assertDatabaseMissing('candidate_educations', ['institution' => 'MIT']);
        $this->assertDatabaseMissing('candidate_experiences', ['company_name' => $firstExperienceCompany]);

        $profile->refresh();
        $this->assertSame(1, $profile->educations()->count());
        $this->assertSame(1, $profile->experiences()->count());
        $this->assertDatabaseHas('candidate_educations', [
            'candidate_profile_id' => $profile->id,
            'institution' => 'Stanford',
        ]);
        $this->assertDatabaseHas('candidate_experiences', [
            'candidate_profile_id' => $profile->id,
            'company_name' => 'Gamma LLC',
            'job_title' => 'Lead',
        ]);
    }

    public function test_is_current_true_persists_null_end_date(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $payload = $this->validPayload();
        $payload['experiences'] = [
            [
                'company_name' => 'Current Co',
                'job_title' => 'Engineer',
                'location' => null,
                'start_date' => '2023-01-01',
                'end_date' => null,
                'is_current' => true,
                'description' => null,
            ],
        ];

        $this->actingAs($user)->put(route('candidate.profile.update'), $payload)
            ->assertSessionHasNoErrors();

        $profile = CandidateProfile::query()->where('user_id', $user->id)->firstOrFail();
        $experience = $profile->experiences()->firstOrFail();

        $this->assertTrue($experience->is_current);
        $this->assertNull($experience->end_date);
    }

    public function test_validation_errors(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $cases = [
            ['headline' => str_repeat('a', 151)],
            ['summary' => str_repeat('a', 5001)],
            ['location' => str_repeat('a', 256)],
            ['skills' => array_fill(0, 31, 'skill')],
            ['skills' => [str_repeat('a', 51)]],
            ['skills' => [123]],
            ['educations' => array_fill(0, 21, ['institution' => 'Uni'])],
            ['experiences' => array_fill(0, 21, ['company_name' => 'Co', 'job_title' => 'Dev'])],
            ['educations' => [['degree' => 'BSc']]],
            ['experiences' => [['location' => 'NYC']]],
            ['educations' => [['institution' => 'Uni', 'start_date' => '2020-01-02', 'end_date' => '2020-01-01']]],
            ['experiences' => [['company_name' => 'Co', 'job_title' => 'Dev', 'start_date' => '2020-01-02', 'end_date' => '2020-01-01']]],
            ['experiences' => [['company_name' => 'Co', 'job_title' => 'Dev', 'is_current' => true, 'end_date' => '2022-01-01']]],
        ];

        foreach ($cases as $payload) {
            $this->actingAs($user)
                ->put(route('candidate.profile.update'), $payload)
                ->assertSessionHasErrors();
        }
    }

    public function test_validation_errors_report_expected_fields(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)->put(route('candidate.profile.update'), ['headline' => str_repeat('a', 151)])
            ->assertSessionHasErrors('headline');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['summary' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('summary');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['location' => str_repeat('a', 256)])
            ->assertSessionHasErrors('location');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['skills' => array_fill(0, 31, 'skill')])
            ->assertSessionHasErrors('skills');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['skills' => [str_repeat('a', 51)]])
            ->assertSessionHasErrors('skills.0');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['skills' => [123]])
            ->assertSessionHasErrors('skills.0');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['educations' => [['degree' => 'BSc']]])
            ->assertSessionHasErrors('educations.0.institution');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['experiences' => [['location' => 'NYC']]])
            ->assertSessionHasErrors(['experiences.0.company_name', 'experiences.0.job_title']);

        $this->actingAs($user)->put(route('candidate.profile.update'), ['educations' => [['institution' => 'Uni', 'start_date' => '2020-01-02', 'end_date' => '2020-01-01']]])
            ->assertSessionHasErrors('educations.0.end_date');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['experiences' => [['company_name' => 'Co', 'job_title' => 'Dev', 'start_date' => '2020-01-02', 'end_date' => '2020-01-01']]])
            ->assertSessionHasErrors('experiences.0.end_date');

        $this->actingAs($user)->put(route('candidate.profile.update'), ['experiences' => [['company_name' => 'Co', 'job_title' => 'Dev', 'is_current' => true, 'end_date' => '2022-01-01']]])
            ->assertSessionHasErrors('experiences.0.end_date');
    }

    public function test_field_exposure_excludes_sensitive_fields(): void
    {
        $user = User::factory()->withTwoFactor()->jobSeeker()->create();

        $this->actingAs($user)->put(route('candidate.profile.update'), $this->validPayload())
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('candidate.profile.edit'))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->component('candidate/profile/edit');

                $profile = $page->toArray()['props']['profile'];
                $this->assertSame(
                    [
                        'headline',
                        'summary',
                        'location',
                        'skills',
                        'preferred_category_id',
                        'preferred_experience_level_id',
                        'preferred_workplace_arrangement_id',
                        'educations',
                        'experiences',
                        'updated_at',
                    ],
                    array_keys($profile)
                );

                $educationKeys = array_keys($profile['educations'][0]);
                $this->assertSame(
                    ['id', 'institution', 'degree', 'field_of_study', 'start_date', 'end_date', 'description'],
                    $educationKeys
                );

                $experienceKeys = array_keys($profile['experiences'][0]);
                $this->assertSame(
                    ['id', 'company_name', 'job_title', 'location', 'start_date', 'end_date', 'is_current', 'description'],
                    $experienceKeys
                );

                $authUser = $page->toArray()['props']['auth']['user'];
                foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'] as $sensitive) {
                    $this->assertArrayNotHasKey($sensitive, $authUser);
                }
            });
    }
}
