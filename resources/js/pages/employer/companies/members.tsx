import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Trash2, UserPlus, Users } from 'lucide-react';
import Pagination from '@/components/pagination';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    index as companiesIndex,
    show as companiesShow,
} from '@/routes/employer/companies';
import {
    store as membersStore,
    update as membersUpdate,
    destroy as membersDestroy,
} from '@/routes/employer/companies/members';
import type {
    CompanyMember,
    CompanyMemberRole,
    CompanyMembersIndexProps,
} from '@/types';

export default function EmployerCompanyMembers({
    company,
    members,
    canManage,
    roles,
}: CompanyMembersIndexProps) {
    const addForm = useForm<{
        email: string;
        company_role: CompanyMemberRole;
    }>({
        email: '',
        company_role: 'recruiter',
    });

    const handleAdd = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        addForm.post(membersStore.url(company.id), {
            preserveScroll: true,
            onSuccess: () => addForm.reset(),
        });
    };

    return (
        <>
            <Head title={`Team members - ${company.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={companiesShow(company.id)}>
                            <ArrowLeft className="size-4" />
                            Back to company
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Team members
                        </h1>
                        <p className="text-sm text-muted-foreground md:text-base">
                            Manage who can access {company.name}.
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {canManage && (
                        <div className="lg:col-span-1">
                            <Card className="border-border">
                                <CardHeader>
                                    <CardTitle className="text-lg">
                                        Add team member
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={handleAdd}
                                        className="flex flex-col gap-4"
                                    >
                                        <div className="space-y-2">
                                            <Label htmlFor="member-email">
                                                Email
                                            </Label>
                                            <Input
                                                id="member-email"
                                                type="email"
                                                value={addForm.data.email}
                                                onChange={(event) =>
                                                    addForm.setData(
                                                        'email',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="employer@example.com"
                                                disabled={addForm.processing}
                                                autoComplete="email"
                                            />
                                            <InputError
                                                message={addForm.errors.email}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="member-role">
                                                Role
                                            </Label>
                                            <Select
                                                value={
                                                    addForm.data.company_role
                                                }
                                                onValueChange={(value) =>
                                                    addForm.setData(
                                                        'company_role',
                                                        value as CompanyMemberRole,
                                                    )
                                                }
                                                disabled={addForm.processing}
                                            >
                                                <SelectTrigger
                                                    id="member-role"
                                                    className="w-full"
                                                >
                                                    <SelectValue placeholder="Select role" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((role) => (
                                                        <SelectItem
                                                            key={role}
                                                            value={role}
                                                        >
                                                            {formatRole(role)}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    addForm.errors.company_role
                                                }
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={addForm.processing}
                                            className="w-full sm:w-auto"
                                        >
                                            {addForm.processing && <Spinner />}
                                            <UserPlus className="size-4" />
                                            Add member
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                    )}

                    <div className="lg:col-span-2">
                        {members.data.length === 0 ? (
                            <Card className="border-border">
                                <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                                    <Users className="size-10 text-muted-foreground" />
                                    <p className="text-sm text-muted-foreground">
                                        No team members yet.
                                    </p>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="border-border">
                                <CardHeader>
                                    <CardTitle className="text-lg">
                                        Members
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="overflow-hidden rounded-lg border border-border">
                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-sm">
                                                <thead className="border-b border-border bg-muted/50 text-xs font-medium text-muted-foreground uppercase">
                                                    <tr>
                                                        <th
                                                            scope="col"
                                                            className="px-4 py-3"
                                                        >
                                                            Member
                                                        </th>
                                                        <th
                                                            scope="col"
                                                            className="px-4 py-3"
                                                        >
                                                            Role
                                                        </th>
                                                        <th
                                                            scope="col"
                                                            className="px-4 py-3"
                                                        >
                                                            Status
                                                        </th>
                                                        {canManage && (
                                                            <th
                                                                scope="col"
                                                                className="px-4 py-3 text-right"
                                                            >
                                                                Actions
                                                            </th>
                                                        )}
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-border">
                                                    {members.data.map(
                                                        (member) => (
                                                            <MemberRow
                                                                key={member.id}
                                                                company={
                                                                    company
                                                                }
                                                                member={member}
                                                                canManage={
                                                                    canManage
                                                                }
                                                                roles={roles}
                                                            />
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {members.total > 0 && (
                                        <Pagination
                                            currentPage={members.current_page}
                                            lastPage={members.last_page}
                                            perPage={members.per_page}
                                            total={members.total}
                                            prevPageUrl={members.prev_page_url}
                                            nextPageUrl={members.next_page_url}
                                        />
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function MemberRow({
    company,
    member,
    canManage,
    roles,
}: {
    company: { id: number; name: string };
    member: CompanyMember;
    canManage: boolean;
    roles: CompanyMemberRole[];
}) {
    const form = useForm<{ company_role: CompanyMemberRole }>({
        company_role: member.company_role,
    });
    const [revokeOpen, setRevokeOpen] = useState(false);
    const [revoking, setRevoking] = useState(false);
    const [revokeError, setRevokeError] = useState<string | null>(null);

    const dirty = form.data.company_role !== member.company_role;
    const isRevoked = member.status === 'revoked';

    const handleRoleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.patch(
            membersUpdate.url({ company: company.id, member: member.id }),
            {
                preserveScroll: true,
            },
        );
    };

    const handleRevoke = () => {
        setRevoking(true);
        setRevokeError(null);
        router.delete(
            membersDestroy.url({ company: company.id, member: member.id }),
            {
                preserveScroll: true,
                onError: (errors) => {
                    setRevokeError(
                        (errors as Record<string, string>).company_role ||
                            (errors as Record<string, string>).message ||
                            'Unable to revoke this member.',
                    );
                },
                onFinish: () => {
                    setRevoking(false);
                },
            },
        );
    };

    return (
        <tr className="hover:bg-muted/40">
            <td className="px-4 py-3">
                <div className="flex flex-col">
                    <span className="font-medium">{member.name ?? '—'}</span>
                    <span className="text-xs text-muted-foreground">
                        {member.email ?? '—'}
                    </span>
                </div>
            </td>
            <td className="px-4 py-3">
                {canManage ? (
                    <form
                        onSubmit={handleRoleSubmit}
                        className="flex items-center gap-2"
                    >
                        <Select
                            value={form.data.company_role}
                            onValueChange={(value) =>
                                form.setData(
                                    'company_role',
                                    value as CompanyMemberRole,
                                )
                            }
                            disabled={form.processing || isRevoked}
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem key={role} value={role}>
                                        {formatRole(role)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={!dirty || form.processing || isRevoked}
                        >
                            {form.processing && <Spinner />}
                            Save
                        </Button>
                        <InputError message={form.errors.company_role} />
                    </form>
                ) : (
                    <Badge variant="secondary">
                        {formatRole(member.company_role)}
                    </Badge>
                )}
            </td>
            <td className="px-4 py-3">
                <StatusBadge status={member.status} />
            </td>
            {canManage && (
                <td className="px-4 py-3 text-right">
                    {isRevoked ? (
                        <span className="text-xs text-muted-foreground">
                            Revoked
                        </span>
                    ) : (
                        <Dialog
                            open={revokeOpen}
                            onOpenChange={(open) => {
                                setRevokeOpen(open);
                                if (!open) setRevokeError(null);
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    title="Revoke member"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Revoke team member?
                                    </DialogTitle>
                                    <DialogDescription>
                                        {member.name ?? member.email} will lose
                                        access to {company.name} immediately.
                                    </DialogDescription>
                                </DialogHeader>
                                {revokeError && (
                                    <p className="text-sm text-red-600 dark:text-red-400">
                                        {revokeError}
                                    </p>
                                )}
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="outline"
                                            disabled={revoking}
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        variant="destructive"
                                        disabled={revoking}
                                        onClick={handleRevoke}
                                    >
                                        {revoking && <Spinner />}
                                        Revoke
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    )}
                </td>
            )}
        </tr>
    );
}

function formatRole(role: CompanyMemberRole): string {
    switch (role) {
        case 'owner':
            return 'Owner';
        case 'recruiter':
            return 'Recruiter';
        case 'reviewer':
            return 'Reviewer';
        default:
            return String(role);
    }
}

function StatusBadge({ status }: { status: CompanyMember['status'] }) {
    if (status === 'active') {
        return (
            <Badge
                variant="outline"
                className="gap-1 border-emerald-300 text-emerald-600 dark:border-emerald-800 dark:text-emerald-400"
            >
                Active
            </Badge>
        );
    }

    if (status === 'revoked') {
        return (
            <Badge
                variant="outline"
                className="gap-1 border-border text-muted-foreground"
            >
                Revoked
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="gap-1 border-border text-muted-foreground"
        >
            {status}
        </Badge>
    );
}

EmployerCompanyMembers.layout = {
    breadcrumbs: [
        {
            title: 'Company Profiles',
            href: companiesIndex(),
        },
        {
            title: 'Company',
            href: companiesIndex(),
        },
    ],
};
