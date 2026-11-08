import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatInterviewDateTime } from '@/lib/datetime';
import type {
    CandidateInterviewRound,
    InterviewDecisionFormData,
    InterviewRescheduleRequestFormData,
} from '@/types';
import { GlobalFormErrors } from './global-form-errors';
import {
    resolveDefaultTimeZone,
    resolveTimeZoneOptions,
} from './interview-utils';

type Props = {
    round: CandidateInterviewRound;
    timezones: string[];
};

function requestInitialData(
    round: CandidateInterviewRound,
    timezones: string[],
): InterviewRescheduleRequestFormData {
    return {
        response: 'reschedule_requested',
        current_status: round.status,
        current_scheduled_at: round.scheduled_at,
        requested_at_local: '',
        requested_timezone: resolveDefaultTimeZone(timezones),
        response_note: '',
    };
}

export function CandidateInterviewActions({ round, timezones }: Props) {
    const [declineOpen, setDeclineOpen] = useState(false);
    const [requestOpen, setRequestOpen] = useState(false);

    const acceptForm = useForm<InterviewDecisionFormData>({
        response: 'accepted',
        current_status: round.status,
        current_scheduled_at: round.scheduled_at,
    });

    const declineForm = useForm<InterviewDecisionFormData>({
        response: 'declined',
        current_status: round.status,
        current_scheduled_at: round.scheduled_at,
    });

    const requestForm = useForm<InterviewRescheduleRequestFormData>(
        requestInitialData(round, timezones),
    );

    const busy =
        acceptForm.processing ||
        declineForm.processing ||
        requestForm.processing;
    const respondUrl = `/candidate/interviews/${round.id}/respond`;
    const requestTimeZoneOptions = resolveTimeZoneOptions(
        timezones,
        requestForm.data.requested_timezone,
    );

    const submitAccept = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const currentStatus = round.status;
        const currentScheduledAt = round.scheduled_at;

        acceptForm.transform(() => ({
            response: 'accepted',
            current_status: currentStatus,
            current_scheduled_at: currentScheduledAt,
        }));

        acceptForm.post(respondUrl, { preserveScroll: true });
    };

    const submitDecline = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const currentStatus = round.status;
        const currentScheduledAt = round.scheduled_at;

        declineForm.transform(() => ({
            response: 'declined',
            current_status: currentStatus,
            current_scheduled_at: currentScheduledAt,
        }));

        declineForm.post(respondUrl, {
            preserveScroll: true,
            onSuccess: () => setDeclineOpen(false),
        });
    };

    const submitRequest = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const currentStatus = round.status;
        const currentScheduledAt = round.scheduled_at;

        requestForm.transform((data) => ({
            ...data,
            current_status: currentStatus,
            current_scheduled_at: currentScheduledAt,
        }));

        requestForm.post(respondUrl, {
            preserveScroll: true,
            onSuccess: () => setRequestOpen(false),
        });
    };

    const handleRequestOpenChange = (open: boolean) => {
        setRequestOpen(open);

        if (open) {
            requestForm.setData(requestInitialData(round, timezones));
            requestForm.clearErrors();
        }
    };

    const requestedAtId = `candidate-interview-${round.id}-requested-at`;
    const requestedTimeZoneId = `candidate-interview-${round.id}-requested-timezone`;
    const responseNoteId = `candidate-interview-${round.id}-response-note`;

    return (
        <div className="space-y-3 border-t border-border pt-3">
            <div className="flex flex-wrap items-center gap-2">
                <form onSubmit={submitAccept}>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={busy}
                        aria-busy={acceptForm.processing || undefined}
                    >
                        {acceptForm.processing && <Spinner />}
                        {acceptForm.processing ? 'Accepting...' : 'Accept'}
                    </Button>
                </form>

                <Dialog open={declineOpen} onOpenChange={setDeclineOpen}>
                    <DialogTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={busy}
                        >
                            Decline
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Decline interview</DialogTitle>
                            <DialogDescription>
                                Decline the interview on{' '}
                                {formatInterviewDateTime(
                                    round.scheduled_at,
                                    round.timezone,
                                )}{' '}
                                ({round.timezone})? The employer will be
                                notified.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submitDecline} className="space-y-4">
                            <GlobalFormErrors
                                errors={[
                                    declineForm.errors.response,
                                    declineForm.errors.current_status,
                                    declineForm.errors.current_scheduled_at,
                                ]}
                                title="Your response could not be sent. Refresh the page and try again."
                            />
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setDeclineOpen(false)}
                                    disabled={declineForm.processing}
                                >
                                    Keep interview
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={declineForm.processing}
                                >
                                    {declineForm.processing && <Spinner />}
                                    {declineForm.processing
                                        ? 'Declining...'
                                        : 'Decline interview'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={requestOpen}
                    onOpenChange={handleRequestOpenChange}
                >
                    <DialogTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={busy}
                        >
                            Request a new time
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Request a new time</DialogTitle>
                            <DialogDescription>
                                Propose one alternative time for this round. The
                                employer decides whether to reschedule.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submitRequest} className="space-y-4">
                            <GlobalFormErrors
                                errors={[
                                    requestForm.errors.response,
                                    requestForm.errors.current_status,
                                    requestForm.errors.current_scheduled_at,
                                ]}
                                title="Your request could not be sent. Refresh the page and try again."
                            />

                            <div className="space-y-2">
                                <Label htmlFor={requestedAtId}>
                                    Proposed date and time
                                </Label>
                                <Input
                                    id={requestedAtId}
                                    type="datetime-local"
                                    value={requestForm.data.requested_at_local}
                                    onChange={(event) =>
                                        requestForm.setData(
                                            'requested_at_local',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    step={60}
                                    disabled={requestForm.processing}
                                    aria-invalid={
                                        requestForm.errors.requested_at_local
                                            ? true
                                            : undefined
                                    }
                                    aria-describedby={
                                        requestForm.errors.requested_at_local
                                            ? `${requestedAtId}-error`
                                            : undefined
                                    }
                                />
                                <InputError
                                    id={`${requestedAtId}-error`}
                                    message={
                                        requestForm.errors.requested_at_local
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={requestedTimeZoneId}>
                                    Time zone
                                </Label>
                                <Select
                                    value={requestForm.data.requested_timezone}
                                    onValueChange={(value) =>
                                        requestForm.setData(
                                            'requested_timezone',
                                            value,
                                        )
                                    }
                                    disabled={requestForm.processing}
                                >
                                    <SelectTrigger
                                        id={requestedTimeZoneId}
                                        className="w-full"
                                        aria-invalid={
                                            requestForm.errors
                                                .requested_timezone
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby={
                                            requestForm.errors
                                                .requested_timezone
                                                ? `${requestedTimeZoneId}-error`
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Select a time zone" />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-72">
                                        {requestTimeZoneOptions.map(
                                            (timeZone) => (
                                                <SelectItem
                                                    key={timeZone}
                                                    value={timeZone}
                                                >
                                                    {timeZone}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id={`${requestedTimeZoneId}-error`}
                                    message={
                                        requestForm.errors.requested_timezone
                                    }
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor={responseNoteId}>
                                    Note (optional)
                                </Label>
                                <Textarea
                                    id={responseNoteId}
                                    value={requestForm.data.response_note}
                                    onChange={(event) =>
                                        requestForm.setData(
                                            'response_note',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Add context for the employer"
                                    rows={3}
                                    maxLength={500}
                                    disabled={requestForm.processing}
                                    aria-invalid={
                                        requestForm.errors.response_note
                                            ? true
                                            : undefined
                                    }
                                    aria-describedby={
                                        requestForm.errors.response_note
                                            ? `${responseNoteId}-error`
                                            : undefined
                                    }
                                />
                                <InputError
                                    id={`${responseNoteId}-error`}
                                    message={requestForm.errors.response_note}
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setRequestOpen(false)}
                                    disabled={requestForm.processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={requestForm.processing}
                                >
                                    {requestForm.processing && <Spinner />}
                                    {requestForm.processing
                                        ? 'Sending request...'
                                        : 'Send request'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <GlobalFormErrors
                errors={[
                    acceptForm.errors.response,
                    acceptForm.errors.current_status,
                    acceptForm.errors.current_scheduled_at,
                ]}
                title="Your response could not be sent. Refresh the page and try again."
            />
        </div>
    );
}
