import {
    formatInTimeZone,
    formatInterviewDateTime,
    getViewerTimeZone,
    sameTimeZone,
} from '@/lib/datetime';
import type { CandidateInterviewRound } from '@/types';
import { CandidateInterviewActions } from './candidate-interview-actions';
import { InterviewDetails } from './interview-details';
import { InterviewStatusBadge } from './interview-status-badge';

type Props = {
    round: CandidateInterviewRound;
    timezones: string[];
};

export function CandidateInterviewRoundCard({ round, timezones }: Props) {
    const viewerTimeZone = getViewerTimeZone();
    const hasResponse =
        round.responded_at !== null ||
        round.requested_scheduled_at !== null ||
        round.response_note !== null;

    return (
        <div className="space-y-4 rounded-lg border border-border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold text-foreground">
                    Interview round
                </h3>
                <InterviewStatusBadge
                    status={round.status}
                    label={
                        round.can_respond && round.status === 'scheduled'
                            ? 'Awaiting your response'
                            : round.status_label
                    }
                />
            </div>

            <InterviewDetails round={round} viewerTimeZone={viewerTimeZone} />

            {hasResponse && (
                <div className="space-y-2 rounded-md border border-border bg-muted/30 p-3 text-sm">
                    <p className="font-medium text-foreground">Your response</p>

                    {round.status === 'accepted' && (
                        <p className="text-foreground">
                            You accepted this interview.
                        </p>
                    )}
                    {round.status === 'declined' && (
                        <p className="text-foreground">
                            You declined this interview.
                        </p>
                    )}
                    {round.status === 'reschedule_requested' && (
                        <p className="text-foreground">
                            You requested a new time for this interview.
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
                                        Employer&apos;s time zone (
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
                                Your note:{' '}
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

            {round.can_respond && (
                <CandidateInterviewActions
                    round={round}
                    timezones={timezones}
                />
            )}
        </div>
    );
}
