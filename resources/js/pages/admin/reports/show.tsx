import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as reportsIndex,
    investigate as reportInvestigate,
    resolve as reportResolve,
    dismiss as reportDismiss,
} from '@/routes/admin/reports';
import type {
    AdminReportShowProps,
    DismissReportForm,
    ResolveReportForm,
} from '@/types';
import {
    reportReasonLabel,
    reportResolutionActionLabel,
    reportStatusLabel,
    reportSubjectLabel,
    REPORT_RESOLUTION_ACTIONS,
} from '@/types/report';

export default function AdminReportShow({ report }: AdminReportShowProps) {
    const [showResolveForm, setShowResolveForm] = useState(false);
    const [showDismissForm, setShowDismissForm] = useState(false);

    const investigateForm = useForm({});

    const resolveForm = useForm<ResolveReportForm>({
        resolution_action: '',
        resolution_message: '',
        internal_note: report.internal_note ?? '',
    });

    const dismissForm = useForm<DismissReportForm>({
        internal_note: report.internal_note ?? '',
    });

    const isOpen =
        report.status === 'pending' || report.status === 'investigating';

    const handleInvestigate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        investigateForm.post(reportInvestigate.url({ report: report.id }));
    };

    const handleResolve = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        resolveForm.post(reportResolve.url({ report: report.id }));
    };

    const handleDismiss = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        dismissForm.post(reportDismiss.url({ report: report.id }));
    };

    const subject = report.subject;
    const isJobSubject = subject.type === 'job';
    const isCompanySubject = subject.type === 'company';

    return (
        <div className="max-w-4xl space-y-6 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <h1 className="text-2xl font-bold tracking-tight">
                            Report #{report.id}
                        </h1>
                        <Badge variant="outline">
                            {reportStatusLabel(report.status)}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {reportSubjectLabel(subject.type)}:{' '}
                        {subject.title || '—'}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={reportsIndex.url()}>Back to Queue</Link>
                    </Button>
                    {report.status === 'pending' && (
                        <form onSubmit={handleInvestigate}>
                            <Button
                                type="submit"
                                disabled={investigateForm.processing}
                            >
                                Start investigation
                            </Button>
                        </form>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-1 gap-6 rounded-lg border border-border p-6 shadow-sm sm:grid-cols-2">
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Report type
                    </span>
                    <p className="text-base font-semibold">
                        {reportSubjectLabel(subject.type)}
                    </p>
                </div>
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Reason
                    </span>
                    <p className="text-base font-semibold">
                        {reportReasonLabel(report.reason)}
                    </p>
                </div>
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Subject status
                    </span>
                    <p className="text-base font-semibold">
                        {subject.status || '—'}
                    </p>
                </div>
                <div>
                    <span className="text-sm font-medium text-muted-foreground">
                        Submitted
                    </span>
                    <p className="text-base font-semibold">
                        {report.created_at
                            ? new Date(report.created_at).toLocaleDateString()
                            : '—'}
                    </p>
                </div>
                <div className="sm:col-span-2">
                    <span className="text-sm font-medium text-muted-foreground">
                        Reporter
                    </span>
                    <p className="text-base font-semibold">
                        {report.reporter.name}{' '}
                        <span className="font-normal text-muted-foreground">
                            ({report.reporter.email})
                        </span>
                    </p>
                </div>
                {report.internal_note && (
                    <div className="sm:col-span-2">
                        <span className="text-sm font-medium text-muted-foreground">
                            Internal note
                        </span>
                        <p className="mt-1 text-sm whitespace-pre-wrap">
                            {report.internal_note}
                        </p>
                    </div>
                )}
            </div>

            {/* Subject context */}
            <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                <h2 className="text-lg font-semibold">Reported subject</h2>
                <div className="space-y-2 text-sm">
                    <p className="flex items-center gap-2">
                        <Badge variant="outline">
                            {reportSubjectLabel(subject.type)}
                        </Badge>
                        {subject.title || '—'}
                    </p>
                    {isJobSubject && (
                        <p className="text-muted-foreground">
                            View the public job listing:{' '}
                            <Link
                                href={`/jobs/${subject.id}`}
                                className="text-primary underline-offset-4 hover:underline"
                            >
                                {`/jobs/${subject.id}`}
                            </Link>
                        </p>
                    )}
                    {isCompanySubject && (
                        <p className="text-muted-foreground">
                            {subject.owner
                                ? `Owner: ${subject.owner.name} (${subject.owner.email})`
                                : 'No owner information available.'}
                        </p>
                    )}
                    {isJobSubject && subject.company && (
                        <p className="text-muted-foreground">
                            Company: {subject.company.name} (
                            {subject.company.is_active ? 'active' : 'inactive'})
                            {subject.company.owner
                                ? `, owner ${subject.company.owner.name} (${subject.company.owner.email})`
                                : ''}
                        </p>
                    )}
                </div>
            </div>

            {/* Explanation from the reporter */}
            <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                <h2 className="text-lg font-semibold">Explanation</h2>
                <div className="prose max-w-none text-sm whitespace-pre-wrap text-foreground">
                    {report.explanation || 'No explanation provided.'}
                </div>
            </div>

            {/* Resolution details for terminal outcomes */}
            {(report.status === 'resolved' ||
                report.status === 'dismissed') && (
                <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                    <h2 className="text-lg font-semibold">Outcome</h2>
                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <span className="text-sm font-medium text-muted-foreground">
                                Resolution action
                            </span>
                            <p className="text-base font-semibold">
                                {report.resolution_action
                                    ? reportResolutionActionLabel(
                                          report.resolution_action,
                                      )
                                    : '—'}
                            </p>
                        </div>
                        <div>
                            <span className="text-sm font-medium text-muted-foreground">
                                Resolved
                            </span>
                            <p className="text-base font-semibold">
                                {report.resolved_at
                                    ? new Date(
                                          report.resolved_at,
                                      ).toLocaleDateString()
                                    : '—'}
                            </p>
                        </div>
                        {report.resolution_message && (
                            <div className="sm:col-span-2">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Message sent to the reporter
                                </span>
                                <p className="mt-1 text-sm whitespace-pre-wrap">
                                    {report.resolution_message}
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Moderation actions for open reports */}
            {isOpen && (
                <div className="space-y-4">
                    {showResolveForm && (
                        <form
                            onSubmit={handleResolve}
                            className="space-y-4 rounded-md border p-6"
                        >
                            <h2 className="text-lg font-semibold">
                                Resolve report
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Resolving closes this report and notifies the
                                reporter.
                            </p>
                            <div className="space-y-2">
                                <Label htmlFor="resolution_action">
                                    Resolution action
                                </Label>
                                <Select
                                    value={
                                        resolveForm.data.resolution_action ===
                                        ''
                                            ? undefined
                                            : resolveForm.data.resolution_action
                                    }
                                    onValueChange={(value) =>
                                        resolveForm.setData(
                                            'resolution_action',
                                            value,
                                        )
                                    }
                                    disabled={resolveForm.processing}
                                >
                                    <SelectTrigger
                                        id="resolution_action"
                                        className="w-full"
                                        aria-invalid={
                                            resolveForm.errors.resolution_action
                                                ? true
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Select an action" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {REPORT_RESOLUTION_ACTIONS.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={
                                        resolveForm.errors.resolution_action
                                    }
                                    className="mt-2"
                                />
                            </div>
                            <div>
                                <Label htmlFor="resolution_message">
                                    Message to the reporter (optional)
                                </Label>
                                <Textarea
                                    id="resolution_message"
                                    value={resolveForm.data.resolution_message}
                                    onChange={(e) =>
                                        resolveForm.setData(
                                            'resolution_message',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Note the reporter will see when the report is resolved..."
                                    rows={3}
                                    maxLength={1000}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {resolveForm.data.resolution_message.length}{' '}
                                    / 1000 characters
                                </p>
                                <InputError
                                    message={
                                        resolveForm.errors.resolution_message
                                    }
                                    className="mt-2"
                                />
                            </div>
                            <div>
                                <Label htmlFor="resolve_internal_note">
                                    Internal note (optional)
                                </Label>
                                <Textarea
                                    id="resolve_internal_note"
                                    value={resolveForm.data.internal_note}
                                    onChange={(e) =>
                                        resolveForm.setData(
                                            'internal_note',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Visible to moderators only..."
                                    rows={3}
                                    maxLength={2000}
                                />
                                <InputError
                                    message={resolveForm.errors.internal_note}
                                    className="mt-2"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowResolveForm(false)}
                                    disabled={resolveForm.processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    className="bg-green-600 hover:bg-green-700"
                                    disabled={resolveForm.processing}
                                >
                                    Confirm Resolution
                                </Button>
                            </div>
                        </form>
                    )}

                    {showDismissForm && (
                        <form
                            onSubmit={handleDismiss}
                            className="space-y-4 rounded-md border bg-muted/30 p-6"
                        >
                            <h2 className="text-lg font-semibold">
                                Dismiss report
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Dismissing closes this report without further
                                action; the reporter is notified.
                            </p>
                            <div>
                                <Label htmlFor="dismiss_internal_note">
                                    Internal note (optional)
                                </Label>
                                <Textarea
                                    id="dismiss_internal_note"
                                    value={dismissForm.data.internal_note}
                                    onChange={(e) =>
                                        dismissForm.setData(
                                            'internal_note',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Why this report is being dismissed (visible to moderators only)..."
                                    rows={3}
                                    maxLength={2000}
                                />
                                <InputError
                                    message={dismissForm.errors.internal_note}
                                    className="mt-2"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowDismissForm(false)}
                                    disabled={dismissForm.processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={dismissForm.processing}
                                >
                                    Confirm Dismissal
                                </Button>
                            </div>
                        </form>
                    )}

                    {!showResolveForm && !showDismissForm && (
                        <div className="flex gap-2">
                            <Button
                                onClick={() => {
                                    setShowResolveForm(true);
                                    setShowDismissForm(false);
                                }}
                                className="bg-green-600 hover:bg-green-700"
                            >
                                Resolve
                            </Button>
                            <Button
                                onClick={() => {
                                    setShowDismissForm(true);
                                    setShowResolveForm(false);
                                }}
                                variant="destructive"
                            >
                                Dismiss
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
