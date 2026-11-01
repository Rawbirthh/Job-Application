import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    CheckCircle2,
    PlayCircle,
    ShieldAlert,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    approve as approveEmployer,
    index as employersIndex,
    reactivate as reactivateEmployer,
    reject as rejectEmployer,
    suspend as suspendEmployer,
} from '@/routes/admin/employers';
import type { AdminEmployerShowProps } from '@/types';
import { useFlashToast } from '@/hooks/use-flash-toast';

function statusBadgeClassName(badge: string): string {
    switch (badge) {
        case 'approved':
            return 'border-transparent bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300';
        case 'rejected':
        case 'suspended':
            return 'border-transparent bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-300';
        case 'pending':
            return 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300';
        default:
            return 'border-transparent bg-muted text-muted-foreground';
    }
}

export default function AdminEmployerShow({
    employer,
    canManageEmployers,
}: AdminEmployerShowProps) {
    useFlashToast();

    const { errors } = usePage<{
        errors: { reason?: string };
    }>().props;

    const [isProcessing, setIsProcessing] = useState(false);

    const rejectForm = useForm<{ reason: string }>({ reason: '' });
    const suspendForm = useForm<{ reason: string }>({ reason: '' });

    const runAction = (url: string, confirmMessage?: string) => {
        if (confirmMessage && !window.confirm(confirmMessage)) {
            return;
        }
        setIsProcessing(true);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleReject = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        rejectForm.post(rejectEmployer.url({ user: employer.id }), {
            preserveScroll: true,
        });
    };

    const handleSuspend = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        suspendForm.post(suspendEmployer.url({ user: employer.id }), {
            preserveScroll: true,
        });
    };

    const request = employer.onboarding_request;

    return (
        <>
            <Head title={`Employer Review — ${employer.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={employersIndex()}>
                            <ArrowLeft className="size-4" />
                            Back to employers
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {employer.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {employer.email}
                        </p>
                    </div>
                    <Badge
                        variant="default"
                        className={statusBadgeClassName(employer.status_badge)}
                    >
                        {employer.status_badge}
                    </Badge>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Applicant summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <span className="font-medium">Name:</span>{' '}
                                {employer.name}
                            </div>
                            <div>
                                <span className="font-medium">Email:</span>{' '}
                                {employer.email}
                            </div>
                            <div>
                                <span className="font-medium">Status:</span>{' '}
                                {employer.status ?? 'not applied'}
                            </div>
                            {employer.suspension_reason && (
                                <div>
                                    <span className="font-medium">
                                        Suspension reason:
                                    </span>{' '}
                                    {employer.suspension_reason}
                                </div>
                            )}
                            <div>
                                <span className="font-medium">Roles:</span>{' '}
                                {employer.roles.length > 0
                                    ? employer.roles
                                          .map((role) => role.name)
                                          .join(', ')
                                    : '—'}
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Request details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {request ? (
                                <>
                                    <div>
                                        <span className="font-medium">
                                            Company:
                                        </span>{' '}
                                        {request.company_name}
                                    </div>
                                    <div>
                                        <span className="font-medium">
                                            Website:
                                        </span>{' '}
                                        {request.company_website ?? '—'}
                                    </div>
                                    <div>
                                        <span className="font-medium">
                                            Job title:
                                        </span>{' '}
                                        {request.job_title}
                                    </div>
                                    <div>
                                        <span className="font-medium">
                                            Message:
                                        </span>{' '}
                                        {request.message ?? '—'}
                                    </div>
                                    <div>
                                        <span className="font-medium">
                                            Submitted:
                                        </span>{' '}
                                        {request.submitted_at
                                            ? new Date(
                                                  request.submitted_at,
                                              ).toLocaleString()
                                            : '—'}
                                    </div>
                                    <div>
                                        <span className="font-medium">
                                            Reviewed:
                                        </span>{' '}
                                        {request.reviewed_at
                                            ? new Date(
                                                  request.reviewed_at,
                                              ).toLocaleString()
                                            : '—'}
                                    </div>
                                    <div>
                                        <span className="font-medium">
                                            Reviewer:
                                        </span>{' '}
                                        {request.reviewer?.name ?? '—'}
                                    </div>
                                    <div>
                                        <span className="font-medium">
                                            Review note:
                                        </span>{' '}
                                        {request.review_note ?? '—'}
                                    </div>
                                </>
                            ) : (
                                <p className="text-muted-foreground">
                                    No onboarding request on file.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="border-border">
                    <CardHeader>
                        <CardTitle className="text-lg">
                            Company profile
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        {employer.company ? (
                            <>
                                <div className="flex items-center gap-3">
                                    {employer.company.logo_url ? (
                                        <img
                                            src={employer.company.logo_url}
                                            alt={`${employer.company.name} logo`}
                                            className="size-16 rounded-md object-cover"
                                        />
                                    ) : (
                                        <div className="flex size-16 items-center justify-center rounded-md bg-muted">
                                            <Building2 className="size-8 text-muted-foreground" />
                                        </div>
                                    )}
                                    <div className="space-y-1">
                                        <div className="font-medium">
                                            {employer.company.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {employer.company.slug}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <span className="font-medium">
                                        Industry:
                                    </span>{' '}
                                    {employer.company.industry ?? '—'}
                                </div>
                                <div>
                                    <span className="font-medium">
                                        Website:
                                    </span>{' '}
                                    {employer.company.website ? (
                                        <a
                                            href={employer.company.website}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-teal-600 underline dark:text-teal-400"
                                        >
                                            {employer.company.website}
                                        </a>
                                    ) : (
                                        '—'
                                    )}
                                </div>
                                <div>
                                    <span className="font-medium">
                                        Size band:
                                    </span>{' '}
                                    {employer.company.size_band ?? '—'}
                                </div>
                                <div>
                                    <span className="font-medium">
                                        Location:
                                    </span>{' '}
                                    {employer.company.location ?? '—'}
                                </div>
                                <div>
                                    <span className="font-medium">
                                        Overview:
                                    </span>{' '}
                                    {employer.company.overview ?? '—'}
                                </div>
                                <div>
                                    <span className="font-medium">Status:</span>{' '}
                                    {employer.company.is_active ? (
                                        <Badge
                                            variant="outline"
                                            className="gap-1 border-emerald-300 text-emerald-600 dark:border-emerald-800 dark:text-emerald-400"
                                        >
                                            Active
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="outline"
                                            className="gap-1 border-border text-muted-foreground"
                                        >
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                                <div>
                                    <span className="font-medium">
                                        Created:
                                    </span>{' '}
                                    {employer.company.created_at
                                        ? new Date(
                                              employer.company.created_at,
                                          ).toLocaleString()
                                        : '—'}
                                </div>
                            </>
                        ) : (
                            <p className="text-muted-foreground">
                                No company profile on file.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {canManageEmployers ? (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">Actions</CardTitle>
                            <CardDescription>
                                Available actions depend on the current status.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {employer.actions.approve && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        disabled={
                                            isProcessing ||
                                            rejectForm.processing ||
                                            suspendForm.processing
                                        }
                                        onClick={() =>
                                            runAction(
                                                approveEmployer.url({
                                                    user: employer.id,
                                                }),
                                                'Approve this employer application?',
                                            )
                                        }
                                    >
                                        {isProcessing ? (
                                            <Spinner className="mr-2" />
                                        ) : (
                                            <CheckCircle2 className="mr-2 size-4" />
                                        )}
                                        Approve
                                    </Button>
                                </div>
                            )}

                            {employer.actions.reject && (
                                <form
                                    onSubmit={handleReject}
                                    className="space-y-3 rounded-lg border border-border p-4"
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="reject-reason">
                                            Rejection reason
                                        </Label>
                                        <Textarea
                                            id="reject-reason"
                                            value={rejectForm.data.reason}
                                            onChange={(e) =>
                                                rejectForm.setData(
                                                    'reason',
                                                    e.target.value,
                                                )
                                            }
                                            rows={3}
                                            maxLength={500}
                                            disabled={rejectForm.processing}
                                            aria-invalid={
                                                rejectForm.errors.reason ||
                                                errors.reason
                                                    ? true
                                                    : undefined
                                            }
                                            aria-describedby="reject-reason-error"
                                        />
                                        <InputError
                                            id="reject-reason-error"
                                            message={
                                                rejectForm.errors.reason ??
                                                errors.reason
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={rejectForm.processing}
                                    >
                                        {rejectForm.processing && (
                                            <Spinner className="mr-2" />
                                        )}
                                        <XCircle className="mr-2 size-4" />
                                        Reject
                                    </Button>
                                </form>
                            )}

                            {employer.actions.suspend && (
                                <form
                                    onSubmit={handleSuspend}
                                    className="space-y-3 rounded-lg border border-border p-4"
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="suspend-reason">
                                            Suspension reason
                                        </Label>
                                        <Textarea
                                            id="suspend-reason"
                                            value={suspendForm.data.reason}
                                            onChange={(e) =>
                                                suspendForm.setData(
                                                    'reason',
                                                    e.target.value,
                                                )
                                            }
                                            rows={3}
                                            maxLength={500}
                                            disabled={suspendForm.processing}
                                            aria-invalid={
                                                suspendForm.errors.reason ||
                                                errors.reason
                                                    ? true
                                                    : undefined
                                            }
                                            aria-describedby="suspend-reason-error"
                                        />
                                        <InputError
                                            id="suspend-reason-error"
                                            message={
                                                suspendForm.errors.reason ??
                                                errors.reason
                                            }
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={suspendForm.processing}
                                    >
                                        {suspendForm.processing && (
                                            <Spinner className="mr-2" />
                                        )}
                                        <ShieldAlert className="mr-2 size-4" />
                                        Suspend
                                    </Button>
                                </form>
                            )}

                            {employer.actions.reactivate && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        disabled={isProcessing}
                                        onClick={() =>
                                            runAction(
                                                reactivateEmployer.url({
                                                    user: employer.id,
                                                }),
                                                'Reactivate this employer account?',
                                            )
                                        }
                                    >
                                        {isProcessing ? (
                                            <Spinner className="mr-2" />
                                        ) : (
                                            <PlayCircle className="mr-2 size-4" />
                                        )}
                                        Reactivate
                                    </Button>
                                </div>
                            )}

                            {!employer.actions.approve &&
                                !employer.actions.reject &&
                                !employer.actions.suspend &&
                                !employer.actions.reactivate && (
                                    <p className="text-sm text-muted-foreground">
                                        No actions available for the current
                                        status.
                                    </p>
                                )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="rounded-lg border border-border bg-card p-4 text-sm text-muted-foreground">
                        View only — you do not have permission to manage
                        employers.
                    </div>
                )}
            </div>
        </>
    );
}
