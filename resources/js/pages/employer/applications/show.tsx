import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Briefcase,
    Building2,
    Calendar,
    Download,
    FileText,
    GraduationCap,
    Mail,
    MapPin,
    MessageSquare,
    User,
    Users,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    StatusHistoryTimeline,
    type StatusHistory,
} from '@/components/status-history-timeline';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EmployerInterviews } from '@/components/interview/employer-interviews';
import { ScreeningAnswers } from '@/components/screening/screening-answers';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type {
    EmployerInterviewRound,
    InterviewPagination,
    ScreeningAnswer,
} from '@/types';

interface Experience {
    id: number;
    job_title: string;
    company_name: string;
    location: string | null;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
    description: string | null;
}

interface Education {
    id: number;
    institution: string;
    degree: string | null;
    field_of_study: string | null;
    start_date: string | null;
    end_date: string | null;
    description: string | null;
}

interface Props {
    application: {
        id: number;
        status: string;
        applied_at: string | null;
        cover_letter: string | null;
        resume: {
            id: number;
            original_filename: string;
            mime_type: string;
            size: number;
        } | null;
    };
    allowed_transitions: { value: string; label: string }[];
    can_message?: boolean;
    status_histories: StatusHistory[];
    candidate: {
        id: number;
        name: string;
        email: string;
        headline: string | null;
        summary: string | null;
        location: string | null;
        skills: string[];
        experiences: Experience[];
        educations: Education[];
    };
    job: {
        id: number;
        title: string;
        status: string;
        company: {
            id: number;
            name: string;
            slug: string;
        } | null;
    };
    interview_eligible: boolean;
    can_update_status?: boolean;
    can_schedule_interview?: boolean;
    timezones: string[];
    interviews: EmployerInterviewRound[];
    interview_pagination: InterviewPagination;
    screening_answers?: ScreeningAnswer[];
}

export default function EmployerApplicationShow({
    application,
    allowed_transitions,
    can_message = false,
    status_histories,
    candidate,
    job,
    interview_eligible,
    can_update_status = true,
    can_schedule_interview = true,
    timezones,
    interviews,
    interview_pagination,
    screening_answers,
}: Props) {
    useFlashToast();

    const { data, setData, post, processing, errors } = useForm({
        current_status: application.status,
        new_status: '',
        reason: '',
    });
    const openConversation = useForm<Record<string, never>>({});

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/employer/applications/${application.id}/status`, {
            preserveScroll: true,
        });
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

    const statusBadgeVariant = (
        status: string,
    ): 'default' | 'secondary' | 'outline' | 'destructive' => {
        switch (status) {
            case 'submitted':
                return 'secondary';
            case 'reviewing':
            case 'shortlisted':
            case 'interview_scheduled':
            case 'offer_sent':
                return 'outline';
            case 'hired':
                return 'default';
            case 'rejected':
                return 'destructive';
            default:
                return 'outline';
        }
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    const formatDateRange = (
        start: string | null,
        end: string | null,
        isCurrent = false,
    ) => {
        const startFormatted = start
            ? new Date(start).toLocaleDateString('en-US', {
                  year: 'numeric',
                  month: 'short',
              })
            : '';
        const endFormatted = isCurrent
            ? 'Present'
            : end
              ? new Date(end).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                })
              : '';

        if (!startFormatted && !endFormatted) return null;
        return `${startFormatted} – ${endFormatted}`;
    };

    return (
        <>
            <Head title={`Application - ${candidate.name} - ${job.title}`} />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                {/* Back Link & Header */}
                <div className="flex flex-col gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        asChild
                        className="-ml-2 w-fit text-muted-foreground hover:text-foreground"
                    >
                        <Link href={`/employer/jobs/${job.id}/applicants`}>
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Applicants
                        </Link>
                    </Button>

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                                {candidate.name}
                            </h1>
                            <p className="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    {job.title}
                                </span>
                                {job.company && (
                                    <>
                                        <span>•</span>
                                        <span>{job.company.name}</span>
                                    </>
                                )}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {can_message && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={openConversation.processing}
                                    onClick={() =>
                                        openConversation.post(
                                            `/applications/${application.id}/conversation`,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <MessageSquare className="mr-2 size-4" />
                                    Messages
                                </Button>
                            )}
                            <Badge
                                variant={statusBadgeVariant(application.status)}
                                className="w-fit text-sm"
                            >
                                {formatStatus(application.status)}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Content Area */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Status Transition Form */}
                        {(allowed_transitions.length > 0 ||
                            !can_update_status) && (
                            <Card className="border-border">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-lg">
                                        Update Status
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={submit}
                                        className="space-y-4"
                                    >
                                        <div className="space-y-2">
                                            <Label htmlFor="new_status">
                                                New Status
                                            </Label>
                                            <Select
                                                value={data.new_status}
                                                onValueChange={(value) =>
                                                    setData('new_status', value)
                                                }
                                                disabled={!can_update_status}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select new status" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {allowed_transitions.map(
                                                        (t) => (
                                                            <SelectItem
                                                                key={t.value}
                                                                value={t.value}
                                                            >
                                                                {t.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.new_status}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="reason">
                                                Reason (optional)
                                            </Label>
                                            <Textarea
                                                id="reason"
                                                value={data.reason}
                                                onChange={(e) =>
                                                    setData(
                                                        'reason',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Enter reason for status change"
                                                disabled={!can_update_status}
                                            />
                                            <InputError
                                                message={errors.reason}
                                            />
                                        </div>

                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                !data.new_status ||
                                                !can_update_status
                                            }
                                        >
                                            {processing
                                                ? 'Updating...'
                                                : 'Update Status'}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}

                        {/* Status History Timeline */}
                        <StatusHistoryTimeline histories={status_histories} />

                        {screening_answers !== undefined && (
                            <ScreeningAnswers
                                answers={screening_answers ?? []}
                            />
                        )}

                        {/* Interview Rounds */}
                        <EmployerInterviews
                            applicationId={application.id}
                            interviewEligible={interview_eligible}
                            canScheduleInterview={can_schedule_interview}
                            timezones={timezones}
                            interviews={interviews}
                            pagination={interview_pagination}
                        />

                        {/* Candidate Summary Card */}
                        <Card className="border-border">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <User className="size-5 text-teal-600 dark:text-teal-400" />
                                    Candidate Profile
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <div className="text-base font-semibold text-foreground">
                                        {candidate.name}
                                    </div>
                                    {candidate.headline && (
                                        <div className="mt-0.5 text-sm font-medium text-muted-foreground">
                                            {candidate.headline}
                                        </div>
                                    )}
                                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-1">
                                            <Mail className="size-3.5" />
                                            <span>{candidate.email}</span>
                                        </div>
                                        {candidate.location && (
                                            <div className="flex items-center gap-1">
                                                <MapPin className="size-3.5" />
                                                <span>
                                                    {candidate.location}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {candidate.summary && (
                                    <div className="border-t border-border pt-2">
                                        <h4 className="mb-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            Professional Summary
                                        </h4>
                                        <p className="text-sm leading-relaxed whitespace-pre-wrap text-foreground">
                                            {candidate.summary}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Skills */}
                        {candidate.skills && candidate.skills.length > 0 && (
                            <Card className="border-border">
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-lg">
                                        Skills
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-1.5">
                                        {candidate.skills.map(
                                            (skill, index) => (
                                                <Badge
                                                    key={index}
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    {skill}
                                                </Badge>
                                            ),
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Work Experience */}
                        <Card className="border-border">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Briefcase className="size-5 text-teal-600 dark:text-teal-400" />
                                    Work Experience
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {candidate.experiences &&
                                candidate.experiences.length > 0 ? (
                                    <div className="space-y-4 divide-y divide-border">
                                        {candidate.experiences.map(
                                            (exp, idx) => (
                                                <div
                                                    key={exp.id}
                                                    className={
                                                        idx === 0 ? '' : 'pt-4'
                                                    }
                                                >
                                                    <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                        <div>
                                                            <h4 className="text-sm font-semibold text-foreground">
                                                                {exp.job_title}
                                                            </h4>
                                                            <div className="text-xs font-medium text-muted-foreground">
                                                                {
                                                                    exp.company_name
                                                                }
                                                                {exp.location &&
                                                                    ` • ${exp.location}`}
                                                            </div>
                                                        </div>
                                                        <span className="shrink-0 text-xs text-muted-foreground">
                                                            {formatDateRange(
                                                                exp.start_date,
                                                                exp.end_date,
                                                                exp.is_current,
                                                            )}
                                                        </span>
                                                    </div>
                                                    {exp.description && (
                                                        <p className="mt-2 text-xs leading-relaxed whitespace-pre-wrap text-foreground/90">
                                                            {exp.description}
                                                        </p>
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground italic">
                                        No work experience listed.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Education */}
                        <Card className="border-border">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <GraduationCap className="size-5 text-teal-600 dark:text-teal-400" />
                                    Education
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {candidate.educations &&
                                candidate.educations.length > 0 ? (
                                    <div className="space-y-4 divide-y divide-border">
                                        {candidate.educations.map(
                                            (edu, idx) => (
                                                <div
                                                    key={edu.id}
                                                    className={
                                                        idx === 0 ? '' : 'pt-4'
                                                    }
                                                >
                                                    <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                        <div>
                                                            <h4 className="text-sm font-semibold text-foreground">
                                                                {
                                                                    edu.institution
                                                                }
                                                            </h4>
                                                            <div className="text-xs font-medium text-muted-foreground">
                                                                {edu.degree}
                                                                {edu.field_of_study &&
                                                                    ` in ${edu.field_of_study}`}
                                                            </div>
                                                        </div>
                                                        <span className="shrink-0 text-xs text-muted-foreground">
                                                            {formatDateRange(
                                                                edu.start_date,
                                                                edu.end_date,
                                                            )}
                                                        </span>
                                                    </div>
                                                    {edu.description && (
                                                        <p className="mt-2 text-xs leading-relaxed whitespace-pre-wrap text-foreground/90">
                                                            {edu.description}
                                                        </p>
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground italic">
                                        No education history listed.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Cover Letter */}
                        {application.cover_letter && (
                            <Card className="border-border">
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <FileText className="size-5 text-teal-600 dark:text-teal-400" />
                                        Cover Letter
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="rounded-lg bg-muted/30 p-4 font-sans text-sm leading-relaxed whitespace-pre-wrap text-foreground">
                                        {application.cover_letter}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar Area */}
                    <div className="space-y-6">
                        {/* Application Meta Card */}
                        <Card className="border-border">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">
                                    Application Status
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">
                                        Current Status
                                    </span>
                                    <Badge
                                        variant={statusBadgeVariant(
                                            application.status,
                                        )}
                                    >
                                        {formatStatus(application.status)}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-muted-foreground">
                                        Applied Date
                                    </span>
                                    <span className="flex items-center gap-1 text-xs font-medium text-foreground">
                                        <Calendar className="size-3.5 text-muted-foreground" />
                                        {application.applied_at
                                            ? new Date(
                                                  application.applied_at,
                                              ).toLocaleDateString('en-US', {
                                                  year: 'numeric',
                                                  month: 'short',
                                                  day: 'numeric',
                                              })
                                            : 'N/A'}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Resume Download Card */}
                        <Card className="border-border">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <FileText className="size-4 text-teal-600 dark:text-teal-400" />
                                    Resume
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {application.resume ? (
                                    <>
                                        <div className="space-y-1 rounded-lg border border-border bg-muted/30 p-3">
                                            <div className="truncate text-xs font-medium text-foreground">
                                                {
                                                    application.resume
                                                        .original_filename
                                                }
                                            </div>
                                            <div className="text-[11px] text-muted-foreground">
                                                {formatFileSize(
                                                    application.resume.size,
                                                )}{' '}
                                                • {application.resume.mime_type}
                                            </div>
                                        </div>

                                        <Button
                                            asChild
                                            className="w-full"
                                            variant="default"
                                        >
                                            <a
                                                href={`/employer/applications/${application.id}/resume`}
                                                download
                                            >
                                                <Download className="mr-2 size-4" />
                                                Download Resume
                                            </a>
                                        </Button>
                                    </>
                                ) : (
                                    <p className="text-xs text-muted-foreground italic">
                                        No resume attached to this application.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Job Context Card */}
                        <Card className="border-border">
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Building2 className="size-4 text-muted-foreground" />
                                    Job Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div>
                                    <div className="text-sm font-semibold text-foreground">
                                        {job.title}
                                    </div>
                                    {job.company && (
                                        <div className="mt-0.5 text-xs text-muted-foreground">
                                            {job.company.name}
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-col gap-2 border-t border-border pt-2">
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="w-full justify-start text-xs"
                                    >
                                        <Link
                                            href={`/employer/jobs/${job.id}/applicants`}
                                        >
                                            <Users className="mr-2 size-3.5" />
                                            All Applicants for this Job
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        variant="ghost"
                                        size="sm"
                                        className="w-full justify-start text-xs text-muted-foreground"
                                    >
                                        <Link href={`/jobs/${job.id}`}>
                                            View Public Job Listing
                                        </Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
