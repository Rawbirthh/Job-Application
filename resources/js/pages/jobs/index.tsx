import { Head, InfiniteScroll, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Bookmark,
    Building2,
    CalendarDays,
    Loader2,
    MapPin,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useState } from 'react';
import SaveSearchDialog from '@/components/saved-search/save-search-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useMediaQuery } from '@/hooks/use-media-query';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

interface Job {
    id: number;
    title: string;
    location: string | null;
    min_salary: string | null;
    max_salary: string | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    created_at: string | null;
    description: string | null;
    responsibilities: string | null;
    requirements: string | null;
    skills: string[];
    application_deadline: string | null;
    company: {
        id: number;
        name: string;
        slug: string;
        logo_url: string | null;
    } | null;
    category: { id: number; name: string } | null;
    employment_type: { id: number; name: string } | null;
    workplace_arrangement: { id: number; name: string } | null;
    experience_level: { id: number; name: string } | null;
    pay_period: { id: number; name: string } | null;
}

interface FilterOption {
    id: number;
    name: string;
}

interface Props {
    savedJobIds?: number[];
    jobs: {
        data: Job[];
        path: string;
        per_page: number;
        next_cursor: string | null;
        prev_cursor: string | null;
    };
    categories: FilterOption[];
    employmentTypes: FilterOption[];
    workplaceArrangements: FilterOption[];
    experienceLevels: FilterOption[];
    filters: {
        search: string | null;
        location: string | null;
        category: number | null;
        employment_type: number | null;
        workplace_arrangement: number | null;
        experience_level: number | null;
    };
}

const selectTriggerClassName = 'h-9 text-sm';

function relativePostedAt(iso: string | null): string {
    if (!iso) {
        return 'Recently';
    }

    const days = Math.floor(
        (Date.now() - new Date(iso).getTime()) / (24 * 3600 * 1000),
    );

    if (Number.isNaN(days) || days <= 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Yesterday';
    }

    if (days < 7) {
        return `${days}d ago`;
    }

    if (days < 30) {
        return `${Math.floor(days / 7)}w ago`;
    }

    if (days < 365) {
        return `${Math.floor(days / 30)}mo ago`;
    }

    return new Date(iso).toLocaleDateString();
}

function formatDeadline(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function isWithinDays(iso: string | null, days: number): boolean {
    if (!iso) {
        return false;
    }

    const diff = Date.now() - new Date(iso).getTime();

    return diff >= 0 && diff < days * 24 * 3600 * 1000;
}

function daysUntil(iso: string): number {
    return Math.ceil(
        (new Date(iso).getTime() - Date.now()) / (24 * 3600 * 1000),
    );
}

export default function PublicJobsIndex({
    savedJobIds = [],
    jobs,
    categories,
    employmentTypes,
    workplaceArrangements,
    experienceLevels,
    filters,
}: Props) {
    // `usePage` requires its generic to carry an index signature, so the auth
    // props use an inline type and `scrollProps` is read from the default
    // PageProps page object.
    const { auth } = usePage<{ auth: Auth }>().props;
    const { scrollProps } = usePage();
    const isJobSeeker = Boolean(auth.user && auth.roles.includes('job_seeker'));
    const hasNextPage = Boolean(scrollProps?.jobs?.nextPage);

    const isDesktop = useMediaQuery('(min-width: 1024px)');

    useFlashToast();

    const [saveSearchOpen, setSaveSearchOpen] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [location, setLocation] = useState(filters.location || '');
    const [selectedCategory, setSelectedCategory] = useState(
        filters.category ? String(filters.category) : 'all',
    );
    const [selectedEmploymentType, setSelectedEmploymentType] = useState(
        filters.employment_type ? String(filters.employment_type) : 'all',
    );
    const [selectedWorkplace, setSelectedWorkplace] = useState(
        filters.workplace_arrangement
            ? String(filters.workplace_arrangement)
            : 'all',
    );
    const [selectedExperience, setSelectedExperience] = useState(
        filters.experience_level ? String(filters.experience_level) : 'all',
    );

    // Master-detail selection state. `null` = "fall back to the first result",
    // which also covers filter changes that remove the previously selected job.
    const [selectedId, setSelectedId] = useState<number | null>(null);

    const selectedJob =
        jobs.data.find((job) => job.id === selectedId) ?? jobs.data[0] ?? null;

    const handleRowClick = (job: Job) => {
        if (isDesktop) {
            setSelectedId(job.id);
            return;
        }

        router.visit(`/jobs/${job.id}`);
    };

    const handleToggleSave = (jobId: number, isCurrentlySaved: boolean) => {
        if (!auth.user) {
            router.visit('/login');
            return;
        }

        if (!isJobSeeker) {
            return;
        }

        if (isCurrentlySaved) {
            router.delete(`/candidate/saved-jobs/${jobId}`, {
                preserveScroll: true,
            });
        } else {
            router.post(
                '/candidate/saved-jobs',
                { job_posting_id: jobId },
                { preserveScroll: true },
            );
        }
    };

    const applyFilters = (
        newFilters: Record<string, string | number | null>,
    ) => {
        // A fresh filter set invalidates the loaded infinite-scroll pages and
        // the master-detail selection; `reset: ['jobs']` makes the client
        // discard previously appended rows.
        setSelectedId(null);
        router.get(
            '/jobs',
            {
                search: search.trim() !== '' ? search.trim() : null,
                location: location.trim() !== '' ? location.trim() : null,
                category:
                    selectedCategory !== 'all'
                        ? parseInt(selectedCategory, 10)
                        : null,
                employment_type:
                    selectedEmploymentType !== 'all'
                        ? parseInt(selectedEmploymentType, 10)
                        : null,
                workplace_arrangement:
                    selectedWorkplace !== 'all'
                        ? parseInt(selectedWorkplace, 10)
                        : null,
                experience_level:
                    selectedExperience !== 'all'
                        ? parseInt(selectedExperience, 10)
                        : null,
                ...newFilters,
            },
            {
                only: ['jobs'],
                reset: ['jobs'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({});
    };

    const handleReset = () => {
        setSearch('');
        setLocation('');
        setSelectedCategory('all');
        setSelectedEmploymentType('all');
        setSelectedWorkplace('all');
        setSelectedExperience('all');
        setSelectedId(null);
        router.get(
            '/jobs',
            {},
            {
                only: ['jobs'],
                reset: ['jobs'],
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const optionName = (options: FilterOption[], id: string): string =>
        options.find((option) => String(option.id) === id)?.name ?? id;

    const activeChips: { key: string; label: string }[] = [];

    if (search.trim() !== '') {
        activeChips.push({ key: 'search', label: `“${search.trim()}”` });
    }

    if (location.trim() !== '') {
        activeChips.push({ key: 'location', label: location.trim() });
    }

    if (selectedCategory !== 'all') {
        activeChips.push({
            key: 'category',
            label: optionName(categories, selectedCategory),
        });
    }

    if (selectedEmploymentType !== 'all') {
        activeChips.push({
            key: 'employment_type',
            label: optionName(employmentTypes, selectedEmploymentType),
        });
    }

    if (selectedWorkplace !== 'all') {
        activeChips.push({
            key: 'workplace_arrangement',
            label: optionName(workplaceArrangements, selectedWorkplace),
        });
    }

    if (selectedExperience !== 'all') {
        activeChips.push({
            key: 'experience_level',
            label: optionName(experienceLevels, selectedExperience),
        });
    }

    const removeChip = (key: string) => {
        switch (key) {
            case 'search':
                setSearch('');
                applyFilters({ search: null });
                break;
            case 'location':
                setLocation('');
                applyFilters({ location: null });
                break;
            case 'category':
                setSelectedCategory('all');
                applyFilters({ category: null });
                break;
            case 'employment_type':
                setSelectedEmploymentType('all');
                applyFilters({ employment_type: null });
                break;
            case 'workplace_arrangement':
                setSelectedWorkplace('all');
                applyFilters({ workplace_arrangement: null });
                break;
            case 'experience_level':
                setSelectedExperience('all');
                applyFilters({ experience_level: null });
                break;
        }
    };

    const formatSalary = (job: Job): string | null => {
        if (!job.min_salary && !job.max_salary) {
            return job.salary_negotiable ? 'Salary negotiable' : null;
        }

        const curr = job.salary_currency || 'USD';
        const period = job.pay_period ? ` / ${job.pay_period.name}` : '';

        if (job.min_salary && job.max_salary) {
            return `${curr} ${Number(job.min_salary).toLocaleString()} – ${Number(job.max_salary).toLocaleString()}${period}`;
        }

        if (job.min_salary) {
            return `From ${curr} ${Number(job.min_salary).toLocaleString()}${period}`;
        }

        return `Up to ${curr} ${Number(job.max_salary).toLocaleString()}${period}`;
    };

    const selectedSalaryText = selectedJob ? formatSalary(selectedJob) : null;
    const selectedHasSalaryRange = Boolean(
        selectedJob && (selectedJob.min_salary || selectedJob.max_salary),
    );

    const hasActiveFilters = activeChips.length > 0;

    return (
        <>
            <Head title="Browse Job Openings">
                <meta
                    name="description"
                    content="Discover published job openings from approved employers. Find your next career opportunity."
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <main className="mx-auto w-full max-w-[96rem] px-4 py-5 sm:px-6 lg:px-10 lg:pb-6">
                    {/* Header row */}
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                Browse jobs
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">
                                Find your next role
                            </h1>
                        </div>
                        {jobs.data.length > 0 && (
                            <p className="pb-1 text-xs text-muted-foreground">
                                <span className="font-medium text-foreground tabular-nums">
                                    {jobs.data.length}
                                </span>{' '}
                                {jobs.data.length === 1 ? 'role' : 'roles'}{' '}
                                loaded · newest first
                            </p>
                        )}
                    </div>

                    {/* Search & filter toolbar */}
                    <form
                        onSubmit={handleSearchSubmit}
                        className="mt-4 rounded-2xl border border-border bg-card p-3 shadow-sm sm:p-4"
                    >
                        <div className="flex flex-col gap-2 lg:flex-row">
                            <div className="relative flex-1">
                                <Search
                                    aria-hidden="true"
                                    className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    aria-label="Search by job title, skill, or keyword"
                                    placeholder="Job title, skill, or keyword…"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="h-11 pl-9 text-base"
                                />
                            </div>
                            <div className="relative flex-1 lg:max-w-xs">
                                <MapPin
                                    aria-hidden="true"
                                    className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    aria-label="Filter by location"
                                    placeholder="Location (e.g. Manila, Remote)…"
                                    value={location}
                                    onChange={(e) =>
                                        setLocation(e.target.value)
                                    }
                                    className="h-11 pr-9 pl-9 text-base"
                                />
                                {location !== '' && (
                                    <button
                                        type="button"
                                        aria-label="Clear location"
                                        onClick={() => {
                                            setLocation('');
                                            applyFilters({ location: null });
                                        }}
                                        className="absolute top-1/2 right-2 -translate-y-1/2 rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                            </div>
                            <Button type="submit" className="h-11 lg:w-32">
                                Search
                            </Button>
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3">
                            <SlidersHorizontal
                                aria-hidden="true"
                                className="size-4 text-muted-foreground"
                            />
                            <Select
                                value={selectedCategory}
                                onValueChange={(val) => {
                                    setSelectedCategory(val);
                                    applyFilters({
                                        category:
                                            val !== 'all'
                                                ? parseInt(val, 10)
                                                : null,
                                    });
                                }}
                            >
                                <SelectTrigger
                                    className={cn(
                                        selectTriggerClassName,
                                        'w-[9.5rem]',
                                    )}
                                    aria-label="Filter by category"
                                >
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Categories
                                    </SelectItem>
                                    {categories.map((cat) => (
                                        <SelectItem
                                            key={cat.id}
                                            value={String(cat.id)}
                                        >
                                            {cat.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={selectedEmploymentType}
                                onValueChange={(val) => {
                                    setSelectedEmploymentType(val);
                                    applyFilters({
                                        employment_type:
                                            val !== 'all'
                                                ? parseInt(val, 10)
                                                : null,
                                    });
                                }}
                            >
                                <SelectTrigger
                                    className={cn(
                                        selectTriggerClassName,
                                        'w-[9.5rem]',
                                    )}
                                    aria-label="Filter by employment type"
                                >
                                    <SelectValue placeholder="Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Types
                                    </SelectItem>
                                    {employmentTypes.map((et) => (
                                        <SelectItem
                                            key={et.id}
                                            value={String(et.id)}
                                        >
                                            {et.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={selectedWorkplace}
                                onValueChange={(val) => {
                                    setSelectedWorkplace(val);
                                    applyFilters({
                                        workplace_arrangement:
                                            val !== 'all'
                                                ? parseInt(val, 10)
                                                : null,
                                    });
                                }}
                            >
                                <SelectTrigger
                                    className={cn(
                                        selectTriggerClassName,
                                        'w-[9rem]',
                                    )}
                                    aria-label="Filter by workplace arrangement"
                                >
                                    <SelectValue placeholder="Arrangement" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Arrangements
                                    </SelectItem>
                                    {workplaceArrangements.map((wa) => (
                                        <SelectItem
                                            key={wa.id}
                                            value={String(wa.id)}
                                        >
                                            {wa.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select
                                value={selectedExperience}
                                onValueChange={(val) => {
                                    setSelectedExperience(val);
                                    applyFilters({
                                        experience_level:
                                            val !== 'all'
                                                ? parseInt(val, 10)
                                                : null,
                                    });
                                }}
                            >
                                <SelectTrigger
                                    className={cn(
                                        selectTriggerClassName,
                                        'w-[10.5rem]',
                                    )}
                                    aria-label="Filter by experience level"
                                >
                                    <SelectValue placeholder="Experience" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Levels
                                    </SelectItem>
                                    {experienceLevels.map((el) => (
                                        <SelectItem
                                            key={el.id}
                                            value={String(el.id)}
                                        >
                                            {el.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            {hasActiveFilters && (
                                <div className="ml-auto flex items-center gap-2">
                                    {isJobSeeker && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setSaveSearchOpen(true)
                                            }
                                            className="h-9"
                                        >
                                            Save this search
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleReset}
                                        className="h-9 text-muted-foreground"
                                    >
                                        <X className="mr-1 size-4" />
                                        Clear
                                    </Button>
                                </div>
                            )}
                        </div>

                        {activeChips.length > 0 && (
                            <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                {activeChips.map((chip) => (
                                    <button
                                        key={chip.key}
                                        type="button"
                                        onClick={() => removeChip(chip.key)}
                                        aria-label={`Remove filter ${chip.label}`}
                                        className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:border-primary/40 hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                    >
                                        <span className="max-w-48 truncate">
                                            {chip.label}
                                        </span>
                                        <X
                                            aria-hidden="true"
                                            className="size-3"
                                        />
                                    </button>
                                ))}
                            </div>
                        )}
                    </form>

                    {/* Master-detail split view */}
                    <div className="mt-4 grid items-start gap-4 lg:grid-cols-[24rem_minmax(0,1fr)] xl:grid-cols-[27rem_minmax(0,1fr)] 2xl:grid-cols-[30rem_minmax(0,1fr)]">
                        {/* LEFT: scrolling list */}
                        <section
                            aria-label="Job listings"
                            className="[scrollbar-width:thin] lg:sticky lg:top-[5.5rem] lg:max-h-[calc(100dvh-7rem)] lg:overflow-y-auto lg:pr-1"
                        >
                            {jobs.data.length > 0 ? (
                                <>
                                    <InfiniteScroll
                                        data="jobs"
                                        onlyNext
                                        buffer={250}
                                        loading={
                                            <div className="flex items-center justify-center gap-2 py-4 text-sm text-muted-foreground">
                                                <Loader2
                                                    aria-hidden="true"
                                                    className="size-4 animate-spin"
                                                />
                                                Loading more roles…
                                            </div>
                                        }
                                    >
                                        <div className="flex flex-col gap-2.5">
                                            {jobs.data.map((job) => {
                                                const isSaved =
                                                    savedJobIds.includes(
                                                        job.id,
                                                    );
                                                const salaryText =
                                                    formatSalary(job);
                                                const isSelected =
                                                    isDesktop &&
                                                    selectedJob?.id === job.id;
                                                const isNew = isWithinDays(
                                                    job.created_at,
                                                    3,
                                                );

                                                return (
                                                    <div
                                                        key={job.id}
                                                        className={cn(
                                                            'relative rounded-xl border bg-card p-3.5 shadow-sm transition-all',
                                                            isSelected
                                                                ? 'border-primary/60 ring-2 ring-primary/30'
                                                                : 'border-border hover:border-primary/40 hover:shadow-md',
                                                        )}
                                                    >
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleRowClick(
                                                                    job,
                                                                )
                                                            }
                                                            aria-pressed={
                                                                isSelected
                                                            }
                                                            aria-label={`${isDesktop ? 'Preview' : 'View'} ${job.title} at ${job.company?.name ?? 'company'}`}
                                                            className="absolute inset-0 rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                                        />
                                                        <div className="pointer-events-none relative flex flex-col gap-2">
                                                            <div className="flex items-center gap-2.5 pr-9">
                                                                {job.company
                                                                    ?.logo_url ? (
                                                                    <img
                                                                        src={
                                                                            job
                                                                                .company
                                                                                .logo_url
                                                                        }
                                                                        alt={`${job.company.name} logo`}
                                                                        className="size-9 shrink-0 rounded-lg border border-border object-cover"
                                                                        loading="lazy"
                                                                    />
                                                                ) : (
                                                                    <div
                                                                        aria-hidden="true"
                                                                        className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground"
                                                                    >
                                                                        <Building2 className="size-4" />
                                                                    </div>
                                                                )}
                                                                <p className="min-w-0 truncate text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                                                    {job.company
                                                                        ?.name ||
                                                                        'Company'}
                                                                </p>
                                                                {isNew && (
                                                                    <span className="shrink-0 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-emerald-800 uppercase dark:bg-emerald-950 dark:text-emerald-300">
                                                                        New
                                                                    </span>
                                                                )}
                                                            </div>

                                                            <h2 className="line-clamp-2 text-[15px] leading-snug font-semibold tracking-[-0.01em] text-foreground">
                                                                {job.title}
                                                            </h2>

                                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                                                                {job.location && (
                                                                    <span className="inline-flex min-w-0 items-center gap-1">
                                                                        <MapPin
                                                                            aria-hidden="true"
                                                                            className="size-3 shrink-0"
                                                                        />
                                                                        <span className="truncate">
                                                                            {
                                                                                job.location
                                                                            }
                                                                        </span>
                                                                    </span>
                                                                )}
                                                                {(job.employment_type ||
                                                                    job.workplace_arrangement) && (
                                                                    <span
                                                                        aria-hidden="true"
                                                                        className="text-border"
                                                                    >
                                                                        •
                                                                    </span>
                                                                )}
                                                                {job.employment_type && (
                                                                    <span className="truncate">
                                                                        {
                                                                            job
                                                                                .employment_type
                                                                                .name
                                                                        }
                                                                    </span>
                                                                )}
                                                                {job.employment_type &&
                                                                    job.workplace_arrangement && (
                                                                        <span
                                                                            aria-hidden="true"
                                                                            className="text-border"
                                                                        >
                                                                            ·
                                                                        </span>
                                                                    )}
                                                                {job.workplace_arrangement && (
                                                                    <span className="truncate">
                                                                        {
                                                                            job
                                                                                .workplace_arrangement
                                                                                .name
                                                                        }
                                                                    </span>
                                                                )}
                                                            </div>

                                                            <div className="flex items-center justify-between gap-3">
                                                                {salaryText ? (
                                                                    <p className="truncate text-[13px] font-semibold text-primary">
                                                                        {
                                                                            salaryText
                                                                        }
                                                                    </p>
                                                                ) : (
                                                                    <span />
                                                                )}
                                                                <p className="shrink-0 text-[11px] whitespace-nowrap text-muted-foreground">
                                                                    {relativePostedAt(
                                                                        job.created_at,
                                                                    )}
                                                                </p>
                                                            </div>
                                                        </div>

                                                        {isJobSeeker && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="absolute top-2.5 right-2.5 z-10 size-8 text-muted-foreground hover:text-primary"
                                                                onClick={() =>
                                                                    handleToggleSave(
                                                                        job.id,
                                                                        isSaved,
                                                                    )
                                                                }
                                                                title={
                                                                    isSaved
                                                                        ? 'Remove from saved jobs'
                                                                        : 'Save job'
                                                                }
                                                                aria-label={
                                                                    isSaved
                                                                        ? `Unsave ${job.title}`
                                                                        : `Save ${job.title}`
                                                                }
                                                                aria-pressed={
                                                                    isSaved
                                                                }
                                                            >
                                                                <Bookmark
                                                                    className={cn(
                                                                        'size-4',
                                                                        isSaved &&
                                                                            'fill-primary text-primary',
                                                                    )}
                                                                />
                                                            </Button>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </InfiniteScroll>

                                    {!hasNextPage && (
                                        <p className="py-4 text-center text-[11px] text-muted-foreground">
                                            You’ve reached the end ·{' '}
                                            {jobs.data.length}{' '}
                                            {jobs.data.length === 1
                                                ? 'role'
                                                : 'roles'}{' '}
                                            loaded
                                        </p>
                                    )}
                                </>
                            ) : (
                                <div className="rounded-2xl border border-border bg-card p-10 text-center">
                                    <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                        <Search
                                            aria-hidden="true"
                                            className="size-6"
                                        />
                                    </div>
                                    <h3 className="mt-4 text-lg font-semibold text-foreground">
                                        No roles match your search
                                    </h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Try broadening the keywords, location,
                                        or removing some filters.
                                    </p>
                                    {hasActiveFilters && (
                                        <Button
                                            variant="outline"
                                            onClick={handleReset}
                                            className="mt-6"
                                        >
                                            Clear all filters
                                        </Button>
                                    )}
                                </div>
                            )}
                        </section>

                        {/* RIGHT: sticky detail preview (desktop only) */}
                        <aside
                            aria-label="Job details preview"
                            className="hidden lg:sticky lg:top-[5.5rem] lg:block lg:max-h-[calc(100dvh-7rem)]"
                        >
                            {selectedJob ? (
                                <div className="flex max-h-[calc(100dvh-7rem)] flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                                    {/* Scrollable body */}
                                    <div className="flex-1 [scrollbar-width:thin] overflow-y-auto p-6">
                                        <div className="flex items-center gap-3">
                                            {selectedJob.company?.logo_url ? (
                                                <img
                                                    src={
                                                        selectedJob.company
                                                            .logo_url
                                                    }
                                                    alt={`${selectedJob.company.name} logo`}
                                                    className="size-14 rounded-xl border border-border object-cover"
                                                />
                                            ) : (
                                                <div
                                                    aria-hidden="true"
                                                    className="flex size-14 items-center justify-center rounded-xl border border-border bg-muted text-muted-foreground"
                                                >
                                                    <Building2 className="size-7" />
                                                </div>
                                            )}
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold text-foreground">
                                                    {selectedJob.company
                                                        ?.name || 'Company'}
                                                </p>
                                                {selectedJob.location && (
                                                    <p className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                                        <MapPin
                                                            aria-hidden="true"
                                                            className="size-3 shrink-0"
                                                        />
                                                        <span className="truncate">
                                                            {
                                                                selectedJob.location
                                                            }
                                                        </span>
                                                    </p>
                                                )}
                                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                                    Posted{' '}
                                                    {relativePostedAt(
                                                        selectedJob.created_at,
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        <h2 className="mt-5 text-2xl leading-tight font-bold tracking-[-0.02em] text-foreground">
                                            {selectedJob.title}
                                        </h2>

                                        {selectedJob.application_deadline && (
                                            <p
                                                className={cn(
                                                    'mt-1.5 flex items-center gap-1.5 text-xs',
                                                    daysUntil(
                                                        selectedJob.application_deadline,
                                                    ) <= 7
                                                        ? 'font-medium text-amber-700 dark:text-amber-400'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                <CalendarDays
                                                    aria-hidden="true"
                                                    className="size-3.5"
                                                />
                                                Apply by{' '}
                                                {formatDeadline(
                                                    selectedJob.application_deadline,
                                                )}
                                            </p>
                                        )}

                                        <div className="mt-4 flex flex-wrap gap-1.5">
                                            {selectedJob.category && (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    {selectedJob.category.name}
                                                </Badge>
                                            )}
                                            {selectedJob.employment_type && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {
                                                        selectedJob
                                                            .employment_type
                                                            .name
                                                    }
                                                </Badge>
                                            )}
                                            {selectedJob.workplace_arrangement && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {
                                                        selectedJob
                                                            .workplace_arrangement
                                                            .name
                                                    }
                                                </Badge>
                                            )}
                                            {selectedJob.experience_level && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {
                                                        selectedJob
                                                            .experience_level
                                                            .name
                                                    }
                                                </Badge>
                                            )}
                                        </div>

                                        <div className="my-5 border-t border-border" />

                                        {selectedSalaryText && (
                                            <section>
                                                <h3 className="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                    Compensation
                                                </h3>
                                                <p className="mt-2 text-base font-semibold text-primary">
                                                    {selectedSalaryText}
                                                </p>
                                                {selectedHasSalaryRange &&
                                                    selectedJob.salary_negotiable && (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Salary is negotiable
                                                        </p>
                                                    )}
                                            </section>
                                        )}

                                        {selectedJob.description && (
                                            <section className="mt-5">
                                                <h3 className="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                    Job description
                                                </h3>
                                                <div className="mt-2 text-sm leading-6 whitespace-pre-wrap text-foreground/90">
                                                    {selectedJob.description}
                                                </div>
                                            </section>
                                        )}

                                        {selectedJob.responsibilities && (
                                            <section className="mt-5">
                                                <h3 className="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                    Responsibilities
                                                </h3>
                                                <div className="mt-2 text-sm leading-6 whitespace-pre-wrap text-foreground/90">
                                                    {
                                                        selectedJob.responsibilities
                                                    }
                                                </div>
                                            </section>
                                        )}

                                        {selectedJob.requirements && (
                                            <section className="mt-5">
                                                <h3 className="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                    Requirements
                                                </h3>
                                                <div className="mt-2 text-sm leading-6 whitespace-pre-wrap text-foreground/90">
                                                    {selectedJob.requirements}
                                                </div>
                                            </section>
                                        )}

                                        {selectedJob.skills.length > 0 && (
                                            <section className="mt-5">
                                                <h3 className="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                                    Skills
                                                </h3>
                                                <div className="mt-2 flex flex-wrap gap-1.5">
                                                    {selectedJob.skills.map(
                                                        (skill) => (
                                                            <span
                                                                key={skill}
                                                                className="rounded-md border border-border bg-muted/60 px-2 py-1 text-xs font-medium text-foreground"
                                                            >
                                                                {skill}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                            </section>
                                        )}
                                    </div>

                                    {/* Sticky action bar */}
                                    <div className="flex items-center gap-2 border-t border-border bg-card p-4">
                                        {isJobSeeker && (
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                className="size-11 shrink-0"
                                                onClick={() =>
                                                    handleToggleSave(
                                                        selectedJob.id,
                                                        savedJobIds.includes(
                                                            selectedJob.id,
                                                        ),
                                                    )
                                                }
                                                title={
                                                    savedJobIds.includes(
                                                        selectedJob.id,
                                                    )
                                                        ? 'Remove from saved jobs'
                                                        : 'Save job'
                                                }
                                                aria-label={
                                                    savedJobIds.includes(
                                                        selectedJob.id,
                                                    )
                                                        ? 'Unsave this job'
                                                        : 'Save this job'
                                                }
                                                aria-pressed={savedJobIds.includes(
                                                    selectedJob.id,
                                                )}
                                            >
                                                <Bookmark
                                                    className={cn(
                                                        'size-5',
                                                        savedJobIds.includes(
                                                            selectedJob.id,
                                                        ) &&
                                                            'fill-primary text-primary',
                                                    )}
                                                />
                                            </Button>
                                        )}
                                        <Button
                                            asChild
                                            size="lg"
                                            className="h-11 flex-1 text-base"
                                        >
                                            <Link
                                                href={`/jobs/${selectedJob.id}`}
                                            >
                                                View full details &amp; apply
                                                <ArrowRight
                                                    aria-hidden="true"
                                                    className="ml-1 size-4"
                                                />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex h-full min-h-96 flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card/50 p-10 text-center">
                                    <Building2
                                        aria-hidden="true"
                                        className="size-10 text-muted-foreground"
                                    />
                                    <p className="mt-4 text-sm font-medium text-muted-foreground">
                                        Select a role on the left to preview it
                                        here.
                                    </p>
                                </div>
                            )}
                        </aside>
                    </div>

                    <SaveSearchDialog
                        open={saveSearchOpen}
                        onOpenChange={setSaveSearchOpen}
                        mode="create"
                        filterOptions={{
                            categories,
                            employmentTypes,
                            workplaceArrangements,
                            experienceLevels,
                        }}
                    />
                </main>
            </div>
        </>
    );
}
