import { formatInterviewDateTime, sameTimeZone } from '@/lib/datetime';
import type { InterviewRound } from '@/types';

type Props = {
    round: InterviewRound;
    viewerTimeZone: string;
};

export function InterviewDetails({ round, viewerTimeZone }: Props) {
    const showViewerTime = !sameTimeZone(round.timezone, viewerTimeZone);

    return (
        <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div className="space-y-1">
                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    When
                </dt>
                <dd className="space-y-0.5">
                    <time
                        dateTime={round.scheduled_at}
                        className="font-medium text-foreground"
                    >
                        {formatInterviewDateTime(
                            round.scheduled_at,
                            round.timezone,
                        )}
                    </time>
                    <div className="text-xs text-muted-foreground">
                        {round.timezone}
                    </div>
                    {showViewerTime && (
                        <div className="text-xs text-muted-foreground">
                            Your local time:{' '}
                            <time dateTime={round.scheduled_at}>
                                {formatInterviewDateTime(
                                    round.scheduled_at,
                                    viewerTimeZone,
                                )}
                            </time>{' '}
                            ({viewerTimeZone})
                        </div>
                    )}
                </dd>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1">
                    <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Duration
                    </dt>
                    <dd className="text-foreground">
                        {round.duration_minutes} minutes
                    </dd>
                </div>
                <div className="space-y-1">
                    <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Mode
                    </dt>
                    <dd className="text-foreground">{round.mode_label}</dd>
                </div>
            </div>

            <div className="space-y-1 sm:col-span-2">
                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Location / meeting details
                </dt>
                <dd className="break-words whitespace-pre-wrap text-foreground">
                    {round.location_details}
                </dd>
            </div>

            {round.candidate_instructions && (
                <div className="space-y-1 sm:col-span-2">
                    <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Candidate instructions
                    </dt>
                    <dd className="break-words whitespace-pre-wrap text-foreground">
                        {round.candidate_instructions}
                    </dd>
                </div>
            )}
        </dl>
    );
}
