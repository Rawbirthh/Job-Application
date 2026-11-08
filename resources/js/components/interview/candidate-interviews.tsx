import { CalendarClock } from 'lucide-react';
import Pagination from '@/components/pagination';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { CandidateInterviewRound, InterviewPagination } from '@/types';
import { CandidateInterviewRoundCard } from './candidate-interview-round';

type Props = {
    timezones: string[];
    interviews: CandidateInterviewRound[];
    pagination: InterviewPagination;
};

export function CandidateInterviews({
    timezones,
    interviews,
    pagination,
}: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <CalendarClock className="size-5" />
                    Interviews
                </CardTitle>
                <CardDescription>
                    Times are shown in the employer&apos;s time zone and in your
                    local time zone when they differ.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {interviews.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-border p-6 text-center">
                        <p className="text-sm font-medium text-foreground">
                            No interview rounds scheduled yet.
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            When an employer schedules an interview, it will
                            appear here and you can accept, decline, or request
                            a new time.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {interviews.map((round) => (
                            <CandidateInterviewRoundCard
                                key={round.id}
                                round={round}
                                timezones={timezones}
                            />
                        ))}
                    </div>
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
