<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmployerRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployerController extends Controller
{
    /**
     * Display the admin employer management index.
     */
    public function index(Request $request): Response
    {
        $this->authorize('employers.viewAny');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $statusFilter = $validated['status'] ?? null;
        $actor = $request->user();

        $query = User::query();

        // Filter by search term (name or email)
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        // Filter by status if provided
        if ($statusFilter !== null) {
            $query->where('status', $statusFilter);
        }

        $users = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $users->through(function (User $user) use ($actor): array {
            $statusBadge = match ($user->status) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                'suspended' => 'suspended',
                default => 'pending',
            };

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'status_badge' => $statusBadge,
                'suspension_reason' => $user->status === 'suspended' ? $user->suspension_reason : null,
                'company_count' => null,
                'can_manage' => $actor instanceof User
                    && $actor->hasPermission('employers.manage'),
            ];
        });

        return Inertia::render('admin/employers/index', [
            'employers' => $users,
            'filters' => ['search' => $search, 'status' => $statusFilter],
            'canManageEmployers' => $actor instanceof User
                && $actor->hasPermission('employers.manage'),
        ]);
    }

    /**
     * Display the specified user's employer profile.
     */
    public function show(Request $request, User $user): Response
    {
        $actor = $request->user();

        $this->authorize('employers.view', $user);

        return Inertia::render('admin/employers/show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'status_badge' => $this->statusBadge($user->status),
                'suspension_reason' => $user->status === 'suspended' ? $user->suspension_reason : null,
                'company_count' => null,
            ],
            'canManageEmployers' => $actor instanceof User
                && $actor->hasPermission('employers.manage'),
        ]);
    }

    /**
     * Handle the employer status transition.
     */
    public function store(StoreEmployerRequest $request, User $user): RedirectResponse
    {
        $this->authorize('employers.manage');

        $status = $request->input('status');

        switch ($status) {
            case 'approved':
                $user->status = 'approved';
                $user->suspension_reason = null;
                break;

            case 'rejected':
                $user->status = 'rejected';
                $user->suspension_reason = null;
                break;

            case 'suspended':
                $user->status = 'suspended';
                $user->suspension_reason = $request->input('suspension_reason');
                break;

            case 'reactivate':
                $user->status = 'approved';
                $user->suspension_reason = null;
                break;
        }

        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Employer '.($status === 'reactivate' ? 'reactivated' : ucfirst($status)).'.',
        ]);

        return to_route('admin.employers.index');
    }

    /**
     * Escape status string for badge display.
     */
    private function statusBadge(string $status): string
    {
        return match ($status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'suspended' => 'suspended',
            default => 'pending',
        };
    }
}
