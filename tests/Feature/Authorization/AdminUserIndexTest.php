<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AdminUserIndexTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAuthorizationCatalogue();
    }

    public function test_admin_user_index_exposes_can_manage_roles_true_for_a_platform_administrator(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $this->actingAs($administrator)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->where('canManageRoles', true),
            );
    }

    public function test_admin_user_index_exposes_can_manage_roles_false_for_a_viewer_without_roles_manage(): void
    {
        $viewerRole = $this->createCustomRoleWithPermissions(
            'user-viewer',
            'User Viewer',
            [PermissionSlug::AdminAccess, PermissionSlug::UsersView],
        );
        $actor = User::factory()->withRole($viewerRole)->create();

        $this->actingAs($actor)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->where('canManageRoles', false),
            );
    }

    public function test_admin_can_search_users_by_name_prefix(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $alice = User::factory()->jobSeeker()->create([
            'name' => 'Alice Anderson',
            'email' => 'alice.anderson@example.com',
        ]);
        User::factory()->jobSeeker()->create([
            'name' => 'Bob Brown',
            'email' => 'bob.brown@example.com',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.users.index', ['search' => 'Ali']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->where('users.total', 1)
                ->where('users.data.0.id', $alice->id)
                ->where('filters.search', 'Ali'),
            );
    }

    public function test_admin_can_search_users_by_email_prefix(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $alice = User::factory()->jobSeeker()->create([
            'name' => 'Zoe Zhang',
            'email' => 'alice.prefix@example.com',
        ]);
        User::factory()->jobSeeker()->create([
            'name' => 'Bob Brown',
            'email' => 'bob.brown@example.com',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.users.index', ['search' => 'alice.prefix']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 1)
                ->where('users.data.0.id', $alice->id)
                ->where('filters.search', 'alice.prefix'),
            );
    }

    public function test_wildcard_search_terms_are_escaped_and_do_not_return_unrelated_users(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        User::factory()->count(3)->jobSeeker()->create();

        foreach (['%', '_', 'a%b', 'a_b'] as $term) {
            $this->actingAs($administrator)
                ->get(route('admin.users.index', ['search' => $term]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('users.total', 0)
                    ->where('filters.search', $term),
                );
        }
    }

    public function test_overlong_search_terms_are_rejected(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($administrator)
            ->from(route('admin.users.index'))
            ->get(route('admin.users.index', ['search' => str_repeat('a', 101)]));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHasErrors('search');
    }

    public function test_admin_user_index_paginates_twenty_users_and_preserves_the_search_query(): void
    {
        $administrator = User::factory()->platformAdmin()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
        ]);

        User::factory()
            ->count(25)
            ->jobSeeker()
            ->sequence(fn (Sequence $sequence): array => [
                'name' => sprintf('Persisted User %02d', $sequence->index + 1),
                'email' => sprintf('persisted%02d@example.com', $sequence->index + 1),
            ])
            ->create();

        $firstPage = $this->actingAs($administrator)
            ->get(route('admin.users.index', ['search' => 'Persisted']));

        $firstPage->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 25)
                ->where('users.per_page', 20)
                ->where('users.current_page', 1)
                ->where('users.last_page', 2)
                ->has('users.data', 20)
                ->where('filters.search', 'Persisted'),
            );

        $firstPagePayload = $firstPage->inertiaPage()['props']['users'];
        $this->assertNotNull($firstPagePayload['next_page_url']);
        $this->assertStringContainsString('search=Persisted', $firstPagePayload['next_page_url']);

        $this->actingAs($administrator)
            ->get(route('admin.users.index', ['search' => 'Persisted', 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 25)
                ->where('users.current_page', 2)
                ->has('users.data', 5),
            );
    }

    public function test_roleless_users_are_rendered_with_an_empty_role_list(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        $roleless = User::factory()->create([
            'name' => 'Roleless User',
            'email' => 'roleless@example.com',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.users.index', ['search' => 'Roleless']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 1)
                ->where('users.data.0.id', $roleless->id)
                ->where('users.data.0.roles', []),
            );
    }

    public function test_admin_user_index_props_only_expose_intended_fields(): void
    {
        $administrator = User::factory()->platformAdmin()->create();
        User::factory()->withTwoFactor()->jobSeeker()->create([
            'name' => 'Sensitive Target',
            'email' => 'sensitive@example.com',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.users.index', ['search' => 'Sensitive']))
            ->assertOk()
            ->assertInertia(function (Assert $page): void {
                $page->component('admin/users/index')
                    ->where('users.total', 1);

                $payload = $page->toArray()['props']['users']['data'][0];

                $this->assertSame(['id', 'name', 'email', 'roles'], array_keys($payload));
                $this->assertSame(['id', 'slug', 'name'], array_keys($payload['roles'][0]));
                $this->assertSame('job_seeker', $payload['roles'][0]['slug']);

                foreach ([
                    'password',
                    'two_factor_secret',
                    'two_factor_recovery_codes',
                    'two_factor_confirmed_at',
                    'remember_token',
                    'email_verified_at',
                ] as $sensitiveField) {
                    $this->assertArrayNotHasKey($sensitiveField, $payload);
                }
            });
    }

    public function test_admin_user_index_does_not_issue_a_query_per_user(): void
    {
        $administrator = User::factory()->platformAdmin()->create();

        User::factory()->count(3)->create();
        $smallPageQueryCount = $this->countQueriesForIndex(User::query()->findOrFail($administrator->id));

        User::factory()->count(12)->create();
        $largePageQueryCount = $this->countQueriesForIndex(User::query()->findOrFail($administrator->id));

        $this->assertSame(
            $smallPageQueryCount,
            $largePageQueryCount,
            'The admin user index query count grew with the number of users, suggesting an N+1 role query.',
        );
    }

    private function countQueriesForIndex(User $administrator): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($administrator)
            ->get(route('admin.users.index'))
            ->assertOk();

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queryCount;
    }
}
