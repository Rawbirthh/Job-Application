import { useState, type DragEvent } from 'react';
import PipelineColumnView from '@/components/pipeline/pipeline-column';
import type {
    PipelineCard,
    PipelineColumn,
    PipelineStatus,
} from '@/types/pipeline';

export type PipelineBoardProps = {
    columns: PipelineColumn[];
    canMove: boolean;
    transitionMatrix: Record<PipelineStatus, PipelineStatus[]>;
    statusLabels: Record<PipelineStatus, string>;
    pendingApplicationIds: ReadonlySet<number>;
    maxColumnLimit: number;
    onMove: (card: PipelineCard, toStatus: PipelineStatus) => void;
    onLoadMore: (status: PipelineStatus, nextLimit: number) => void;
};

/**
 * The Kanban board of the recruitment pipeline.
 *
 * Drag and drop is purely additive: the card status menu is always available,
 * so every action stays reachable with a keyboard. The board only decides
 * whether a drop is *offered*; the server still validates the transition.
 */
export default function PipelineBoard({
    columns,
    canMove,
    transitionMatrix,
    statusLabels,
    pendingApplicationIds,
    maxColumnLimit,
    onMove,
    onLoadMore,
}: PipelineBoardProps) {
    const [draggedCard, setDraggedCard] = useState<PipelineCard | null>(null);
    const [overStatus, setOverStatus] = useState<PipelineStatus | null>(null);

    const allowedTargets = draggedCard
        ? (transitionMatrix[draggedCard.status] ?? [])
        : [];

    const handleDragStart = (card: PipelineCard) => {
        setDraggedCard(card);
    };

    const handleDragEnd = () => {
        setDraggedCard(null);
        setOverStatus(null);
    };

    const handleDragOver = (
        event: DragEvent<HTMLDivElement>,
        status: PipelineStatus,
    ) => {
        if (!draggedCard || !allowedTargets.includes(status)) {
            event.dataTransfer.dropEffect = 'none';
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        setOverStatus(status);
    };

    const handleDragLeave = (event: DragEvent<HTMLDivElement>) => {
        const related = event.relatedTarget;

        if (related instanceof Node && event.currentTarget.contains(related)) {
            return;
        }

        setOverStatus(null);
    };

    const handleDrop = (
        event: DragEvent<HTMLDivElement>,
        status: PipelineStatus,
    ) => {
        event.preventDefault();

        const card = draggedCard;

        setDraggedCard(null);
        setOverStatus(null);

        if (!card || !allowedTargets.includes(status)) {
            return;
        }

        onMove(card, status);
    };

    return (
        <div className="flex gap-4 overflow-x-auto pb-4">
            {columns.map((column) => (
                <PipelineColumnView
                    key={column.status}
                    column={column}
                    canMove={canMove}
                    transitionMatrix={transitionMatrix}
                    statusLabels={statusLabels}
                    pendingApplicationIds={pendingApplicationIds}
                    maxColumnLimit={maxColumnLimit}
                    isDropTarget={
                        canMove &&
                        overStatus === column.status &&
                        allowedTargets.includes(column.status)
                    }
                    onDragOver={(event) => handleDragOver(event, column.status)}
                    onDragLeave={handleDragLeave}
                    onDrop={(event) => handleDrop(event, column.status)}
                    onMove={onMove}
                    onLoadMore={onLoadMore}
                    onCardDragStart={handleDragStart}
                    onCardDragEnd={handleDragEnd}
                />
            ))}
        </div>
    );
}
