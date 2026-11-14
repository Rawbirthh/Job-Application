import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import JobStatusBadge from '@/components/jobs/job-status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    index as jobsIndex,
    approve as jobApprove,
    reject as jobReject,
    close as jobClose,
} from '@/routes/admin/jobs';

type JobDetail = {
    id: number;
    title: string;
    description: string;
    responsibilities: string | null;
    requirements: string | null;
    status: string;
    rejection_reason: string | null;
    salary_min: number | null;
    salary_max: number | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    location: string | null;
    application_deadline: string | null;
    company: {
        id: number;
        name: string;
        owner: { name: string; email: string } | null;
    } | null;
    category: string | null;
    employment_type: string | null;
    workplace_arrangement: string | null;
    experience_level: string | null;
    pay_period: string | null;
};

type AdminJobShowProps = {
    job: JobDetail;
};

const detailLabelClassName =
    'text-xs font-medium tracking-wide text-muted-foreground uppercase';

export default function AdminJobShow({ job }: AdminJobShowProps) {
    const [showRejectForm, setShowRejectForm] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        rejection_reason: '',
    });

    const handleApprove = () => {
        post(jobApprove.url({ job: job.id }));
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        post(jobReject.url({ job: job.id }));
    };

    const handleClose = () => {
        post(jobClose.url({ job: job.id }));
    };

    return (
        <div className="w-full space-y-6 p-6">
            <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight break-words">
                            {job.title}
                        </h1>
                        <JobStatusBadge status={job.status} />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Company: {job.company?.name ?? '—'}{' '}
                        {job.company?.owner &&
                            `(Owner: ${job.company.owner.name})`}
                    </p>
                </div>
                <Button asChild variant="outline" className="shrink-0">
                    <Link href={jobsIndex.url()}>Back to Queue</Link>
                </Button>
            </div>

            {job.rejection_reason && (
                <div className="rounded-md border border-destructive/50 bg-destructive/5 p-4 text-destructive">
                    <h3 className="font-semibold">Rejection Reason:</h3>
                    <p className="mt-1 text-sm">{job.rejection_reason}</p>
                </div>
            )}

            <div className="grid items-start gap-6 lg:grid-cols-3">
                {/* Main column: job content */}
                <div className="space-y-6 lg:col-span-2">
                    <div className="grid grid-cols-1 gap-5 rounded-lg border border-border bg-card p-6 shadow-sm sm:grid-cols-2">
                        <div>
                            <span className={detailLabelClassName}>
                                Category
                            </span>
                            <p className="mt-1 text-base font-semibold">
                                {job.category || '—'}
                            </p>
                        </div>
                        <div>
                            <span className={detailLabelClassName}>
                                Employment Type
                            </span>
                            <p className="mt-1 text-base font-semibold">
                                {job.employment_type || '—'}
                            </p>
                        </div>
                        <div>
                            <span className={detailLabelClassName}>
                                Workplace Arrangement
                            </span>
                            <p className="mt-1 text-base font-semibold">
                                {job.workplace_arrangement || '—'}
                            </p>
                        </div>
                        <div>
                            <span className={detailLabelClassName}>
                                Experience Level
                            </span>
                            <p className="mt-1 text-base font-semibold">
                                {job.experience_level || '—'}
                            </p>
                        </div>
                        <div>
                            <span className={detailLabelClassName}>
                                Location
                            </span>
                            <p className="mt-1 text-base font-semibold">
                                {job.location || '—'}
                            </p>
                        </div>
                        <div>
                            <span className={detailLabelClassName}>Salary</span>
                            <p className="mt-1 text-base font-semibold">
                                {job.salary_min !== null ||
                                job.salary_max !== null ? (
                                    <>
                                        {job.salary_currency}{' '}
                                        {job.salary_min ?? '—'} -{' '}
                                        {job.salary_max ?? '—'} (
                                        {job.pay_period})
                                        {job.salary_negotiable &&
                                            ' (Negotiable)'}
                                    </>
                                ) : (
                                    'Not disclosed'
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                        <h2 className="text-lg font-semibold">
                            Job Description
                        </h2>
                        <div className="prose max-w-none text-sm whitespace-pre-wrap text-foreground">
                            {job.description}
                        </div>
                    </div>

                    {job.responsibilities && (
                        <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                            <h2 className="text-lg font-semibold">
                                Responsibilities
                            </h2>
                            <div className="prose max-w-none text-sm whitespace-pre-wrap text-foreground">
                                {job.responsibilities}
                            </div>
                        </div>
                    )}

                    {job.requirements && (
                        <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                            <h2 className="text-lg font-semibold">
                                Requirements
                            </h2>
                            <div className="prose max-w-none text-sm whitespace-pre-wrap text-foreground">
                                {job.requirements}
                            </div>
                        </div>
                    )}
                </div>

                {/* Aside: moderation actions + company context */}
                <aside className="space-y-6 lg:col-span-1">
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm lg:sticky lg:top-6">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-base font-semibold">
                                Moderation
                            </h2>
                            <JobStatusBadge status={job.status} />
                        </div>

                        {job.status === 'pending_review' && (
                            <div className="flex flex-col gap-2 border-t border-border pt-4">
                                <Button
                                    onClick={handleApprove}
                                    className="w-full bg-green-600 hover:bg-green-700"
                                >
                                    Approve & Publish
                                </Button>
                                <Button
                                    onClick={() =>
                                        setShowRejectForm(!showRejectForm)
                                    }
                                    variant="destructive"
                                    className="w-full"
                                >
                                    Reject
                                </Button>
                            </div>
                        )}

                        {job.status === 'published' && (
                            <div className="flex flex-col gap-2 border-t border-border pt-4">
                                <Button
                                    onClick={handleClose}
                                    variant="outline"
                                    className="w-full"
                                >
                                    Close Job
                                </Button>
                            </div>
                        )}

                        {job.status !== 'pending_review' &&
                            job.status !== 'published' && (
                                <p className="border-t border-border pt-4 text-sm text-muted-foreground">
                                    No moderation actions available for this
                                    status.
                                </p>
                            )}

                        {showRejectForm && (
                            <form
                                onSubmit={handleReject}
                                className="space-y-3 rounded-md border border-destructive/40 bg-destructive/10 p-4"
                            >
                                <h3 className="text-sm font-semibold text-destructive">
                                    Reason for Rejection
                                </h3>
                                <div>
                                    <Label
                                        htmlFor="rejection_reason"
                                        className="sr-only"
                                    >
                                        Rejection Reason
                                    </Label>
                                    <Textarea
                                        id="rejection_reason"
                                        value={data.rejection_reason}
                                        onChange={(e) =>
                                            setData(
                                                'rejection_reason',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Explain why this job posting is being rejected..."
                                        rows={4}
                                    />
                                    <InputError
                                        message={errors.rejection_reason}
                                        className="mt-2"
                                    />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setShowRejectForm(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Confirm Rejection
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>

                    <div className="space-y-3 rounded-lg border border-border bg-card p-6 shadow-sm">
                        <h2 className={detailLabelClassName}>Company</h2>
                        <div>
                            <p className="text-base font-semibold">
                                {job.company?.name ?? '—'}
                            </p>
                            {job.company?.owner && (
                                <p className="mt-0.5 text-sm text-muted-foreground">
                                    {job.company.owner.name} ·{' '}
                                    {job.company.owner.email}
                                </p>
                            )}
                        </div>
                        {job.application_deadline && (
                            <div className="border-t border-border pt-3">
                                <span className={detailLabelClassName}>
                                    Application Deadline
                                </span>
                                <p className="mt-1 text-sm font-medium">
                                    {new Date(
                                        job.application_deadline,
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                        )}
                    </div>
                </aside>
            </div>
        </div>
    );
}
