<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Authorization\Concerns\InteractsWithAuthorizationCatalogue;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use InteractsWithAuthorizationCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAuthorizationCatalogue();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
        $this->patch('/notifications/00000000-0000-0000-0000-000000000000/read')
            ->assertRedirect(route('login'));
    }

    public function test_notifications_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();

        $own = $this->createNotification($user, ['title' => 'Own']);
        $this->createNotification($other, ['title' => 'Other']);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.id', $own->id)
            );
    }

    public function test_notifications_are_ordered_newest_first(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $older = $this->createNotification($user, ['title' => 'Older']);
        $older->forceFill(['created_at' => now()->subMinutes(5)])->save();

        $newer = $this->createNotification($user, ['title' => 'Newer']);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 2)
                ->where('notifications.data.0.id', $newer->id)
                ->where('notifications.data.1.id', $older->id)
            );
    }

    public function test_owner_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $notification = $this->createNotification($user, [
            'title' => 'Read me',
            'url' => route('dashboard'),
        ]);

        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_an_already_read_notification_still_redirects(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $notification = $this->createNotification($user, [
            'title' => 'Already read',
            'url' => route('dashboard'),
        ]);
        $notification->markAsRead();

        $this->actingAs($user)
            ->patch(route('notifications.read', $notification))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_non_owner_cannot_mark_a_notification_as_read(): void
    {
        $owner = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();
        $notification = $this->createNotification($owner, ['title' => 'Private']);

        $this->actingAs($other)
            ->patch(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_marking_an_invalid_notification_returns_not_found(): void
    {
        $user = User::factory()->jobSeeker()->create();

        $this->actingAs($user)
            ->patch('/notifications/00000000-0000-0000-0000-000000000000/read')
            ->assertNotFound();
    }

    public function test_mark_all_as_read_marks_only_the_authenticated_users_unread_notifications(): void
    {
        $user = User::factory()->jobSeeker()->create();
        $other = User::factory()->jobSeeker()->create();

        $first = $this->createNotification($user, ['title' => 'One']);
        $second = $this->createNotification($user, ['title' => 'Two']);
        $alreadyRead = $this->createNotification($user, ['title' => 'Read']);
        $alreadyRead->markAsRead();

        $otherNotification = $this->createNotification($other, ['title' => 'Other']);
        $otherUnreadBefore = $otherNotification->fresh()->read_at;

        $this->actingAs($user)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNotNull($alreadyRead->fresh()->read_at);
        $this->assertNull($otherNotification->fresh()->read_at);
        $this->assertSame($otherUnreadBefore, $otherNotification->fresh()->read_at);

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $other->fresh()->unreadNotifications()->count());
    }

    private function createNotification(User $user, array $data = []): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ApplicationSubmittedNotification',
            'data' => array_merge(['title' => 'Test', 'message' => 'Test message'], $data),
            'read_at' => null,
        ]);
    }
}
