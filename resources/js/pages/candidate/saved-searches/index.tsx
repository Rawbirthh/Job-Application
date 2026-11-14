import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { BellRing, Search, Trash2, Edit3 } from 'lucide-react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';
import { index as jobsIndex } from '@/routes/jobs';
import { destroy as savedSearchesDestroy } from '@/routes/candidate/saved-searches';
import SaveSearchDialog from '@/components/saved-search/save-search-dialog';
import type { SavedSearchRow, SavedSearchesIndexProps } from '@/types';
import { describeCriteria, SavedSearchCriteria } from '@/types/saved-search';

function alertBadgeClasses(enabled: boolean, frequency: string | null): string {
    if (!enabled) {
        return 'border-border bg-muted text-muted-foreground';
    }

    return frequency === 'daily'
        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-400';
}

export default function SavedSearchesIndex({
    savedSearches,
    filterOptions,
}: SavedSearchesIndexProps) {
    useFlashToast();

    const items = Array.isArray(savedSearches)
        ? savedSearches
        : savedSearches.data;
    const pagination = !Array.isArray(savedSearches) ? savedSearches : null;
    const total = pagination?.total ?? items.length;

    const [editOpen, setEditOpen] = useState(false);
    const [editingSearch, setEditingSearch] = useState<SavedSearchRow | null>(
        null,
    );

    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const criteriaLabelFor = (
        key:
            | 'category'
            | 'employment_type'
            | 'workplace_arrangement'
            | 'experience_level',
        list: { id: number; name: string }[],
        id: number | null,
    ): string | null => {
        if (!id) {
            return null;
        }
        return list.find((option) => option.id === id)?.name ?? null;
    };

    const handleRerun = (criteria: SavedSearchCriteria) => {
        const params: Record<string, string | number> = {};
        if (criteria.search) {
            params.search = criteria.search;
        }
        if (criteria.category) {
            params.category = criteria.category;
        }
        if (criteria.employment_type) {
            params.employment_type = criteria.employment_type;
        }
        if (criteria.workplace_arrangement) {
            params.workplace_arrangement = criteria.workplace_arrangement;
        }
        if (criteria.experience_level) {
            params.experience_level = criteria.experience_level;
        }

        router.get(jobsIndex({ query: params }).url, {
            preserveScroll: true,
        });
    };

    const handleDelete = (id: number) => {
        setDeletingId(id);
        setDeleteOpen(true);
    };

    const confirmDelete = () => {
        if (deletingId === null) {
            return;
        }
        router.delete(savedSearchesDestroy.url({ savedSearch: deletingId }), {
            preserveScroll: true,
        });
        setDeleteOpen(false);
        setDeletingId(null);
    };

    const lastAlerted = (value: string | null) => {
        if (!value) {
            return 'Never';
        }
        return new Date(value).toLocaleString(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    };

    return (
        <div className="flex w-full flex-1 flex-col gap-6 p-6 md:p-8">
            <Head title="Saved Searches" />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                        Saved Searches
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground md:text-base">
                        Re-run bookmarked searches and manage job alerts ·{' '}
                        {total} saved {total === 1 ? 'search' : 'searches'}
                    </p>
                </div>
                <Button asChild variant="outline" size="sm">
                    <Link href="/jobs">Browse Jobs</Link>
                </Button>
            </div>

            {items.length === 0 ? (
                <Card className="py-12 text-center">
                    <CardContent className="flex flex-col items-center justify-center space-y-4">
                        <div className="rounded-full bg-muted p-4">
                            <Search className="h-8 w-8 text-muted-foreground" />
                        </div>
                        <h3 className="text-lg font-semibold">
                            No saved searches yet
                        </h3>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            Run a search on the job listings page and save it
                            with the filters applied. You'll be able to rerun it
                            here and opt in to alerts for new matches.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href="/jobs">Browse Job Listings</Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-3">
                    {items.map((saved) => {
                        const criteriaBadges = [
                            {
                                key: 'category',
                                label: criteriaLabelFor(
                                    'category',
                                    filterOptions.categories,
                                    saved.category?.id,
                                ),
                            },
                            {
                                key: 'employment_type',
                                label: criteriaLabelFor(
                                    'employment_type',
                                    filterOptions.employmentTypes,
                                    saved.employment_type?.id,
                                ),
                            },
                            {
                                key: 'workplace_arrangement',
                                label: criteriaLabelFor(
                                    'workplace_arrangement',
                                    filterOptions.workplaceArrangements,
                                    saved.workplace_arrangement?.id,
                                ),
                            },
                            {
                                key: 'experience_level',
                                label: criteriaLabelFor(
                                    'experience_level',
                                    filterOptions.experienceLevels,
                                    saved.experience_level?.id,
                                ),
                            },
                        ].filter((item) => item.label);

                        const hasAnyCriteria =
                            saved.criteria !== null &&
                            Boolean(
                                saved.criteria.search ||
                                saved.criteria.category ||
                                saved.criteria.employment_type ||
                                saved.criteria.workplace_arrangement ||
                                saved.criteria.experience_level,
                            );

                        const alertLabel = !saved.alerts_enabled
                            ? 'Alerts off'
                            : saved.alert_frequency === 'daily'
                              ? 'Daily alerts'
                              : 'Weekly alerts';

                        return (
                            <Card
                                key={saved.id}
                                className="transition-shadow hover:shadow-md"
                            >
                                <CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0 flex-1 space-y-1.5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h2 className="text-base leading-snug font-semibold sm:text-lg">
                                                {saved.name ??
                                                    describeCriteria(
                                                        saved.criteria,
                                                        filterOptions,
                                                    )}
                                            </h2>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    'gap-1.5',
                                                    alertBadgeClasses(
                                                        saved.alerts_enabled,
                                                        saved.alert_frequency,
                                                    ),
                                                )}
                                            >
                                                <BellRing
                                                    className="size-3"
                                                    aria-hidden="true"
                                                />
                                                {alertLabel}
                                            </Badge>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1.5">
                                            {saved.search && (
                                                <span className="mr-1 flex items-center gap-1 text-xs text-foreground sm:text-sm">
                                                    <Search
                                                        className="size-3.5 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                    “{saved.search}”
                                                </span>
                                            )}
                                            {criteriaBadges.map((item) => (
                                                <Badge
                                                    key={item.key}
                                                    variant="secondary"
                                                    className="font-normal"
                                                >
                                                    {item.label}
                                                </Badge>
                                            ))}
                                            {!hasAnyCriteria && (
                                                <span className="text-xs text-muted-foreground">
                                                    All jobs
                                                </span>
                                            )}
                                        </div>

                                        <p className="text-[11px] text-muted-foreground">
                                            Last alert:{' '}
                                            {lastAlerted(saved.last_alerted_at)}{' '}
                                            · Created{' '}
                                            {new Date(
                                                saved.created_at,
                                            ).toLocaleDateString(undefined, {
                                                dateStyle: 'medium',
                                            })}
                                        </p>
                                    </div>

                                    <div className="flex shrink-0 items-center gap-2 self-end sm:self-center">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                handleRerun(saved.criteria);
                                            }}
                                        >
                                            Rerun
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => {
                                                setEditingSearch(saved);
                                                setEditOpen(true);
                                            }}
                                        >
                                            <Edit3
                                                className="mr-1.5 size-3.5"
                                                aria-hidden="true"
                                            />
                                            Edit
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                handleDelete(saved.id)
                                            }
                                        >
                                            <Trash2
                                                className="mr-1.5 size-3.5"
                                                aria-hidden="true"
                                            />
                                            Delete
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}

                    {pagination && pagination.last_page > 1 && (
                        <div className="mt-2">
                            <Pagination
                                currentPage={pagination.current_page}
                                lastPage={pagination.last_page}
                                perPage={pagination.per_page}
                                total={pagination.total}
                                prevPageUrl={pagination.prev_page_url}
                                nextPageUrl={pagination.next_page_url}
                            />
                        </div>
                    )}
                </div>
            )}

            <SaveSearchDialog
                open={editOpen}
                onOpenChange={setEditOpen}
                mode="edit"
                savedSearch={editingSearch ?? undefined}
                filterOptions={filterOptions}
            />

            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete saved search?</DialogTitle>
                        <DialogDescription>
                            This cannot be undone. All alert history for this
                            search will be removed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmDelete}
                            disabled={deletingId === null}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
