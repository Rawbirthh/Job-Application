import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type PaginationProps = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
    prevPageUrl: string | null;
    nextPageUrl: string | null;
    className?: string;
};

export default function Pagination({
    currentPage,
    lastPage,
    perPage,
    total,
    prevPageUrl,
    nextPageUrl,
    className,
}: PaginationProps) {
    const firstItem =
        total === 0 ? 0 : Math.min((currentPage - 1) * perPage + 1, total);
    const lastItem = total === 0 ? 0 : Math.min(currentPage * perPage, total);

    return (
        <nav
            aria-label="Pagination"
            className={cn(
                'flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <p className="text-sm text-muted-foreground">
                Showing{' '}
                <span className="font-medium text-foreground">{firstItem}</span>
                {'–'}
                <span className="font-medium text-foreground">
                    {lastItem}
                </span>{' '}
                of <span className="font-medium text-foreground">{total}</span>{' '}
                <span className="whitespace-nowrap">({perPage} per page)</span>
            </p>

            <div className="flex flex-wrap items-center gap-2">
                {prevPageUrl ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={prevPageUrl} rel="prev">
                            <ChevronLeft aria-hidden="true" />
                            Previous
                        </Link>
                    </Button>
                ) : (
                    <Button type="button" variant="outline" size="sm" disabled>
                        <ChevronLeft aria-hidden="true" />
                        Previous
                    </Button>
                )}

                <p
                    className="px-2 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    Page{' '}
                    <span
                        aria-current="page"
                        className="font-medium text-foreground"
                    >
                        {currentPage}
                    </span>{' '}
                    of {lastPage}
                </p>

                {nextPageUrl ? (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={nextPageUrl} rel="next">
                            Next
                            <ChevronRight aria-hidden="true" />
                        </Link>
                    </Button>
                ) : (
                    <Button type="button" variant="outline" size="sm" disabled>
                        Next
                        <ChevronRight aria-hidden="true" />
                    </Button>
                )}
            </div>
        </nav>
    );
}
