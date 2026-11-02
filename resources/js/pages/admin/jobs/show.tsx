import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
        <div className="max-w-4xl space-y-6 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {job.title}
                        </h1>
                        <Badge variant="outline">{job.status}</Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Company: {job.company?.name}{' '}
                        {job.company?.owner &&
                            `(Owner: ${job.company.owner.name})`}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={jobsIndex.url()}>Back to Queue</Link>
                    </Button>
                    {job.status === 'pending_review' && (
                        <>
                            <Button
                                onClick={handleApprove}
                                className="bg-green-600 hover:bg-green-700"
                            >
                                Approve & Publish
                            </Button>
                            <Button
                                onClick={() =>
                                    setShowRejectForm(!showRejectForm)
                                }
                                variant="destructive"
                            >
                                Reject
                            </Button>
                        </>
                    )}
                    {job.status === 'published' && (
                        <Button onClick={handleClose} variant="outline">
                            Close Job
                        </Button>
                    )}
                </div>
            </div>

            {showRejectForm && (
                <form
                    onSubmit={handleReject}
                    className="space-y-4 rounded-md border bg-destructive/10 p-6"
                >
                    <h2 className="text-lg font-semibold text-destructive">
                        Reason for Rejection
                    </h2>
                    <div>
                        <Label htmlFor="rejection_reason">
                            Rejection Reason
                        </Label>
                        <Textarea
                            id="rejection_reason"
                            value={data.rejection_reason}
                            onChange={(e) =>
                                setData('rejection_reason', e.target.value)
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
                            onClick={() => setShowRejectForm(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            Confirm Rejection
                        </Button>
                    </div>
                </form>
            )}

            {job.rejection_reason && (
                <div className="rounded-md border border-destructive/50 bg-destructive/5 p-4 text-destructive">
                    <h3 className="font-semibold">Rejection Reason:</h3>
                    <p className="mt-1 text-sm">{job.rejection_reason}</p>
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 rounded-lg border border-border bg-card p-6 shadow-sm md:grid-cols-2">
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Category
                    </span>
                    <p className="text-base font-semibold">
                        {job.category || '—'}
                    </p>
                </div>
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Employment Type
                    </span>
                    <p className="text-base font-semibold">
                        {job.employment_type || '—'}
                    </p>
                </div>
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Workplace Arrangement
                    </span>
                    <p className="text-base font-semibold">
                        {job.workplace_arrangement || '—'}
                    </p>
                </div>
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Experience Level
                    </span>
                    <p className="text-base font-semibold">
                        {job.experience_level || '—'}
                    </p>
                </div>
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Location
                    </span>
                    <p className="text-base font-semibold">
                        {job.location || '—'}
                    </p>
                </div>
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Salary
                    </span>
                    <p className="text-base font-semibold">
                        {job.salary_min !== null || job.salary_max !== null ? (
                            <>
                                {job.salary_currency} {job.salary_min ?? '—'} -{' '}
                                {job.salary_max ?? '—'} ({job.pay_period})
                                {job.salary_negotiable && ' (Negotiable)'}
                            </>
                        ) : (
                            'Not disclosed'
                        )}
                    </p>
                </div>
            </div>

            <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                <h2 className="text-lg font-semibold">Job Description</h2>
                <div className="prose max-w-none text-sm whitespace-pre-wrap text-foreground">
                    {job.description}
                </div>
            </div>

            {job.responsibilities && (
                <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                    <h2 className="text-lg font-semibold">Responsibilities</h2>
                    <div className="prose max-w-none text-sm whitespace-pre-wrap text-foreground">
                        {job.responsibilities}
                    </div>
                </div>
            )}

            {job.requirements && (
                <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                    <h2 className="text-lg font-semibold">Requirements</h2>
                    <div className="prose max-w-none text-sm whitespace-pre-wrap text-foreground">
                        {job.requirements}
                    </div>
                </div>
            )}
        </div>
    );
}
