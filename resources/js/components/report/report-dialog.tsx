'use client';

import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Flag } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import AlertError from '@/components/alert-error';
import InputError from '@/components/input-error';
import { store as reportsStore } from '@/routes/reports';
import type {
    Auth,
    ReportDialogForm,
    ReportDialogProps,
    ReportReasonValue,
} from '@/types';
import { REPORT_REASONS } from '@/types/report';

/**
 * Public report affordance for a job detail page.
 *
 * Shows a compact "Report" trigger (replaceable via `children`); the dialog
 * itself submits `reports.store`. Guests get a sign-in prompt instead of the
 * form — reporting requires an authenticated account.
 */
export default function ReportDialog({ job, children }: ReportDialogProps) {
    const [open, setOpen] = useState(false);
    const { auth } = usePage<{ auth: Auth }>().props;

    const form = useForm<ReportDialogForm>({
        subject: 'job',
        reason: '',
        explanation: '',
    });

    const hasCompany = job.company !== null && job.company.id > 0;

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        // Send only the field set the backend validates for the chosen subject
        // (validated mapping, never a raw form spread).
        form.transform((data) => ({
            subject: data.subject,
            reason: data.reason,
            explanation: data.explanation,
            ...(data.subject === 'job'
                ? { job_posting_id: job.id }
                : { company_id: job.company?.id ?? null }),
        }));

        form.post(reportsStore.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    const nonFieldErrors = [
        (form.errors as Record<string, string | undefined>).report,
        (form.errors as Record<string, string | undefined>).reports,
    ].filter((message): message is string => typeof message === 'string');

    const reasonId = 'report-reason';
    const explanationId = 'report-explanation';

    return (
        <>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild>
                    {children ?? (
                        <Button variant="ghost" size="sm">
                            <Flag className="size-4" />
                            Report
                        </Button>
                    )}
                </DialogTrigger>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Report this listing</DialogTitle>
                        <DialogDescription>
                            Tell us what looks wrong about the job or the
                            employer. Our moderation team reviews every report.
                        </DialogDescription>
                    </DialogHeader>

                    {!auth.user ? (
                        <div className="space-y-4 text-sm">
                            <p className="font-medium text-foreground">
                                Sign in to report this job
                            </p>
                            <p className="text-muted-foreground">
                                Reporting requires an account so our team can
                                follow up on reports when needed.
                            </p>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button asChild>
                                    <Link href="/login">Sign in to report</Link>
                                </Button>
                            </DialogFooter>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-5">
                            {nonFieldErrors.length > 0 && (
                                <AlertError
                                    errors={nonFieldErrors}
                                    title="Report could not be submitted."
                                />
                            )}

                            <fieldset className="space-y-3">
                                <legend className="text-sm font-medium text-foreground">
                                    What are you reporting?
                                </legend>
                                <div className="flex items-start gap-3">
                                    <input
                                        id="report-subject-job"
                                        name="report-subject"
                                        type="radio"
                                        value="job"
                                        checked={form.data.subject === 'job'}
                                        onChange={() =>
                                            form.setData('subject', 'job')
                                        }
                                        disabled={form.processing}
                                        className="mt-0.5"
                                    />
                                    <div>
                                        <Label
                                            htmlFor="report-subject-job"
                                            className="font-normal"
                                        >
                                            Report this job
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            {job.title}
                                        </p>
                                    </div>
                                </div>
                                {hasCompany && (
                                    <div className="flex items-start gap-3">
                                        <input
                                            id="report-subject-company"
                                            name="report-subject"
                                            type="radio"
                                            value="company"
                                            checked={
                                                form.data.subject === 'company'
                                            }
                                            onChange={() =>
                                                form.setData(
                                                    'subject',
                                                    'company',
                                                )
                                            }
                                            disabled={form.processing}
                                            className="mt-0.5"
                                        />
                                        <div>
                                            <Label
                                                htmlFor="report-subject-company"
                                                className="font-normal"
                                            >
                                                Report this employer
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                {job.company?.name}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                <InputError message={form.errors.subject} />
                            </fieldset>

                            <div className="space-y-2">
                                <Label htmlFor={reasonId}>Reason</Label>
                                <Select
                                    value={
                                        form.data.reason === ''
                                            ? undefined
                                            : form.data.reason
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'reason',
                                            value as ReportReasonValue,
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger
                                        id={reasonId}
                                        className="w-full"
                                        aria-invalid={
                                            form.errors.reason
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby={
                                            form.errors.reason
                                                ? `${reasonId}-error`
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Select a reason" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {REPORT_REASONS.map((reason) => (
                                            <SelectItem
                                                key={reason.value}
                                                value={reason.value}
                                            >
                                                {reason.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id={`${reasonId}-error`}
                                    message={form.errors.reason}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={explanationId}>
                                    Explanation (optional)
                                </Label>
                                <Textarea
                                    id={explanationId}
                                    value={form.data.explanation}
                                    onChange={(event) =>
                                        form.setData(
                                            'explanation',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Add any details that help the review, up to 2000 characters"
                                    rows={4}
                                    maxLength={2000}
                                    disabled={form.processing}
                                    aria-invalid={
                                        form.errors.explanation
                                            ? true
                                            : undefined
                                    }
                                    aria-describedby={
                                        form.errors.explanation
                                            ? `${explanationId}-error`
                                            : `${explanationId}-hint`
                                    }
                                />
                                <p
                                    id={`${explanationId}-hint`}
                                    className="text-xs text-muted-foreground"
                                >
                                    {form.data.explanation.length} / 2000
                                    characters
                                </p>
                                <InputError
                                    id={`${explanationId}-error`}
                                    message={form.errors.explanation}
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                    disabled={form.processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Submit report
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
