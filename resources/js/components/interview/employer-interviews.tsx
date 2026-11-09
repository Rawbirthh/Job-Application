import { CalendarClock } from 'lucide-react';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { EmployerInterviewRound, InterviewPagination } from '@/types';
import { EmployerInterviewRoundCard } from './employer-interview-round';
import { ScheduleInterviewDialog } from './schedule-interview-dialog';

type Props = {
    applicationId: number;
    interviewEligible: boolean;
    canScheduleInterview?: boolean;
    timezones: string[];
    interviews: EmployerInterviewRound[];
    pagination: InterviewPagination;
};

export function EmployerInterviews({
    applicationId,
    interviewEligible,
    canScheduleInterview = true,
    timezones,
    interviews,
    pagination,
}: Props) {
    const canSchedule = interviewEligible && canScheduleInterview;
    const hasInterviews = interviews.length > 0;

    return (
        <Card className="border-border">
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-1">
                    <CardTitle className="flex items-center gap-2 text-lg">
                        <CalendarClock className="size-5 text-teal-600 dark:text-teal-400" />
                        Interviews
                    </CardTitle>
                    <CardDescription>
                        Schedule rounds, review candidate responses, and manage
                        active interviews.
                    </CardDescription>
                </div>

                {canSchedule && (
                    <ScheduleInterviewDialog
                        applicationId={applicationId}
                        timezones={timezones}
                    >
                        <Button
                            type="button"
                            size="sm"
                            className="w-fit shrink-0"
                        >
                            Schedule interview
                        </Button>
                    </ScheduleInterviewDialog>
                )}
            </CardHeader>

            <CardContent className="space-y-4">
                {!hasInterviews ? (
                    <div className="rounded-lg border border-dashed border-border p-6 text-center">
                        <p className="text-sm font-medium text-foreground">
                            No interview rounds scheduled yet.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {canSchedule
                                ? 'Use “Schedule interview” to set up the first round for this candidate.'
                                : 'Interviews can be scheduled once the application is shortlisted or already in interview scheduling.'}
                        </p>
                        {canSchedule && (
                            <ScheduleInterviewDialog
                                applicationId={applicationId}
                                timezones={timezones}
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    className="mt-4"
                                >
                                    Schedule interview
                                </Button>
                            </ScheduleInterviewDialog>
                        )}
                    </div>
                ) : (
                    <>
                        {(!interviewEligible || !canSchedule) && (
                            <p className="rounded-md border border-border bg-muted/30 p-3 text-sm text-muted-foreground">
                                New rounds cannot be scheduled while the
                                application is outside the shortlisted or
                                interview-scheduled stages. Existing rounds
                                remain available to review.
                            </p>
                        )}

                        <div className="space-y-4">
                            {interviews.map((round) => (
                                <EmployerInterviewRoundCard
                                    key={round.id}
                                    round={round}
                                    timezones={timezones}
                                />
                            ))}
                        </div>
                    </>
                )}

                {pagination.total > 0 && (
                    <Pagination
                        currentPage={pagination.current_page}
                        lastPage={pagination.last_page}
                        perPage={pagination.per_page}
                        total={pagination.total}
                        prevPageUrl={pagination.prev_page_url}
                        nextPageUrl={pagination.next_page_url}
                    />
                )}
            </CardContent>
        </Card>
    );
}
