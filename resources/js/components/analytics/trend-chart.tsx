import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/** One day of the daily trend series. */
export type TrendPoint = {
    /** A `Y-m-d` calendar date. */
    date: string;
    jobs: number;
    applications: number;
    users?: number;
};

/** The keys a trend series can be plotted from. */
export type TrendSeriesKey = 'jobs' | 'applications' | 'users';

/** One plotted series of the trend chart. */
export type TrendSeries = {
    key: TrendSeriesKey;
    label: string;
    /**
     * The Tailwind background token used for the bar and its legend swatch.
     * Only design tokens are used, never a literal colour.
     */
    barClassName: string;
};

export type TrendChartProps = {
    points: TrendPoint[];
    series: TrendSeries[];
    /** Replaces the chart with a skeleton while a navigation is in flight. */
    isLoading?: boolean;
    /** Shown when the server sent no daily series at all. */
    emptyMessage?: string;
};

/**
 * Format the day axis label of a single column.
 */
function formatDayLabel(date: string): string {
    const parsed = new Date(`${date}T00:00:00Z`);

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return parsed.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    });
}

/**
 * Summarise the chart for assistive technology.
 */
function describeTrend(points: TrendPoint[], series: TrendSeries[]): string {
    const totals = series
        .map((item) => {
            const total = points.reduce(
                (sum, point) => sum + (point[item.key] ?? 0),
                0,
            );

            return `${item.label}: ${total}`;
        })
        .join(', ');

    return `Daily activity chart covering ${points.length} day${
        points.length === 1 ? '' : 's'
    }. Totals — ${totals}.`;
}

/**
 * A daily series drawn with plain CSS bars.
 *
 * The project ships no charting library and adding one is out of scope, so
 * every day is a column of bars whose heights are proportional to the
 * largest value of the whole series.
 */
export default function TrendChart({
    points,
    series,
    isLoading = false,
    emptyMessage = 'No daily activity was recorded in this window.',
}: TrendChartProps) {
    if (isLoading) {
        return <Skeleton className="h-44 w-full" />;
    }

    if (points.length === 0) {
        return (
            <p className="py-10 text-center text-sm text-muted-foreground">
                {emptyMessage}
            </p>
        );
    }

    const maximum = points.reduce(
        (highest, point) =>
            series.reduce(
                (seriesHighest, item) =>
                    Math.max(seriesHighest, point[item.key] ?? 0),
                highest,
            ),
        0,
    );

    // Keep the axis readable on long windows by labelling every nth day.
    const labelStep = Math.max(1, Math.ceil(points.length / 8));

    return (
        <div className="space-y-4">
            <div
                role="img"
                aria-label={describeTrend(points, series)}
                className="overflow-x-auto"
            >
                <div className="flex h-44 min-w-full items-stretch gap-1">
                    {points.map((point, index) => (
                        <div
                            key={point.date}
                            className="flex min-w-2 flex-1 flex-col items-center gap-1"
                        >
                            <div className="flex w-full flex-1 items-end justify-center gap-0.5">
                                {series.map((item) => {
                                    const value = point[item.key] ?? 0;
                                    const height =
                                        maximum > 0
                                            ? Math.round(
                                                  (value / maximum) * 100,
                                              )
                                            : 0;

                                    return (
                                        <div
                                            key={item.key}
                                            title={`${point.date} · ${item.label}: ${value}`}
                                            className={cn(
                                                'min-h-px max-w-2 flex-1 rounded-t-sm',
                                                item.barClassName,
                                            )}
                                            style={{ height: `${height}%` }}
                                        />
                                    );
                                })}
                            </div>
                            <span className="text-[10px] text-muted-foreground tabular-nums">
                                {index % labelStep === 0 ||
                                index === points.length - 1
                                    ? formatDayLabel(point.date)
                                    : null}
                            </span>
                        </div>
                    ))}
                </div>
            </div>

            <ul className="flex flex-wrap items-center gap-4">
                {series.map((item) => (
                    <li
                        key={item.key}
                        className="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span
                            aria-hidden="true"
                            className={cn(
                                'size-2.5 rounded-full',
                                item.barClassName,
                            )}
                        />
                        {item.label}
                    </li>
                ))}
            </ul>
        </div>
    );
}
