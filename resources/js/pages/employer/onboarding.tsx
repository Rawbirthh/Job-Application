import { Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, ShieldAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFlashToast } from '@/hooks/use-flash-toast';
import {
    index as employerOnboardingIndex,
    store as employerOnboardingStore,
} from '@/routes/employer/onboarding';
import employer from '@/routes/employer';
import type { EmployerOnboardingPageProps } from '@/types';

export default function EmployerOnboarding({
    status,
    request,
    suspension_reason,
    can_apply,
    can_access_employer_dashboard,
}: EmployerOnboardingPageProps) {
    useFlashToast();

    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const form = useForm({
        company_name: request?.company_name ?? '',
        company_website: request?.company_website ?? '',
        job_title: request?.job_title ?? '',
        message: request?.message ?? '',
    });

    const isPending = status === 'pending';
    const isApproved = status === 'approved';
    const isRejected = status === 'rejected';
    const isSuspended = status === 'suspended';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(employerOnboardingStore.url(), { preserveScroll: true });
    };

    const statusBadgeClass = (value: string | null): string => {
        switch (value) {
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
    };

    return (
        <>
            <Head title="Employer Onboarding" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Employer Onboarding
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Request employer capability for your account. An
                        administrator reviews each application before granting
                        access.
                    </p>
                </div>

                {isApproved && (
                    <Alert>
                        <CheckCircle2 className="size-4" />
                        <AlertTitle>Application approved</AlertTitle>
                        <AlertDescription>
                            Your employer access is active. You can now manage
                            your company profile and job postings.
                            {can_access_employer_dashboard && (
                                <div className="mt-3">
                                    <Button asChild>
                                        <Link href={employer.dashboard()}>
                                            Go to employer dashboard
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {isSuspended && (
                    <Alert variant="destructive">
                        <ShieldAlert className="size-4" />
                        <AlertTitle>Employer access suspended</AlertTitle>
                        <AlertDescription>
                            <p>
                                Your employer capability has been suspended. The
                                reason provided by the administrator is shown
                                below.
                            </p>
                            {suspension_reason && (
                                <p className="mt-2 font-medium">
                                    {suspension_reason}
                                </p>
                            )}
                            <p className="mt-2 text-sm">
                                Employer actions are unavailable until your
                                access is reactivated. You can still use your
                                candidate account.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {isPending && request && (
                    <Card className="border-border">
                        <CardHeader>
                            <div className="flex items-center justify-between gap-2">
                                <CardTitle className="text-lg">
                                    Application under review
                                </CardTitle>
                                <Badge
                                    variant="default"
                                    className={statusBadgeClass(status)}
                                >
                                    {status}
                                </Badge>
                            </div>
                            <CardDescription>
                                Submitted on{' '}
                                {new Date(
                                    request.submitted_at,
                                ).toLocaleString()}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2 text-sm">
                                <div>
                                    <span className="font-medium">
                                        Company:
                                    </span>{' '}
                                    {request.company_name}
                                </div>
                                <div>
                                    <span className="font-medium">Role:</span>{' '}
                                    {request.job_title}
                                </div>
                                {request.company_website && (
                                    <div>
                                        <span className="font-medium">
                                            Website:
                                        </span>{' '}
                                        {request.company_website}
                                    </div>
                                )}
                                {request.message && (
                                    <div>
                                        <span className="font-medium">
                                            Message:
                                        </span>{' '}
                                        {request.message}
                                    </div>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                You cannot submit another application while this
                                one is under review. We will update this page
                                when a decision is made.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {isRejected && request?.review_note && (
                    <Alert variant="destructive">
                        <ShieldAlert className="size-4" />
                        <AlertTitle>Application not approved</AlertTitle>
                        <AlertDescription>
                            <p className="font-medium">{request.review_note}</p>
                            <p className="mt-2 text-sm">
                                You may revise and resubmit your application
                                below.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {(status === null || isRejected) && can_apply && (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                {isRejected
                                    ? 'Resubmit your application'
                                    : 'Apply for employer access'}
                            </CardTitle>
                            <CardDescription>
                                Tell us about the company you represent. An
                                administrator reviews every request.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={handleSubmit}
                                className="space-y-4"
                                aria-busy={form.processing}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="company_name">
                                        Company name{' '}
                                        <span
                                            className="text-red-600"
                                            aria-hidden="true"
                                        >
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="company_name"
                                        value={form.data.company_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'company_name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        minLength={2}
                                        maxLength={150}
                                        disabled={form.processing}
                                        aria-invalid={
                                            form.errors.company_name ||
                                            errors.company_name
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby={
                                            form.errors.company_name ||
                                            errors.company_name
                                                ? 'company_name-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="company_name-error"
                                        message={
                                            form.errors.company_name ??
                                            errors.company_name
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="company_website">
                                        Company website (optional)
                                    </Label>
                                    <Input
                                        id="company_website"
                                        type="url"
                                        value={form.data.company_website}
                                        onChange={(e) =>
                                            form.setData(
                                                'company_website',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={255}
                                        placeholder="https://example.com"
                                        disabled={form.processing}
                                        aria-invalid={
                                            form.errors.company_website ||
                                            errors.company_website
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby="company_website-help company_website-error"
                                    />
                                    <p
                                        id="company_website-help"
                                        className="text-xs text-muted-foreground"
                                    >
                                        Include the https:// prefix.
                                    </p>
                                    <InputError
                                        id="company_website-error"
                                        message={
                                            form.errors.company_website ??
                                            errors.company_website
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="job_title">
                                        Your role at the company{' '}
                                        <span
                                            className="text-red-600"
                                            aria-hidden="true"
                                        >
                                            *
                                        </span>
                                    </Label>
                                    <Input
                                        id="job_title"
                                        value={form.data.job_title}
                                        onChange={(e) =>
                                            form.setData(
                                                'job_title',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        minLength={2}
                                        maxLength={100}
                                        disabled={form.processing}
                                        aria-invalid={
                                            form.errors.job_title ||
                                            errors.job_title
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby={
                                            form.errors.job_title ||
                                            errors.job_title
                                                ? 'job_title-error'
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="job_title-error"
                                        message={
                                            form.errors.job_title ??
                                            errors.job_title
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="message">
                                        Message (optional)
                                    </Label>
                                    <Textarea
                                        id="message"
                                        value={form.data.message}
                                        onChange={(e) =>
                                            form.setData(
                                                'message',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={1000}
                                        rows={4}
                                        disabled={form.processing}
                                        aria-invalid={
                                            form.errors.message ||
                                            errors.message
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby="message-error"
                                    />
                                    <InputError
                                        id="message-error"
                                        message={
                                            form.errors.message ??
                                            errors.message
                                        }
                                    />
                                </div>

                                <div className="flex items-center gap-2 pt-2">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing && (
                                            <Spinner className="mr-2" />
                                        )}
                                        {isRejected
                                            ? 'Resubmit application'
                                            : 'Submit application'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {!can_apply && !isPending && !isApproved && !isRejected && (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Status unavailable
                            </CardTitle>
                            <CardDescription>
                                You cannot submit a new application right now.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}

                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Clock className="size-4" aria-hidden="true" />
                    <span>
                        Review times vary. This page reflects your current
                        status.
                    </span>
                </div>
            </div>
        </>
    );
}

EmployerOnboarding.layout = {
    breadcrumbs: [
        {
            title: 'Employer Onboarding',
            href: employerOnboardingIndex(),
        },
    ],
};
