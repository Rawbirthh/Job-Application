import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    ArrowRight,
    Building2,
    Eye,
    Kanban,
    MousePointerClick,
    Users,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';
import PipelineBoardView from '@/components/pipeline/pipeline-board';
import PipelineFilters from '@/components/pipeline/pipeline-filters';
import { PIPELINE_STATUS_STYLES } from '@/components/pipeline/pipeline-status';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import http from '@/lib/http';
import employer from '@/routes/employer';
import type {
    PipelineBoard,
    PipelineCard,
    PipelineColumnLimits,
    PipelineStatus,
} from '@/types/pipeline';

/**
 * The hard maximum number of cards a column may render (server enforced).
 */
const MAX_COLUMN_LIMIT = 100;

/**
 * The application statuses of the pipeline board.
 */
const PIPELINE_STATUSES = new Set<string>([
    'submitted',
    'reviewing',
    'shortlisted',
    'interview_scheduled',
    'offer_sent',
    'hired',
    'rejected',
    'withdrawn',
]);

/**
 * The query payload of the board route.
 *
 * `limit` is a nested object: it is serialised as `limit[<status>]=<n>` and
 * raises the card limit of that single column.
 */
type PipelineQuery = {
    company?: number;
    job?: number;
    q?: string;
    limit?: PipelineColumnLimits;
};

/**
 * The JSON body returned by the transition endpoint on a rejection.
 */
type TransitionErrorPayload = {
    message?: string;
    errors?: Record<string, string[]>;
};

type Props = {
    board: PipelineBoard;
};

/**
 * Narrow an unknown query key to a pipeline status.
 */
function isPipelineStatus(value: string): value is PipelineStatus {
    return PIPELINE_STATUSES.has(value);
}

/**
 * Read the per-column limits from the current URL.
 *
 * The limits live in the query string, so the board can preserve them when
 * another filter changes without keeping a second source of truth.
 */
function readColumnLimits(url: string): PipelineColumnLimits {
    const separator = url.indexOf('?');

    if (separator === -1) {
        return {};
    }

    const limits: PipelineColumnLimits = {};
    const params = new URLSearchParams(url.slice(separator + 1));

    params.forEach((value, key) => {
        if (!key.startsWith('limit[') || !key.endsWith(']')) {
            return;
        }

        const status = key.slice('limit['.length, -1);

        if (!isPipelineStatus(status)) {
            return;
        }

        const limit = Number(value);

        if (
            Number.isInteger(limit) &&
            limit >= 1 &&
            limit <= MAX_COLUMN_LIMIT
        ) {
            limits[status] = limit;
        }
    });

    return limits;
}

/**
 * Extract the message of a failed transition request.
 */
function extractErrorMessage(error: unknown): string {
    if (axios.isAxiosError<TransitionErrorPayload>(error)) {
        const payload = error.response?.data;
        const status = error.response?.status;

        if (status === 422 && payload?.errors) {
            const fieldError = Object.values(payload.errors).flat()[0];

            if (fieldError) {
                return fieldError;
            }
        }

        if (payload?.message) {
            return payload.message;
        }

        if (error.message) {
            return error.message;
        }
    }

    return 'The application status could not be changed.';
}

/**
 * The visual recruitment pipeline board.
 *
 * The board is a read surface over the existing application workflow: every
 * filter and every move goes through the server, and the transition endpoint
 * stays the single authority on which status changes are legal.
 */
export default function EmployerPipelineIndex({ board }: Props) {
    const { url } = usePage();
    const [pendingApplicationIds, setPendingApplicationIds] = useState<
        ReadonlySet<number>
    >(() => new Set<number>());
    const [isNavigating, setIsNavigating] = useState(false);
    const [moveTarget, setMoveTarget] = useState<{
        card: PipelineCard;
        toStatus: PipelineStatus;
    } | null>(null);
    const [reason, setReason] = useState('');

    const columnLimits = useMemo(() => readColumnLimits(url), [url]);

    const companyId = board.filters.company_id;
    const jobId = board.filters.job_id;
    const search = board.filters.search;

    const totalApplications = Object.values(board.status_counts).reduce(
        (total, count) => total + count,
        0,
    );

    const hasActiveFilters = jobId !== null || search !== null;

    const navigate = useCallback((query: PipelineQuery) => {
        router.get(employer.pipeline.index().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsNavigating(true),
            onFinish: () => setIsNavigating(false),
        });
    }, []);

    const reloadBoard = useCallback(() => {
        // A reload keeps the scroll position and the component state, so the
        // board refreshes in place after a move.
        router.reload({
            only: ['board'],
            onStart: () => setIsNavigating(true),
            onFinish: () => {
                setIsNavigating(false);
                setPendingApplicationIds(new Set<number>());
            },
        });
    }, []);

    const handleCompanyChange = useCallback(
        (nextCompanyId: number) => {
            navigate({
                company: nextCompanyId,
                // A job belongs to a single company, so the job filter is
                // dropped together with the company context.
                job: undefined,
                q: search ?? undefined,
                limit: columnLimits,
            });
        },
        [columnLimits, navigate, search],
    );

    const handleJobChange = useCallback(
        (nextJobId: number | null) => {
            navigate({
                company: companyId ?? undefined,
                job: nextJobId ?? undefined,
                q: search ?? undefined,
                limit: columnLimits,
            });
        },
        [columnLimits, companyId, navigate, search],
    );

    const handleSearchSubmit = useCallback(
        (term: string) => {
            const trimmed = term.trim();

            navigate({
                company: companyId ?? undefined,
                job: jobId ?? undefined,
                q: trimmed === '' ? undefined : trimmed,
                limit: columnLimits,
            });
        },
        [columnLimits, companyId, jobId, navigate],
    );

    const handleClearFilters = useCallback(() => {
        navigate({
            company: companyId ?? undefined,
            job: undefined,
            q: undefined,
            limit: columnLimits,
        });
    }, [columnLimits, companyId, navigate]);

    const handleLoadMore = useCallback(
        (status: PipelineStatus, nextLimit: number) => {
            navigate({
                company: companyId ?? undefined,
                job: jobId ?? undefined,
                q: search ?? undefined,
                limit: {
                    ...columnLimits,
                    [status]: Math.min(nextLimit, MAX_COLUMN_LIMIT),
                },
            });
        },
        [columnLimits, companyId, jobId, navigate, search],
    );

    const handleMove = useCallback(
        (card: PipelineCard, toStatus: PipelineStatus) => {
            setReason('');
            setMoveTarget({ card, toStatus });
        },
        [],
    );

    const cancelMove = useCallback(() => {
        setMoveTarget(null);
        setReason('');
    }, []);

    const confirmMove = useCallback(async () => {
        if (moveTarget === null) {
            return;
        }

        const { card, toStatus } = moveTarget;
        const trimmed = reason.trim();

        setPendingApplicationIds((current) => {
            const next = new Set(current);
            next.add(card.id);
            return next;
        });

        try {
            await http.post(employer.applications.updateStatus(card.id).url, {
                current_status: card.status,
                new_status: toStatus,
                ...(trimmed !== '' ? { reason: trimmed } : {}),
            });

            toast.success(
                `${card.candidate.name} moved to ${board.status_labels[toStatus]}.`,
            );
            setMoveTarget(null);
            setReason('');
        } catch (error) {
            toast.error(extractErrorMessage(error));
        } finally {
            // The board always reconciles with the server state, whether
            // the move succeeded or was rejected.
            reloadBoard();
        }
    }, [board.status_labels, moveTarget, reason, reloadBoard]);

    if (board.company === null) {
        return (
            <>
                <Head title="Recruitment Pipeline" />

                <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                    <div className="flex items-start gap-3">
                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-border bg-primary/10 text-primary">
                            <Kanban className="size-6" aria-hidden="true" />
                        </div>
                        <div className="flex flex-col gap-1">
                            <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                                Recruitment Pipeline
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                See every application of your company in one
                                pipeline and move candidates forward.
                            </p>
                        </div>
                    </div>

                    <Card className="border-border">
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                            <div className="flex size-14 items-center justify-center rounded-full bg-muted">
                                <Building2 className="size-7 text-muted-foreground" />
                            </div>
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold text-foreground">
                                    No company you can manage yet
                                </h2>
                                <p className="max-w-sm text-sm text-muted-foreground">
                                    The pipeline shows the applications of a
                                    single company. Create a company or ask an
                                    administrator for an invitation to see its
                                    pipeline.
                                </p>
                            </div>
                            <div className="flex flex-wrap justify-center gap-2">
                                <Button asChild>
                                    <Link
                                        href={employer.companies.create().url}
                                    >
                                        <Building2 className="mr-2 size-4" />
                                        Create a company
                                    </Link>
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={employer.companies.index().url}>
                                        View your companies
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Recruitment Pipeline" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-start gap-3">
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-border bg-primary/10 text-primary">
                        <Kanban className="size-6" aria-hidden="true" />
                    </div>
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Recruitment Pipeline
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <span className="font-medium text-foreground">
                                {board.company.name}
                            </span>
                            {' — '}
                            {totalApplications}{' '}
                            {totalApplications === 1
                                ? 'application'
                                : 'applications'}{' '}
                            across {board.columns.length} stages.
                        </p>
                    </div>
                </div>

                <PipelineFilters
                    companies={board.companies}
                    jobs={board.jobs}
                    selectedCompanyId={companyId}
                    selectedJobId={jobId}
                    search={search ?? ''}
                    isNavigating={isNavigating}
                    onCompanyChange={handleCompanyChange}
                    onJobChange={handleJobChange}
                    onSearchSubmit={handleSearchSubmit}
                    onClearFilters={handleClearFilters}
                />

                {board.can_move ? (
                    <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                        <MousePointerClick
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            Drag a card onto another stage, or use the card menu
                            to move an application.
                        </span>
                    </div>
                ) : (
                    <div className="flex items-center gap-2 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
                        <Eye className="size-4 shrink-0" aria-hidden="true" />
                        <span>
                            Read-only view: you can browse this pipeline, but
                            you cannot change the status of an application.
                        </span>
                    </div>
                )}

                {totalApplications === 0 && (
                    <Card className="border-border">
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                            <div className="flex size-14 items-center justify-center rounded-full bg-muted">
                                <Users className="size-7 text-muted-foreground" />
                            </div>
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold text-foreground">
                                    {hasActiveFilters
                                        ? 'No applications match the current filters'
                                        : 'No applications yet'}
                                </h2>
                                <p className="max-w-sm text-sm text-muted-foreground">
                                    {hasActiveFilters
                                        ? 'No application of this company matches the selected job or candidate name.'
                                        : 'When candidates apply to a job posting of this company, they appear here.'}
                                </p>
                            </div>
                            {hasActiveFilters && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleClearFilters}
                                    disabled={isNavigating}
                                >
                                    Clear filters
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}

                <div aria-busy={isNavigating}>
                    <PipelineBoardView
                        columns={board.columns}
                        canMove={board.can_move}
                        transitionMatrix={board.transition_matrix}
                        statusLabels={board.status_labels}
                        pendingApplicationIds={pendingApplicationIds}
                        maxColumnLimit={MAX_COLUMN_LIMIT}
                        onMove={handleMove}
                        onLoadMore={handleLoadMore}
                    />
                </div>
            </div>

            <Dialog
                open={moveTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        cancelMove();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Move application</DialogTitle>
                        <DialogDescription>
                            {moveTarget?.card.candidate.name}
                            {moveTarget ? ' · ' : null}
                            {moveTarget?.card.job.title}
                        </DialogDescription>
                    </DialogHeader>
                    {moveTarget !== null && (
                        <>
                            <div className="flex items-center gap-2 text-sm">
                                <Badge
                                    variant="outline"
                                    className="text-muted-foreground"
                                >
                                    {
                                        board.status_labels[
                                            moveTarget.card.status
                                        ]
                                    }
                                </Badge>
                                <ArrowRight
                                    className="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Badge
                                    variant="outline"
                                    className={
                                        PIPELINE_STATUS_STYLES[
                                            moveTarget.toStatus
                                        ]
                                    }
                                >
                                    {board.status_labels[moveTarget.toStatus]}
                                </Badge>
                            </div>
                            <div className="space-y-1.5">
                                <div className="flex items-center justify-between gap-2">
                                    <Label htmlFor="move-reason">
                                        Reason{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optional)
                                        </span>
                                    </Label>
                                    <span
                                        className="text-xs text-muted-foreground tabular-nums"
                                        aria-hidden="true"
                                    >
                                        {reason.length}/500
                                    </span>
                                </div>
                                <Textarea
                                    id="move-reason"
                                    name="reason"
                                    value={reason}
                                    onChange={(event) =>
                                        setReason(event.target.value)
                                    }
                                    maxLength={500}
                                    rows={3}
                                    placeholder="Add a note about why this stage changed"
                                    autoFocus
                                />
                                <p className="text-xs text-muted-foreground">
                                    Saved to the application's status history.
                                </p>
                            </div>
                        </>
                    )}
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                variant="outline"
                                disabled={
                                    moveTarget !== null &&
                                    pendingApplicationIds.has(
                                        moveTarget.card.id,
                                    )
                                }
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            onClick={() => void confirmMove()}
                            disabled={
                                moveTarget === null ||
                                pendingApplicationIds.has(moveTarget.card.id)
                            }
                        >
                            {moveTarget !== null &&
                                pendingApplicationIds.has(
                                    moveTarget.card.id,
                                ) && <Spinner />}
                            Move to{' '}
                            {moveTarget
                                ? board.status_labels[moveTarget.toStatus]
                                : ''}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

EmployerPipelineIndex.layout = {
    breadcrumbs: [
        {
            title: 'Recruitment Pipeline',
            href: employer.pipeline.index(),
        },
    ],
};
