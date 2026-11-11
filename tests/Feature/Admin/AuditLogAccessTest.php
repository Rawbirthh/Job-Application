<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditTargetType;
use App\Enums\PermissionSlug;
use App\Enums\RoleSlug;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    /**
     * The administrator that browses the trail.
     */
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();

        $this->admin = User::factory()->platformAdmin()->create([
            'email' => 'viewer@example.com',
            'name' => 'Audit Viewer',
        ]);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Seed an audit entry directly so the read surface can be tested in isolation.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedEntry(array $attributes = []): AuditLog
    {
        return AuditLog::query()->create(array_merge([
            'actor_id' => null,
            'actor_email' => null,
            'action' => AuditAction::JobApproved->value,
            'target_type' => AuditTargetType::JobPosting->value,
            'target_id' => null,
            'target_label' => null,
            'metadata' => null,
            'ip_address' => null,
            'request_id' => null,
            'occurred_at' => now(),
        ], $attributes));
    }

    /**
     * Read the ids of the entries returned for the given filters.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function resultIds(array $filters): array
    {
        $ids = [];

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', $filters))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids): void {
                $props = $page->toArray()['props'];

                $ids = array_column($props['auditLogs']['data'], 'id');
            });

        return $ids;
    }

    // ------------------------------------------------------------ access gate

    public function test_guests_are_redirected_to_login(): void
    {
        $entry = $this->seedEntry();

        $this->get(route('admin.audit-logs.index'))->assertRedirect(route('login'));
        $this->get(route('admin.audit-logs.show', $entry))->assertRedirect(route('login'));
    }

    public function test_authenticated_job_seekers_and_employers_are_forbidden(): void
    {
        $entry = $this->seedEntry();

        foreach ([User::factory()->jobSeeker()->create(), User::factory()->approvedEmployer()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.audit-logs.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.audit-logs.show', $entry))
                ->assertForbidden();
        }
    }

    public function test_an_admin_without_the_audit_logs_view_permission_is_forbidden(): void
    {
        $role = $this->createCustomRoleWithPermissions(
            'audit_reader',
            'Audit reader',
            [PermissionSlug::AdminAccess->value],
        );

        $user = User::factory()->withRole($role)->create();
        $entry = $this->seedEntry();

        $this->actingAs($user)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.audit-logs.show', $entry))
            ->assertForbidden();
    }

    // ------------------------------------------------------------- list page

    public function test_an_admin_with_the_permission_sees_the_paginated_trail(): void
    {
        $other = User::factory()->platformAdmin()->create(['name' => 'Second Admin']);

        $oldest = $this->seedEntry([
            'action' => AuditAction::JobApproved->value,
            'target_type' => AuditTargetType::JobPosting->value,
            'target_id' => 1,
            'target_label' => 'Alpha Engineer',
            'actor_id' => $this->admin->id,
            'actor_email' => $this->admin->email,
            'occurred_at' => now()->subHours(3),
        ]);

        $middle = $this->seedEntry([
            'action' => AuditAction::JobApproved->value,
            'target_type' => AuditTargetType::JobPosting->value,
            'target_id' => 2,
            'target_label' => 'Beta Engineer',
            'actor_id' => $other->id,
            'actor_email' => $other->email,
            'occurred_at' => now()->subHours(2),
        ]);

        $newest = $this->seedEntry([
            'action' => AuditAction::JobApproved->value,
            'target_type' => AuditTargetType::JobPosting->value,
            'target_id' => 3,
            'target_label' => 'Gamma Engineer',
            'actor_id' => $this->admin->id,
            'actor_email' => $this->admin->email,
            'occurred_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', ['action' => AuditAction::JobApproved->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-logs/index')
                ->where('auditLogs.total', 3)
                ->where('auditLogs.per_page', 25)
                ->where('auditLogs.current_page', 1)
                ->has('auditLogs.data', 3)
                // Newest first.
                ->where('auditLogs.data.0.id', $newest->id)
                ->where('auditLogs.data.1.id', $middle->id)
                ->where('auditLogs.data.2.id', $oldest->id)
                ->where('auditLogs.data.0.action', AuditAction::JobApproved->value)
                ->where('auditLogs.data.0.action_label', 'Job approved')
                ->where('auditLogs.data.0.target_type', AuditTargetType::JobPosting->value)
                ->where('auditLogs.data.0.target_type_label', 'Job posting')
                ->where('auditLogs.data.0.target_id', 3)
                ->where('auditLogs.data.0.target_label', 'Gamma Engineer')
                ->where('auditLogs.data.0.actor.id', $this->admin->id)
                ->where('auditLogs.data.0.actor.name', $this->admin->name)
                ->where('auditLogs.data.0.actor_email', $this->admin->email)
                ->has('auditLogs.data.0.occurred_at')
                // The list payload never carries the metadata blob.
                ->missing('auditLogs.data.0.metadata')
                ->where('filters.action', AuditAction::JobApproved->value)
                ->where('filters.target_type', null)
                ->where('filters.actor_id', null)
                ->where('filters.search', null)
                ->where('filters.from', null)
                ->where('filters.to', null)
                ->has('actions', count(AuditAction::cases()))
                ->where('actions.0.value', AuditAction::EmployerApproved->value)
                ->where('actions.0.label', 'Employer approved')
                ->has('targetTypes', count(AuditTargetType::cases()))
                ->where('targetTypes.0.value', AuditTargetType::User->value)
                ->where('targetTypes.0.label', 'User'),
            );
    }

    // --------------------------------------------------------------- filters

    public function test_each_filter_narrows_the_result_set(): void
    {
        $actorOne = User::factory()->create(['email' => 'searchable-actor@example.com']);
        $actorTwo = User::factory()->create(['email' => 'other-actor@example.com']);

        $oldEntry = $this->seedEntry([
            'action' => AuditAction::JobApproved->value,
            'target_type' => AuditTargetType::JobPosting->value,
            'target_id' => 1,
            'target_label' => 'searchable corp',
            'actor_id' => $actorOne->id,
            'actor_email' => $actorOne->email,
            'occurred_at' => now()->subDays(5),
        ]);

        $middleEntry = $this->seedEntry([
            'action' => AuditAction::JobApproved->value,
            'target_type' => AuditTargetType::User->value,
            'target_id' => 2,
            'target_label' => 'user target',
            'actor_id' => $actorTwo->id,
            'actor_email' => $actorTwo->email,
            'occurred_at' => now()->subDays(3),
        ]);

        $newEntry = $this->seedEntry([
            'action' => AuditAction::ReportResolved->value,
            'target_type' => AuditTargetType::Report->value,
            'target_id' => 3,
            'target_label' => 'report target',
            'actor_id' => $actorOne->id,
            'actor_email' => $actorOne->email,
            'occurred_at' => now()->subDay(),
        ]);

        // The action filter (newest first).
        $this->assertSame(
            [$middleEntry->id, $oldEntry->id],
            $this->resultIds(['action' => AuditAction::JobApproved->value]),
        );

        // The target type filter.
        $this->assertSame(
            [$middleEntry->id],
            $this->resultIds(['target_type' => AuditTargetType::User->value]),
        );

        // The actor filter.
        $this->assertSame(
            [$newEntry->id, $oldEntry->id],
            $this->resultIds(['actor_id' => $actorOne->id]),
        );

        // The date range filter is inclusive on both ends.
        $day = now()->subDays(5)->toDateString();

        $this->assertSame(
            [$oldEntry->id],
            $this->resultIds(['from' => $day, 'to' => $day]),
        );

        // The search filter matches a prefix of the actor email or the target label.
        $this->assertSame(
            [$newEntry->id, $oldEntry->id],
            $this->resultIds(['search' => 'searchable']),
        );

        // The search is prefix-only: a mid-string term does not match.
        $this->assertSame([], $this->resultIds(['search' => 'corp']));
    }

    public function test_unknown_filter_values_are_rejected_and_never_widen_the_result_set(): void
    {
        $this->seedEntry([
            'action' => AuditAction::JobApproved->value,
            'target_type' => AuditTargetType::JobPosting->value,
        ]);

        $entriesBefore = AuditLog::query()->count();

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', ['action' => 'not-a-real-action']))
            ->assertStatus(302)
            ->assertSessionHasErrors('action');

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', ['target_type' => 'not-a-real-target']))
            ->assertStatus(302)
            ->assertSessionHasErrors('target_type');

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', ['from' => 'yesterday', 'to' => 'today']))
            ->assertStatus(302)
            ->assertSessionHasErrors('from');

        // A rejected filter never writes a row and never changes the trail.
        $this->assertSame($entriesBefore, AuditLog::query()->count());
    }

    // ------------------------------------------------------------ pagination

    public function test_the_queue_paginates_at_twenty_five_and_keeps_the_filters(): void
    {
        for ($index = 0; $index < 26; $index++) {
            $this->seedEntry([
                'action' => AuditAction::JobApproved->value,
                'target_type' => AuditTargetType::JobPosting->value,
                'target_label' => "Job {$index}",
                'occurred_at' => now()->subMinutes(60 - $index),
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', ['action' => AuditAction::JobApproved->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auditLogs.total', 26)
                ->where('auditLogs.per_page', 25)
                ->where('auditLogs.current_page', 1)
                ->where('auditLogs.from', 1)
                ->where('auditLogs.to', 25)
                ->has('auditLogs.data', 25)
                ->where('auditLogs.next_page_url', fn (string $url): bool => str_contains($url, 'page=2')
                    && str_contains($url, 'action=job.approved')),
            );

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.index', [
                'action' => AuditAction::JobApproved->value,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auditLogs.total', 26)
                ->where('auditLogs.current_page', 2)
                ->has('auditLogs.data', 1)
                ->where('auditLogs.prev_page_url', fn (string $url): bool => str_contains($url, 'page=1')
                    && str_contains($url, 'action=job.approved')),
            );
    }

    // ------------------------------------------------------------ detail page

    public function test_the_detail_page_exposes_the_metadata_key_value_list(): void
    {
        $entry = $this->seedEntry([
            'action' => AuditAction::EmployerSuspended->value,
            'target_type' => AuditTargetType::User->value,
            'target_id' => 42,
            'target_label' => 'suspended@example.com',
            'actor_id' => $this->admin->id,
            'actor_email' => $this->admin->email,
            'metadata' => [
                'previous_status' => 'approved',
                'reason' => 'Policy violations',
            ],
            'occurred_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.show', $entry))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-logs/show')
                ->where('auditLog.id', $entry->id)
                ->where('auditLog.action', AuditAction::EmployerSuspended->value)
                ->where('auditLog.action_label', 'Employer suspended')
                ->where('auditLog.target_type', AuditTargetType::User->value)
                ->where('auditLog.target_type_label', 'User')
                ->where('auditLog.target_id', 42)
                ->where('auditLog.target_label', 'suspended@example.com')
                ->where('auditLog.actor.id', $this->admin->id)
                ->where('auditLog.actor_email', $this->admin->email)
                ->has('auditLog.metadata', 2)
                ->where('auditLog.metadata.0.key', 'previous_status')
                ->where('auditLog.metadata.0.value', 'approved')
                ->where('auditLog.metadata.1.key', 'reason')
                ->where('auditLog.metadata.1.value', 'Policy violations'),
            );
    }

    public function test_an_unknown_entry_returns_not_found(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.audit-logs.show', 999999))
            ->assertNotFound();
    }

    // ------------------------------------------------------- no mutation surface

    public function test_there_is_no_mutation_surface_for_audit_entries(): void
    {
        $entry = $this->seedEntry();

        $this->assertTrue(Route::has('admin.audit-logs.index'));
        $this->assertTrue(Route::has('admin.audit-logs.show'));
        $this->assertFalse(Route::has('admin.audit-logs.store'));
        $this->assertFalse(Route::has('admin.audit-logs.update'));
        $this->assertFalse(Route::has('admin.audit-logs.destroy'));

        $this->actingAs($this->admin)->post('/admin/audit-logs')->assertStatus(405);
        $this->actingAs($this->admin)->put('/admin/audit-logs')->assertStatus(405);
        $this->actingAs($this->admin)->delete('/admin/audit-logs')->assertStatus(405);

        $this->actingAs($this->admin)->post("/admin/audit-logs/{$entry->id}")->assertStatus(405);
        $this->actingAs($this->admin)->put("/admin/audit-logs/{$entry->id}")->assertStatus(405);
        $this->actingAs($this->admin)->delete("/admin/audit-logs/{$entry->id}")->assertStatus(405);

        $this->assertSame(1, AuditLog::query()->count());
    }

    // --------------------------------------------------- permission catalogue

    public function test_the_audit_logs_view_permission_is_seeded_for_platform_administrators_only(): void
    {
        $permission = Permission::query()
            ->where('slug', PermissionSlug::AuditLogsView->value)
            ->firstOrFail();

        $this->assertTrue($permission->is_system);

        $administratorRole = Role::query()
            ->where('slug', RoleSlug::PlatformAdmin->value)
            ->firstOrFail();

        $this->assertTrue(
            $administratorRole->permissions()
                ->where('permissions.id', $permission->id)
                ->exists(),
        );

        foreach ([RoleSlug::Employer, RoleSlug::JobSeeker] as $nonAdministratorRole) {
            $role = Role::query()
                ->where('slug', $nonAdministratorRole->value)
                ->firstOrFail();

            $this->assertSame(
                0,
                $role->permissions()->where('permissions.id', $permission->id)->count(),
                sprintf('The [%s] role must not hold the audit log permission.', $nonAdministratorRole->value),
            );
        }
    }

    // ---------------------------------------------------------------- isolation

    public function test_the_trail_is_never_reachable_from_employer_or_candidate_pages(): void
    {
        $employer = User::factory()->approvedEmployer()->create();
        $candidate = User::factory()->jobSeeker()->create();
        $entry = $this->seedEntry();

        foreach ([$employer, $candidate] as $user) {
            $this->actingAs($user)
                ->get(route('admin.audit-logs.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('admin.audit-logs.show', $entry))
                ->assertForbidden();
        }

        $this->actingAs($employer)
            ->get(route('employer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('auditLogs')
                ->missing('auditLog'),
            );

        $this->actingAs($candidate)
            ->get(route('candidate.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('auditLogs')
                ->missing('auditLog'),
            );
    }
}
