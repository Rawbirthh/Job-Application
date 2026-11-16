import type { DragEvent } from 'react';
import PipelineCardView from '@/components/pipeline/pipeline-card';
import {
    PIPELINE_STATUS_DOTS,
    PIPELINE_STATUS_STYLES,
} from '@/components/pipeline/pipeline-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type {
    PipelineCard,
    PipelineColumn,
    PipelineStatus,
} from '@/types/pipeline';

export type PipelineColumnProps = {
    column: PipelineColumn;
    canMove: boolean;
    transitionMatrix: Record<PipelineStatus, PipelineStatus[]>;
    statusLabels: Record<PipelineStatus, string>;
    pendingApplicationIds: ReadonlySet<number>;
    maxColumnLimit: number;
    isDropTarget: boolean;
    onDragOver: (event: DragEvent<HTMLDivElement>) => void;
    onDragLeave: (event: DragEvent<HTMLDivElement>) => void;
    onDrop: (event: DragEvent<HTMLDivElement>) => void;
    onMove: (card: PipelineCard, toStatus: PipelineStatus) => void;
    onLoadMore: (status: PipelineStatus, nextLimit: number) => void;
    onCardDragStart: (card: PipelineCard) => void;
    onCardDragEnd: () => void;
};

/**
 * One status column of the pipeline board.
 *
 * The column carries its stage color as a small marker so the eight columns can
 * be told apart at a glance, while the surface itself stays neutral. The header
 * exposes the label and the server side count, and a partially loaded column
 * shows how much of it is rendered plus the per-column "Load more" control.
 */
export default function PipelineColumnView({
    column,
    canMove,
    transitionMatrix,
    statusLabels,
    pendingApplicationIds,
    maxColumnLimit,
    isDropTarget,
    onDragOver,
    onDragLeave,
    onDrop,
    onMove,
    onLoadMore,
    onCardDragStart,
    onCardDragEnd,
}: PipelineColumnProps) {
    const nextLimit = Math.min(maxColumnLimit, column.shown * 2);
    const canLoadMore = column.has_more && nextLimit > column.shown;
    const loadedPercent =
        column.count === 0
            ? 0
            : Math.round((column.shown / column.count) * 100);

    return (
        <div
            onDragOver={onDragOver}
            onDragLeave={onDragLeave}
            onDrop={onDrop}
            className={cn(
                'flex w-80 shrink-0 flex-col gap-3 rounded-xl border border-border bg-muted/30 p-3 transition-colors',
                isDropTarget &&
                    'border-primary bg-primary/10 ring-2 ring-primary/40',
            )}
        >
            <span
                className={cn(
                    'h-1.5 w-10 rounded-full',
                    PIPELINE_STATUS_DOTS[column.status],
                )}
                aria-hidden="true"
            />

            <div className="flex items-center justify-between gap-2">
                <h2 className="truncate text-sm font-semibold text-foreground">
                    {column.label}
                </h2>
                <Badge
                    variant="secondary"
                    className="shrink-0 tabular-nums"
                    aria-label={`${column.count} applications`}
                >
                    {column.count}
                </Badge>
            </div>

            {isDropTarget && (
                <div
                    className={cn(
                        'rounded-md border px-2 py-1.5 text-center text-xs font-medium',
                        PIPELINE_STATUS_STYLES[column.status],
                    )}
                >
                    Drop here
                </div>
            )}

            <div
                role="list"
                aria-label={column.label}
                className="flex min-h-24 flex-col gap-2"
            >
                {column.items.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border px-3 py-6 text-center text-xs text-muted-foreground">
                        No applications in this stage
                    </p>
                ) : (
                    column.items.map((card) => (
                        <PipelineCardView
                            key={card.id}
                            card={card}
                            canMove={canMove}
                            allowedTargets={transitionMatrix[card.status] ?? []}
                            statusLabels={statusLabels}
                            isPending={pendingApplicationIds.has(card.id)}
                            onMove={onMove}
                            onDragStart={onCardDragStart}
                            onDragEnd={onCardDragEnd}
                        />
                    ))
                )}
            </div>

            {column.has_more && (
                <div className="mt-auto space-y-2 border-t border-border pt-2">
                    <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span>
                            Showing {column.shown} of {column.count}
                        </span>
                        <span className="tabular-nums" aria-hidden="true">
                            {loadedPercent}%
                        </span>
                    </div>
                    <div
                        className="h-1 w-full overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuenow={column.shown}
                        aria-valuemin={0}
                        aria-valuemax={column.count}
                        aria-label={`${column.label} loaded`}
                    >
                        <div
                            className={cn(
                                'h-full rounded-full transition-[width]',
                                PIPELINE_STATUS_DOTS[column.status],
                            )}
                            style={{ width: `${loadedPercent}%` }}
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-full"
                        onClick={() => onLoadMore(column.status, nextLimit)}
                        disabled={!canLoadMore}
                        title={
                            canLoadMore
                                ? undefined
                                : `A column shows at most ${maxColumnLimit} applications`
                        }
                    >
                        Load more
                    </Button>
                </div>
            )}
        </div>
    );
}
