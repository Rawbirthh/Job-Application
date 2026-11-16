import { Link } from '@inertiajs/react';
import { Briefcase, Calendar, MapPin, MoreVertical } from 'lucide-react';
import type { DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import employer from '@/routes/employer';
import type { PipelineCard, PipelineStatus } from '@/types/pipeline';
import { PIPELINE_STATUS_DOTS } from '@/components/pipeline/pipeline-status';

export type PipelineCardProps = {
    card: PipelineCard;
    canMove: boolean;
    allowedTargets: PipelineStatus[];
    statusLabels: Record<PipelineStatus, string>;
    isPending: boolean;
    onMove: (card: PipelineCard, toStatus: PipelineStatus) => void;
    onDragStart: (card: PipelineCard) => void;
    onDragEnd: () => void;
};

/**
 * Format the application date for the card.
 */
function formatAppliedDate(value: string | null): string {
    if (value === null) {
        return 'Date unavailable';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Date unavailable';
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * One application on the pipeline board.
 *
 * The card only renders the whitelisted board payload (candidate name,
 * headline, location, job title and applied date). The status action menu is
 * always present for a recruiter who may move applications, so no action
 * depends on drag and drop.
 */
export default function PipelineCardView({
    card,
    canMove,
    allowedTargets,
    statusLabels,
    isPending,
    onMove,
    onDragStart,
    onDragEnd,
}: PipelineCardProps) {
    const handleDragStart = (event: DragEvent<HTMLDivElement>) => {
        event.dataTransfer.setData('text/plain', String(card.id));
        event.dataTransfer.effectAllowed = 'move';
        onDragStart(card);
    };

    const isJobClosed = card.job.status === 'closed';
    const showStatusMenu = canMove && !isJobClosed && allowedTargets.length > 0;

    return (
        <Card
            role="listitem"
            aria-label={
                isJobClosed
                    ? `${card.candidate.name} — ${card.job.title} (closed job, read-only)`
                    : `${card.candidate.name} — ${card.job.title}`
            }
            draggable={canMove && !isPending && !isJobClosed}
            onDragStart={handleDragStart}
            onDragEnd={onDragEnd}
            data-pending={isPending ? 'true' : undefined}
            className={cn(
                'gap-0 border-border py-3 transition-[border-color,box-shadow] hover:border-foreground/20 hover:shadow-sm',
                canMove && !isJobClosed && 'cursor-grab active:cursor-grabbing',
                isPending && 'opacity-70',
            )}
        >
            <CardContent className="px-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1 space-y-1">
                        <Link
                            href={employer.applications.show(card.id).url}
                            className="font-medium text-foreground hover:underline"
                            draggable={false}
                        >
                            {card.candidate.name}
                        </Link>

                        {card.candidate.headline !== null && (
                            <p className="line-clamp-1 text-xs text-muted-foreground">
                                {card.candidate.headline}
                            </p>
                        )}

                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span className="inline-flex min-w-0 items-center gap-1">
                                <Briefcase
                                    className="size-3 shrink-0"
                                    aria-hidden="true"
                                />
                                <span className="truncate">
                                    {card.job.title}
                                </span>
                            </span>

                            {card.candidate.location !== null && (
                                <span className="inline-flex items-center gap-1">
                                    <MapPin
                                        className="size-3 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {card.candidate.location}
                                </span>
                            )}

                            <span className="inline-flex items-center gap-1">
                                <Calendar
                                    className="size-3 shrink-0"
                                    aria-hidden="true"
                                />
                                {formatAppliedDate(card.applied_at)}
                            </span>
                        </div>
                    </div>

                    {isJobClosed && (
                        <Badge
                            variant="outline"
                            className="shrink-0 border-border bg-muted text-muted-foreground"
                        >
                            Closed job
                        </Badge>
                    )}

                    {showStatusMenu && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 shrink-0"
                                    disabled={isPending}
                                    aria-label={`Change status for ${card.candidate.name}`}
                                >
                                    {isPending ? (
                                        <Spinner />
                                    ) : (
                                        <MoreVertical className="size-4" />
                                    )}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>Move to</DropdownMenuLabel>
                                {allowedTargets.map((target) => (
                                    <DropdownMenuItem
                                        key={target}
                                        onSelect={() => onMove(card, target)}
                                    >
                                        <span
                                            className={cn(
                                                'size-2 rounded-full',
                                                PIPELINE_STATUS_DOTS[target],
                                            )}
                                            aria-hidden="true"
                                        />
                                        {statusLabels[target]}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
