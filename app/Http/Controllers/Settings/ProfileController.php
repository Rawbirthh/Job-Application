<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AuditAction;
use App\Exceptions\LastAdministratorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Show the user's profile settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/profile');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(
        ProfileDeleteRequest $request,
        RoleAssignmentService $roleAssignmentService,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->authorize('delete', $user);

        try {
            DB::transaction(function () use ($user, $roleAssignmentService): void {
                $roleAssignmentService->assertUserCanBeDeleted($user);

                // The audit entry is written before the deletion so the actor
                // snapshot survives the foreign key nulling out.
                $this->auditLogService->record(AuditAction::AccountDeleted, $user, [
                    'self_service' => true,
                ], $user);

                $user->delete();
            });
        } catch (LastAdministratorException) {
            throw ValidationException::withMessages([
                'account' => __('The last platform administrator account cannot be deleted.'),
            ]);
        }

        // The deleted model must not cycle its remember token, which would re-insert it.
        Auth::logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
