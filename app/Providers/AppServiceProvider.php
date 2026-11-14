<?php

namespace App\Providers;

use App\Listeners\RecordAuthenticationAuditEvents;
use App\Models\ApplicationConversation;
use App\Models\ApplicationEvaluation;
use App\Models\ApplicationNote;
use App\Models\ConversationMessage;
use App\Policies\ApplicationEvaluationPolicy;
use App\Policies\ApplicationNotePolicy;
use App\Policies\ConversationPolicy;
use App\Policies\EmployerPolicy;
use App\Policies\NotificationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('employers.viewAny', [EmployerPolicy::class, 'viewAny']);
        Gate::define('employers.view', [EmployerPolicy::class, 'view']);
        Gate::define('employers.review', [EmployerPolicy::class, 'review']);
        Gate::define('employers.suspend', [EmployerPolicy::class, 'suspend']);
        Gate::define('employers.reactivate', [EmployerPolicy::class, 'reactivate']);

        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);
        Gate::policy(ApplicationConversation::class, ConversationPolicy::class);
        Gate::policy(ConversationMessage::class, ConversationPolicy::class);
        Gate::policy(ApplicationNote::class, ApplicationNotePolicy::class);
        Gate::policy(ApplicationEvaluation::class, ApplicationEvaluationPolicy::class);

        // Edit/delete targets are allowed to resolve to tombstoned messages;
        // the service then returns a validation error instead of a 404.
        Route::bind('message', fn (string $value): ConversationMessage => ConversationMessage::withTrashed()->findOrFail($value));

        // Authentication events are the only source of login/logout facts.
        Event::listen(Login::class, [RecordAuthenticationAuditEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [RecordAuthenticationAuditEvents::class, 'handleLogout']);
        Event::listen(PasswordReset::class, [RecordAuthenticationAuditEvents::class, 'handlePasswordReset']);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
