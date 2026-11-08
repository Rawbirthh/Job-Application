import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Building, Building2, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    StatusHistoryTimeline,
    type StatusHistory,
} from '@/components/status-history-timeline';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { CandidateInterviews } from '@/components/interview/candidate-interviews';
import type {
    CandidateInterviewRound,
    InterviewPagination,
    ScreeningAnswer,
} from '@/types';
import type { Application } from '@/types/candidate';
import { ScreeningAnswers } from '@/components/screening/screening-answers';

interface Props {
    application: Application;
    can_withdraw: boolean;
    status_histories: StatusHistory[];
    job: {
        id: number;
        title: string;
        status: string;
        description: string;
        responsibilities: string | null;
        requirements: string | null;
        location: string | null;
        salary_min: string | null;
        salary_max: string | null;
        salary_currency: string | null;
        salary_negotiable: boolean;
        application_deadline: string | null;
        company: {
            id: number;
            name: string;
            logo_path: string | null;
            overview: string | null;
            website: string | null;
            location: string | null;
            industry: string | null;
            size_band: string | null;
        } | null;
        employment_type: { name: string } | null;
        workplace_arrangement: { name: string } | null;
        experience_level: { name: string } | null;
        pay_period: { name: string } | null;
    };
    timezones: string[];
    interviews: CandidateInterviewRound[];
    interview_pagination: InterviewPagination;
    screening_answers?: ScreeningAnswer[];
}

export default function CandidateApplicationShow({
    application,
    can_withdraw,
    status_histories,
    job,
    timezones,
    interviews,
    interview_pagination,
    screening_answers,
}: Props) {
    useFlashToast();
    const [withdrawOpen, setWithdrawOpen] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        reason: '',
    });

    const handleWithdraw = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/candidate/applications/${application.id}/withdraw`, {
            preserveScroll: true,
            onSuccess: () => setWithdrawOpen(false),
        });
    };

    const statusVariants: Record<
        string,
        'default' | 'secondary' | 'outline' | 'destructive'
    > = {
        submitted: 'default',
        reviewing: 'outline',
        shortlisted: 'outline',
        interview_scheduled: 'outline',
        offer_sent: 'outline',
        hired: 'default',
        rejected: 'destructive',
        withdrawn: 'secondary',
    };

    const formatStatus = (status: string) => {
        const statusMap: Record<string, string> = {
            submitted: 'Submitted',
            reviewing: 'Reviewing',
            shortlisted: 'Shortlisted',
            interview_scheduled: 'Interview Scheduled',
            offer_sent: 'Offer Sent',
            hired: 'Hired',
            rejected: 'Rejected',
            withdrawn: 'Withdrawn',
        };
        return statusMap[status] || status;
    };

    return (
        <div className="container mx-auto max-w-6xl px-4 py-8">
            <Head title={`Application: ${job.title}`} />

            <div className="mb-8 flex items-center justify-between">
                <div>
                    <Button
                        variant="ghost"
                        size="sm"
                        asChild
                        className="mb-2 -ml-2"
                    >
                        <Link href="/candidate/applications">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to My Applications
                        </Link>
                    </Button>
                    <h1 className="text-3xl font-bold tracking-tight">
                        {job.title}
                    </h1>
                    <div className="mt-1 flex flex-wrap items-center gap-3">
                        {job.company && (
                            <p className="flex items-center gap-2 text-muted-foreground">
                                <Building className="h-4 w-4" />
                                {job.company.name}
                            </p>
                        )}
                        {job.status === 'closed' && (
                            <Badge variant="destructive">Job Closed</Badge>
                        )}
                    </div>
                </div>
            </div>

            <div className="grid gap-8 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Application Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Status
                                </span>
                                <Badge
                                    variant={
                                        statusVariants[application.status] ||
                                        'default'
                                    }
                                >
                                    {formatStatus(application.status)}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Job Status
                                </span>
                                <Badge
                                    variant={
                                        job.status === 'closed'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                >
                                    {job.status === 'closed'
                                        ? 'Closed'
                                        : 'Active'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Applied Date
                                </span>
                                <span className="text-sm">
                                    {application.applied_at
                                        ? new Date(
                                              application.applied_at,
                                          ).toLocaleDateString('en-US', {
                                              year: 'numeric',
                                              month: 'long',
                                              day: 'numeric',
                                          })
                                        : 'N/A'}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <StatusHistoryTimeline histories={status_histories} />

                    {screening_answers !== undefined && (
                        <ScreeningAnswers answers={screening_answers ?? []} />
                    )}

                    <CandidateInterviews
                        timezones={timezones}
                        interviews={interviews}
                        pagination={interview_pagination}
                    />

                    {job.description && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Job Description</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="text-sm whitespace-pre-wrap">
                                    {job.description}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {job.company && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="size-5" />
                                    About {job.company.name}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {job.company.overview && (
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        {job.company.overview}
                                    </p>
                                )}
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    {job.company.industry && (
                                        <div>
                                            <span className="block font-medium text-muted-foreground">
                                                Industry
                                            </span>
                                            <span>{job.company.industry}</span>
                                        </div>
                                    )}
                                    {job.company.size_band && (
                                        <div>
                                            <span className="block font-medium text-muted-foreground">
                                                Size
                                            </span>
                                            <span>{job.company.size_band}</span>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="space-y-6 lg:col-span-1">
                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            <Button asChild variant="outline" size="sm">
                                <Link href={`/jobs/${job.id}`}>
                                    View Full Job Details
                                </Link>
                            </Button>

                            {can_withdraw && (
                                <Dialog
                                    open={withdrawOpen}
                                    onOpenChange={setWithdrawOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button variant="destructive" size="sm">
                                            Withdraw Application
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Withdraw Application
                                            </DialogTitle>
                                            <DialogDescription>
                                                Are you sure you want to
                                                withdraw your application for{' '}
                                                {job.title}? This action cannot
                                                be undone.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <form
                                            onSubmit={handleWithdraw}
                                            className="space-y-4"
                                        >
                                            <div className="space-y-2">
                                                <Label htmlFor="withdrawal_reason">
                                                    Reason (optional)
                                                </Label>
                                                <Textarea
                                                    id="withdrawal_reason"
                                                    value={data.reason}
                                                    onChange={(e) =>
                                                        setData(
                                                            'reason',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Provide a reason for withdrawal (optional)"
                                                />
                                                <InputError
                                                    message={errors.reason}
                                                />
                                            </div>
                                            <DialogFooter>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setWithdrawOpen(false)
                                                    }
                                                >
                                                    Cancel
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                >
                                                    {processing
                                                        ? 'Withdrawing...'
                                                        : 'Confirm Withdrawal'}
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </CardContent>
                    </Card>

                    {job.company?.website && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Company Website</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <a
                                    href={job.company.website}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-primary underline-offset-4 transition-colors hover:underline"
                                >
                                    Visit website
                                    <ExternalLink className="size-3" />
                                </a>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </div>
    );
}
