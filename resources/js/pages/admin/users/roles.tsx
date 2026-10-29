import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Info, ShieldAlert, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { useId } from 'react';
import UserRoleController from '@/actions/App/Http/Controllers/Admin/UserRoleController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import type { AdminUserRolesProps, RoleOption } from '@/types';

type RoleCheckboxProps = {
    role: RoleOption;
    checked: boolean;
    disabled: boolean;
    invalid: boolean;
    describedBy: string;
    onChange: (checked: boolean) => void;
};

function RoleCheckbox({
    role,
    checked,
    disabled,
    invalid,
    describedBy,
    onChange,
}: RoleCheckboxProps) {
    const id = useId();
    const descriptionId = `${id}-description`;
    const ariaDescribedBy = role.description
        ? `${describedBy} ${descriptionId}`
        : describedBy;

    return (
        <div className="flex items-start gap-3 rounded-lg border border-border p-4">
            <Checkbox
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={(value) => onChange(value === true)}
                aria-invalid={invalid || undefined}
                aria-describedby={ariaDescribedBy}
                className="mt-0.5"
            />

            <div className="grid gap-1">
                <div className="flex flex-wrap items-center gap-2">
                    <Label htmlFor={id} className="cursor-pointer">
                        {role.name}
                    </Label>

                    {role.is_system && (
                        <Badge variant="outline" className="gap-1">
                            <ShieldCheck aria-hidden="true" />
                            System
                        </Badge>
                    )}
                </div>

                {role.description && (
                    <p
                        id={descriptionId}
                        className="text-sm text-muted-foreground"
                    >
                        {role.description}
                    </p>
                )}
            </div>
        </div>
    );
}

export default function AdminUserRoles({
    user,
    roles,
    canViewUsers,
}: AdminUserRolesProps) {
    const groupId = useId();
    const helpId = `${groupId}-help`;
    const errorId = `${groupId}-error`;

    const backUrl = canViewUsers
        ? UserRoleController.index.url()
        : dashboard.url();
    const backLabel = canViewUsers ? 'Back to users' : 'Back to dashboard';

    const form = useForm<{ roles: number[] }>({
        roles: user.roles.map((role) => role.id),
    });

    const roleErrors = Array.from(
        new Set(
            Object.entries(form.errors)
                .filter(([key]) => key === 'roles' || key.startsWith('roles.'))
                .map(([, message]) => message),
        ),
    );
    const roleError = roleErrors.length > 0 ? roleErrors.join(' ') : undefined;
    const groupDescribedBy = roleError ? `${helpId} ${errorId}` : helpId;

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.put(UserRoleController.update.url(user.id), {
            preserveScroll: true,
        });
    };

    const toggleRole = (roleId: number, checked: boolean) => {
        form.setData(
            'roles',
            checked
                ? Array.from(new Set([...form.data.roles, roleId]))
                : form.data.roles.filter((id) => id !== roleId),
        );
    };

    return (
        <>
            <Head title={`Manage roles for ${user.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            Manage roles
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Update the global roles assigned to {user.name}.
                        </p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={backUrl}>
                            <ArrowLeft aria-hidden="true" />
                            {backLabel}
                        </Link>
                    </Button>
                </div>

                <section
                    aria-labelledby="user-summary-heading"
                    className="rounded-lg border border-border p-4"
                >
                    <h2 id="user-summary-heading" className="sr-only">
                        User details
                    </h2>

                    <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[7rem_1fr]">
                        <dt className="text-muted-foreground">Name</dt>
                        <dd className="font-medium">{user.name}</dd>

                        <dt className="text-muted-foreground">Email</dt>
                        <dd className="break-words">{user.email}</dd>

                        <dt className="text-muted-foreground">Current roles</dt>
                        <dd>
                            {user.roles.length > 0 ? (
                                <ul
                                    className="flex flex-wrap gap-1.5"
                                    aria-label="Current roles"
                                >
                                    {user.roles.map((role) => (
                                        <li key={role.id}>
                                            <Badge variant="secondary">
                                                {role.name}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <span className="text-muted-foreground">
                                    No roles
                                </span>
                            )}
                        </dd>
                    </dl>
                </section>

                {roles.length > 0 && (
                    <div
                        role="note"
                        className="flex gap-3 rounded-lg border border-border bg-muted/40 p-4 text-sm"
                    >
                        <Info
                            className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div className="space-y-1">
                            <p className="font-medium">Before you save</p>
                            <p className="text-muted-foreground">
                                Users with no roles can sign in, but they cannot
                                access permission-protected areas. At least one
                                platform administrator must remain assigned.
                            </p>
                        </div>
                    </div>
                )}

                {roles.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-lg border border-border p-8 text-center">
                        <ShieldAlert
                            className="size-8 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="font-medium">No roles are available</p>
                        <p className="max-w-md text-sm text-muted-foreground">
                            The role catalogue is empty, so role assignments
                            cannot be changed. Run the authorization seeder or
                            ask a platform administrator to configure roles.
                        </p>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <fieldset
                            aria-describedby={groupDescribedBy}
                            disabled={form.processing}
                            className="min-w-0"
                        >
                            <legend className="text-base font-medium">
                                Role assignments
                            </legend>

                            <p
                                id={helpId}
                                className="mt-1 text-sm text-muted-foreground"
                            >
                                Select every role that should apply to this
                                user. An empty selection is allowed, but the
                                user will not be able to access
                                permission-protected areas.
                            </p>

                            <InputError
                                id={errorId}
                                message={roleError}
                                className="mt-2"
                            />

                            <div className="mt-4 grid gap-3">
                                {roles.map((role) => (
                                    <RoleCheckbox
                                        key={role.id}
                                        role={role}
                                        checked={form.data.roles.includes(
                                            role.id,
                                        )}
                                        disabled={form.processing}
                                        invalid={Boolean(roleError)}
                                        describedBy={groupDescribedBy}
                                        onChange={(checked) =>
                                            toggleRole(role.id, checked)
                                        }
                                    />
                                ))}
                            </div>
                        </fieldset>

                        <div className="flex flex-col-reverse gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p
                                className="text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                {form.data.roles.length} of {roles.length}{' '}
                                selected
                            </p>

                            <div className="flex items-center gap-2">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={backUrl}>Cancel</Link>
                                </Button>

                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing && <Spinner />}
                                    Save roles
                                </Button>
                            </div>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

AdminUserRoles.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: UserRoleController.index.url(),
        },
        {
            title: 'Manage roles',
            href: UserRoleController.index.url(),
        },
    ],
};
