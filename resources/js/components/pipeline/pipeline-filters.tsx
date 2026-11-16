import { Search, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { PipelineCompany, PipelineJobOption } from '@/types/pipeline';

const ALL_JOBS = 'all';

export type PipelineFiltersProps = {
    companies: PipelineCompany[];
    jobs: PipelineJobOption[];
    selectedCompanyId: number | null;
    selectedJobId: number | null;
    search: string;
    isNavigating: boolean;
    onCompanyChange: (companyId: number) => void;
    onJobChange: (jobId: number | null) => void;
    onSearchSubmit: (term: string) => void;
    onClearFilters: () => void;
};

/**
 * The server driven filter bar of the pipeline board.
 *
 * Every control navigates through the Inertia router: the board never filters
 * the applications in the browser, so the counts and the cards always agree
 * with the query string.
 */
export default function PipelineFilters({
    companies,
    jobs,
    selectedCompanyId,
    selectedJobId,
    search,
    isNavigating,
    onCompanyChange,
    onJobChange,
    onSearchSubmit,
    onClearFilters,
}: PipelineFiltersProps) {
    const [term, setTerm] = useState(search);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        onSearchSubmit(term);
    };

    const handleClear = () => {
        setTerm('');
        onClearFilters();
    };

    const hasActiveFilters = selectedJobId !== null || search !== '';

    return (
        <Card className="border-border">
            <CardContent className="flex flex-col gap-4 p-4 xl:flex-row xl:items-end">
                <div className="w-full xl:w-56">
                    <Label htmlFor="pipeline-company">Company</Label>
                    <Select
                        value={
                            selectedCompanyId !== null
                                ? String(selectedCompanyId)
                                : undefined
                        }
                        onValueChange={(value) =>
                            onCompanyChange(Number(value))
                        }
                        disabled={isNavigating}
                    >
                        <SelectTrigger id="pipeline-company" className="w-full">
                            <SelectValue placeholder="Select a company" />
                        </SelectTrigger>
                        <SelectContent>
                            {companies.map((company) => (
                                <SelectItem
                                    key={company.id}
                                    value={String(company.id)}
                                >
                                    {company.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="w-full xl:w-64">
                    <Label htmlFor="pipeline-job">Job</Label>
                    <Select
                        value={
                            selectedJobId !== null
                                ? String(selectedJobId)
                                : ALL_JOBS
                        }
                        onValueChange={(value) =>
                            onJobChange(
                                value === ALL_JOBS ? null : Number(value),
                            )
                        }
                        disabled={isNavigating}
                    >
                        <SelectTrigger id="pipeline-job" className="w-full">
                            <SelectValue placeholder="All jobs" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_JOBS}>All jobs</SelectItem>
                            {jobs.map((job) => (
                                <SelectItem key={job.id} value={String(job.id)}>
                                    {job.title} ({job.count})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="flex w-full flex-col gap-2 sm:flex-row sm:items-end xl:w-auto"
                >
                    <div className="w-full sm:w-64">
                        <Label htmlFor="pipeline-search">Candidate name</Label>
                        <Input
                            id="pipeline-search"
                            name="q"
                            type="search"
                            value={term}
                            maxLength={100}
                            placeholder="Search by candidate name"
                            onChange={(event) => setTerm(event.target.value)}
                            disabled={isNavigating}
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="secondary"
                        disabled={isNavigating}
                    >
                        {isNavigating ? (
                            <Spinner />
                        ) : (
                            <Search className="size-4" />
                        )}
                        Search
                    </Button>
                </form>

                <Button
                    type="button"
                    variant="ghost"
                    className="w-fit"
                    onClick={handleClear}
                    disabled={isNavigating || !hasActiveFilters}
                >
                    <X className="size-4" />
                    Clear filters
                </Button>
            </CardContent>
        </Card>
    );
}
