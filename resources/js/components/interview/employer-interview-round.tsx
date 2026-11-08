import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    formatInTimeZone,
    formatInterviewDateTime,
    getViewerTimeZone,
    sameTimeZone,
} from '@/lib/datetime';
import type { EmployerInterviewRound } from '@/types';
import { CancelInterviewForm } from './cancel-interview-form';
import { InterviewDetails } from './interview-details';
import { InterviewStatusBadge } from './interview-status-badge';
import { isActiveInterviewStatus } from './interview-utils';
import { ScheduleInterviewForm } from './schedule-interview-form';

type Props = {
    round: EmployerInterviewRound;
    timezones: string[];
};

export function EmployerInterviewRoundCard({ round, timezones }: Props) {
    const [rescheduleOpen, setRescheduleOpen] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const viewerTimeZone = getViewerTimeZone();
    const canManage = round.can_manage && isActiveInterviewStatus(round.status);
    const hasResponse =
        round.responded_at !== null ||
        round.requested_scheduled_at !== null ||
        round.response_note !== null ||
        round.status === 'accepted' ||
        round.status === 'declined' ||
        round.status === 'reschedule_requested';

    return (
        <div className="space-y-4 rounded-lg border border-border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold text-foreground">
                    Interview round
                </h3>
                <InterviewStatusBadge
                    status={round.status}
                    label={round.status_label}
                />
            </div>

            <InterviewDetails round={round} viewerTimeZone={viewerTimeZone} />

            {hasResponse && (
                <div className="space-y-2 rounded-md border border-border bg-muted/30 p-3 text-sm">
                    <p className="font-medium text-foreground">
                        Candidate response
                    </p>

                    {round.status === 'accepted' && (
                        <p className="text-foreground">
                            The candidate accepted this interview.
                        </p>
                    )}
                    {round.status === 'declined' && (
                        <p className="text-foreground">
                            The candidate declined this interview.
                        </p>
                    )}
                    {round.status === 'reschedule_requested' && (
                        <p className="text-foreground">
                            The candidate requested a new time.
                        </p>
                    )}

                    {round.responded_at && (
                        <p className="text-xs text-muted-foreground">
                            Responded{' '}
                            <time dateTime={round.responded_at}>
                                {formatInTimeZone(
                                    round.responded_at,
                                    viewerTimeZone,
                                    {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    },
                                )}
                            </time>{' '}
                            ({viewerTimeZone})
                        </p>
                    )}

                    {round.requested_scheduled_at &&
                        round.requested_timezone && (
                            <div className="space-y-1">
                                <p>
                                    <span className="text-muted-foreground">
                                        Proposed time:{' '}
                                    </span>
                                    <time
                                        dateTime={round.requested_scheduled_at}
                                        className="font-medium text-foreground"
                                    >
                                        {formatInterviewDateTime(
                                            round.requested_scheduled_at,
                                            round.requested_timezone,
                                        )}
                                    </time>{' '}
                                    <span className="text-xs text-muted-foreground">
                                        ({round.requested_timezone})
                                    </span>
                                </p>
                                {!sameTimeZone(
                                    round.requested_timezone,
                                    round.timezone,
                                ) && (
                                    <p className="text-xs text-muted-foreground">
                                        In your interview time zone (
                                        {round.timezone}):{' '}
                                        <time
                                            dateTime={
                                                round.requested_scheduled_at
                                            }
                                        >
                                            {formatInterviewDateTime(
                                                round.requested_scheduled_at,
                                                round.timezone,
                                            )}
                                        </time>
                                    </p>
                                )}
                            </div>
                        )}

                    {round.response_note && (
                        <p className="whitespace-pre-wrap text-foreground">
                            <span className="text-muted-foreground">
                                Candidate note:{' '}
                            </span>
                            {round.response_note}
                        </p>
                    )}
                </div>
            )}

            {round.cancelled_at && (
                <div className="space-y-1 rounded-md border border-border bg-muted/30 p-3 text-sm">
                    <p className="font-medium text-foreground">
                        Cancelled{' '}
                        <time dateTime={round.cancelled_at}>
                            {formatInTimeZone(
                                round.cancelled_at,
                                viewerTimeZone,
                                {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                },
                            )}
                        </time>
                    </p>
                    {round.cancellation_reason && (
                        <p className="whitespace-pre-wrap text-muted-foreground">
                            Reason: {round.cancellation_reason}
                        </p>
                    )}
                </div>
            )}

            {round.internal_notes && (
                <div className="space-y-1 rounded-md border border-dashed border-border bg-muted/30 p-3 text-sm">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Internal notes — not visible to the candidate
                    </p>
                    <p className="whitespace-pre-wrap text-foreground">
                        {round.internal_notes}
                    </p>
                </div>
            )}

            {canManage && (
                <div className="flex flex-wrap gap-2 border-t border-border pt-3">
                    <Dialog
                        open={rescheduleOpen}
                        onOpenChange={setRescheduleOpen}
                    >
                        <DialogTrigger asChild>
                            <Button type="button" variant="outline" size="sm">
                                Reschedule
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Reschedule interview</DialogTitle>
                                <DialogDescription>
                                    Choose a new time and update the meeting
                                    details. The round returns to “scheduled”
                                    and the candidate is notified.
                                </DialogDescription>
                            </DialogHeader>
                            <ScheduleInterviewForm
                                mode="update"
                                round={round}
                                timezones={timezones}
                                onSuccess={() => setRescheduleOpen(false)}
                                onCancel={() => setRescheduleOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>

                    <Dialog open={cancelOpen} onOpenChange={setCancelOpen}>
                        <DialogTrigger asChild>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                            >
                                Cancel interview
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Cancel interview</DialogTitle>
                                <DialogDescription>
                                    This cannot be undone. The candidate will
                                    receive the cancellation reason and the
                                    round will no longer be active.
                                </DialogDescription>
                            </DialogHeader>
                            <CancelInterviewForm
                                round={round}
                                onSuccess={() => setCancelOpen(false)}
                                onCancel={() => setCancelOpen(false)}
                            />
                        </DialogContent>
                    </Dialog>
                </div>
            )}
        </div>
    );
}
