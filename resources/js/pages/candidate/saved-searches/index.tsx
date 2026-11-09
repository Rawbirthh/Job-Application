import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, Trash2, Edit3 } from 'lucide-react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { index as jobsIndex } from '@/routes/jobs';
import { destroy as savedSearchesDestroy } from '@/routes/candidate/saved-searches';
import SaveSearchDialog from '@/components/saved-search/save-search-dialog';
import type { SavedSearchRow, SavedSearchesIndexProps } from '@/types';
import { describeCriteria, SavedSearchCriteria } from '@/types/saved-search';

export default function SavedSearchesIndex({
    savedSearches,
    filterOptions,
}: SavedSearchesIndexProps) {
    useFlashToast();

    const items = Array.isArray(savedSearches)
        ? savedSearches
        : savedSearches.data;
    const pagination = !Array.isArray(savedSearches) ? savedSearches : null;

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
        <div className="container mx-auto max-w-6xl px-4 py-8">
            <Head title="Saved Searches" />

            <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Saved Searches
                    </h1>
                    <p className="mt-1 text-muted-foreground">
                        Re-run your saved job searches and manage alert
                        preferences. New matching jobs surface as in-app
                        notifications.
                    </p>
                </div>
                <Button asChild variant="outline">
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
                <div className="space-y-4">
                    {items.map((saved) => {
                        return (
                            <Card
                                key={saved.id}
                                className="transition-shadow hover:shadow-md"
                            >
                                <CardHeader className="flex flex-row items-start justify-between gap-4 px-4 pt-4 pb-3">
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-start gap-3">
                                            <h2 className="text-lg leading-snug font-semibold">
                                                {saved.name ??
                                                    describeCriteria(
                                                        saved.criteria,
                                                        filterOptions,
                                                    )}
                                            </h2>
                                            {!saved.alerts_enabled && (
                                                <Badge variant="outline">
                                                    Alerts off
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap gap-2 text-sm text-muted-foreground">
                                            {saved.search && (
                                                <span className="flex items-center gap-1">
                                                    <Search className="size-3.5" />
                                                    {saved.search}
                                                </span>
                                            )}
                                            {[
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
                                                        saved.employment_type
                                                            ?.id,
                                                    ),
                                                },
                                                {
                                                    key: 'workplace_arrangement',
                                                    label: criteriaLabelFor(
                                                        'workplace_arrangement',
                                                        filterOptions.workplaceArrangements,
                                                        saved
                                                            .workplace_arrangement
                                                            ?.id,
                                                    ),
                                                },
                                                {
                                                    key: 'experience_level',
                                                    label: criteriaLabelFor(
                                                        'experience_level',
                                                        filterOptions.experienceLevels,
                                                        saved.experience_level
                                                            ?.id,
                                                    ),
                                                },
                                            ]
                                                .filter((item) => item.label)
                                                .map((item) => (
                                                    <Badge
                                                        key={item.key}
                                                        variant="secondary"
                                                    >
                                                        {item.label}
                                                    </Badge>
                                                ))}
                                            {saved.criteria &&
                                                !saved.criteria.search &&
                                                !saved.criteria.category &&
                                                !saved.criteria
                                                    .employment_type &&
                                                !saved.criteria
                                                    .workplace_arrangement &&
                                                !saved.criteria
                                                    .experience_level && (
                                                    <span className="text-xs text-muted-foreground">
                                                        All jobs
                                                    </span>
                                                )}
                                        </div>
                                    </div>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                variant={
                                                    saved.alerts_enabled
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {saved.alerts_enabled
                                                    ? saved.alert_frequency ===
                                                      'daily'
                                                        ? 'Daily'
                                                        : 'Weekly'
                                                    : 'Off'}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                Last alert:{' '}
                                                {lastAlerted(
                                                    saved.last_alerted_at,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
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
                                                <Edit3 className="mr-1.5 size-3.5" />
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                onClick={() =>
                                                    handleDelete(saved.id)
                                                }
                                            >
                                                <Trash2 className="mr-1.5 size-3.5" />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardFooter className="border-t border-border px-4 pt-4 pb-4">
                                    <p className="text-xs text-muted-foreground">
                                        Created{' '}
                                        <span className="font-medium">
                                            {new Date(
                                                saved.created_at,
                                            ).toLocaleDateString(undefined, {
                                                dateStyle: 'medium',
                                            })}
                                        </span>
                                    </p>
                                </CardFooter>
                            </Card>
                        );
                    })}

                    {pagination && pagination.last_page > 1 && (
                        <div className="mt-8">
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
