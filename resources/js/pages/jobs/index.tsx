import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    Calendar,
    MapPin,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
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

interface Job {
    id: number;
    title: string;
    location: string | null;
    min_salary: string | null;
    max_salary: string | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    published_at: string | null;
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
    jobs: {
        data: Job[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    categories: FilterOption[];
    employmentTypes: FilterOption[];
    workplaceArrangements: FilterOption[];
    experienceLevels: FilterOption[];
    filters: {
        search: string | null;
        category: number | null;
        employment_type: number | null;
        workplace_arrangement: number | null;
        experience_level: number | null;
    };
}

export default function PublicJobsIndex({
    jobs,
    categories,
    employmentTypes,
    workplaceArrangements,
    experienceLevels,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
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

    const applyFilters = (newFilters: Record<string, any>) => {
        router.get(
            '/jobs',
            {
                search: search !== '' ? search : null,
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
            { preserveState: true, replace: true },
        );
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({});
    };

    const handleReset = () => {
        setSearch('');
        setSelectedCategory('all');
        setSelectedEmploymentType('all');
        setSelectedWorkplace('all');
        setSelectedExperience('all');
        router.get('/jobs', {}, { preserveState: true, replace: true });
    };

    const formatSalary = (job: Job) => {
        if (!job.min_salary && !job.max_salary) {
            return job.salary_negotiable ? 'Salary negotiable' : null;
        }
        const curr = job.salary_currency || 'USD';
        const period = job.pay_period ? ` / ${job.pay_period.name}` : '';
        if (job.min_salary && job.max_salary) {
            return `${curr} ${Number(job.min_salary).toLocaleString()} - ${Number(job.max_salary).toLocaleString()}${period}`;
        }
        if (job.min_salary) {
            return `From ${curr} ${Number(job.min_salary).toLocaleString()}${period}`;
        }
        if (job.max_salary) {
            return `Up to ${curr} ${Number(job.max_salary).toLocaleString()}${period}`;
        }
        return null;
    };

    const hasActiveFilters =
        Boolean(search) ||
        selectedCategory !== 'all' ||
        selectedEmploymentType !== 'all' ||
        selectedWorkplace !== 'all' ||
        selectedExperience !== 'all';

    return (
        <>
            <Head title="Browse Job Openings">
                <meta
                    name="description"
                    content="Discover published job openings from approved employers. Find your next career opportunity."
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    {/* Title & Intro */}
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                            Explore Career Opportunities
                        </h1>
                        <p className="mt-2 text-base text-muted-foreground">
                            Browse verified job openings from top employers.
                            Filter by category, arrangement, and experience.
                        </p>
                    </div>

                    {/* Search & Filters Form */}
                    <div className="mb-8 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6">
                        <form
                            onSubmit={handleSearchSubmit}
                            className="space-y-4"
                        >
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <div className="relative flex-1">
                                    <Search className="absolute top-3 left-3 size-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search job title, keywords, or location..."
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        className="pl-9"
                                    />
                                </div>
                                <Button type="submit" className="sm:w-auto">
                                    Search Jobs
                                </Button>
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {/* Category Filter */}
                                <div>
                                    <label className="mb-1.5 block text-xs font-medium text-muted-foreground">
                                        Category
                                    </label>
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
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="All Categories" />
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
                                </div>

                                {/* Employment Type Filter */}
                                <div>
                                    <label className="mb-1.5 block text-xs font-medium text-muted-foreground">
                                        Employment Type
                                    </label>
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
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="All Employment Types" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All Employment Types
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
                                </div>

                                {/* Workplace Arrangement Filter */}
                                <div>
                                    <label className="mb-1.5 block text-xs font-medium text-muted-foreground">
                                        Workplace Arrangement
                                    </label>
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
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="All Arrangements" />
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
                                </div>

                                {/* Experience Level Filter */}
                                <div>
                                    <label className="mb-1.5 block text-xs font-medium text-muted-foreground">
                                        Experience Level
                                    </label>
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
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="All Experience Levels" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All Experience Levels
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
                                </div>
                            </div>

                            {hasActiveFilters && (
                                <div className="flex items-center justify-between border-t border-border pt-2">
                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <SlidersHorizontal className="size-3.5" />
                                        <span>Filters applied</span>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleReset}
                                        className="h-8 px-2 text-xs"
                                    >
                                        <X className="mr-1 size-3.5" />
                                        Reset all filters
                                    </Button>
                                </div>
                            )}
                        </form>
                    </div>

                    {/* Job Listings Grid */}
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {jobs.data.map((job) => {
                            const salaryText = formatSalary(job);
                            return (
                                <div
                                    key={job.id}
                                    className="flex flex-col justify-between rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:border-primary/50 hover:shadow-md"
                                >
                                    <div>
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-3">
                                                {job.company?.logo_url ? (
                                                    <img
                                                        src={
                                                            job.company.logo_url
                                                        }
                                                        alt={job.company.name}
                                                        className="size-10 rounded-lg border border-border object-cover"
                                                    />
                                                ) : (
                                                    <div className="flex size-10 items-center justify-center rounded-lg border border-border bg-muted text-muted-foreground">
                                                        <Building2 className="size-5" />
                                                    </div>
                                                )}
                                                <div>
                                                    <h3 className="font-semibold text-foreground">
                                                        {job.company?.name ||
                                                            'Company'}
                                                    </h3>
                                                    {job.location && (
                                                        <p className="flex items-center gap-1 text-xs text-muted-foreground">
                                                            <MapPin className="size-3" />
                                                            {job.location}
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <h2 className="mt-4 text-lg font-bold tracking-tight text-foreground">
                                            {job.title}
                                        </h2>

                                        <div className="mt-3 flex flex-wrap gap-1.5">
                                            {job.category && (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    {job.category.name}
                                                </Badge>
                                            )}
                                            {job.employment_type && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {job.employment_type.name}
                                                </Badge>
                                            )}
                                            {job.workplace_arrangement && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {
                                                        job
                                                            .workplace_arrangement
                                                            .name
                                                    }
                                                </Badge>
                                            )}
                                            {job.experience_level && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {job.experience_level.name}
                                                </Badge>
                                            )}
                                        </div>

                                        {salaryText && (
                                            <p className="mt-4 text-sm font-medium text-foreground">
                                                {salaryText}
                                            </p>
                                        )}
                                    </div>

                                    <div className="mt-6 flex items-center justify-between border-t border-border pt-4 text-xs text-muted-foreground">
                                        <span className="flex items-center gap-1">
                                            <Calendar className="size-3.5" />
                                            {job.published_at
                                                ? new Date(
                                                      job.published_at,
                                                  ).toLocaleDateString()
                                                : 'Recently'}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="default"
                                            asChild
                                        >
                                            <Link href={`/jobs/${job.id}`}>
                                                View Role
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    {jobs.data.length === 0 && (
                        <div className="my-16 rounded-2xl border border-border bg-card p-12 text-center">
                            <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Search className="size-6" />
                            </div>
                            <h3 className="mt-4 text-lg font-semibold text-foreground">
                                No job openings found
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Try adjusting your search criteria or filters to
                                find what you're looking for.
                            </p>
                            {hasActiveFilters && (
                                <Button
                                    variant="outline"
                                    onClick={handleReset}
                                    className="mt-6"
                                >
                                    Reset all filters
                                </Button>
                            )}
                        </div>
                    )}

                    {/* Pagination */}
                    {jobs.total > 0 && (
                        <div className="mt-8">
                            <Pagination
                                currentPage={jobs.current_page}
                                lastPage={jobs.last_page}
                                perPage={jobs.per_page}
                                total={jobs.total}
                                prevPageUrl={jobs.prev_page_url}
                                nextPageUrl={jobs.next_page_url}
                            />
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
