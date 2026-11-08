import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useFlashToast } from '@/hooks/use-flash-toast';
import {
    update as jobsUpdate,
    submit as jobsSubmit,
    close as jobsClose,
} from '@/routes/employer/jobs';
import { index as screeningQuestionsIndex } from '@/routes/employer/jobs/screening-questions';
import { index as companiesIndex } from '@/routes/employer/companies';
import type { JobForm, JobEditProps, CatalogueOption } from '@/types';

export default function EmployerJobEdit({
    job,
    companies,
    jobCategories,
    employmentTypes,
    workplaceArrangements,
    experienceLevels,
    payPeriods,
}: JobEditProps) {
    useFlashToast();

    const form = useForm<JobForm>({
        company_id: job.company_id,
        title: job.title,
        description: job.description,
        responsibilities: job.responsibilities,
        requirements: job.requirements,
        category_id: job.category_id,
        employment_type_id: job.employment_type_id,
        workplace_arrangement_id: job.workplace_arrangement_id,
        experience_level_id: job.experience_level_id,
        salary_min: job.salary_min?.toString() ?? '',
        salary_max: job.salary_max?.toString() ?? '',
        salary_currency: job.salary_currency ?? '',
        salary_negotiable: job.salary_negotiable,
        pay_period_id: job.pay_period_id,
        location: job.location ?? '',
        application_deadline: job.application_deadline
            ? job.application_deadline.split('T')[0]
            : '',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(jobsUpdate.url({ job: job.id }), {
            preserveScroll: true,
        });
    };

    const hasSalary = Boolean(form.data.salary_min || form.data.salary_max);

    return (
        <>
            <Head title={`Edit Job: ${job.title}`} />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={companiesIndex()}>
                            <ArrowLeft className="size-4" />
                            Back
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Edit Job Draft
                        </h1>
                        <p className="text-sm text-muted-foreground md:text-base">
                            Update your job draft. Status: {job.status}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={screeningQuestionsIndex({ job: job.id })}
                            >
                                Screening Questions
                            </Link>
                        </Button>
                        {['draft', 'rejected', 'closed'].includes(
                            job.status,
                        ) && (
                            <Button
                                type="button"
                                onClick={() =>
                                    router.post(
                                        jobsSubmit.url({ job: job.id }),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Submit for Review
                            </Button>
                        )}
                        {['published', 'pending_review'].includes(
                            job.status,
                        ) && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        jobsClose.url({ job: job.id }),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                Close Job
                            </Button>
                        )}
                    </div>
                </div>

                {job.rejection_reason && (
                    <div className="rounded-lg border border-destructive/50 bg-destructive/5 p-4 text-destructive">
                        <h3 className="font-semibold">Rejection Reason</h3>
                        <p className="mt-1 text-sm">{job.rejection_reason}</p>
                    </div>
                )}

                <Card className="border-border">
                    <CardHeader>
                        <CardTitle className="text-lg">Job details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-6"
                            aria-busy={form.processing}
                        >
                            {/* Company (locked on edit) */}
                            <div className="space-y-2">
                                <Label htmlFor="company_id">
                                    Company{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={
                                        form.data.company_id?.toString() ?? ''
                                    }
                                    disabled={true}
                                >
                                    <SelectTrigger
                                        id="company_id"
                                        className="opacity-60"
                                    >
                                        <SelectValue placeholder="Select a company" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {companies.map((company) => (
                                            <SelectItem
                                                key={company.id}
                                                value={company.id.toString()}
                                            >
                                                {company.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    Company cannot be changed after creation
                                </p>
                            </div>

                            {/* Title */}
                            <div className="space-y-2">
                                <Label htmlFor="title">
                                    Job Title{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    type="text"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    disabled={form.processing}
                                    maxLength={150}
                                    aria-invalid={!!form.errors.title}
                                    aria-describedby={
                                        form.errors.title
                                            ? 'title-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="title-error"
                                    message={form.errors.title}
                                />
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="description">
                                    Job Description{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                    rows={6}
                                    maxLength={20000}
                                    aria-invalid={!!form.errors.description}
                                    aria-describedby={
                                        form.errors.description
                                            ? 'description-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="description-error"
                                    message={form.errors.description}
                                />
                            </div>

                            {/* Responsibilities */}
                            <div className="space-y-2">
                                <Label htmlFor="responsibilities">
                                    Responsibilities
                                </Label>
                                <Textarea
                                    id="responsibilities"
                                    value={form.data.responsibilities ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'responsibilities',
                                            e.target.value || null,
                                        )
                                    }
                                    disabled={form.processing}
                                    rows={4}
                                    maxLength={20000}
                                    aria-invalid={
                                        !!form.errors.responsibilities
                                    }
                                    aria-describedby={
                                        form.errors.responsibilities
                                            ? 'responsibilities-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="responsibilities-error"
                                    message={form.errors.responsibilities}
                                />
                            </div>

                            {/* Requirements */}
                            <div className="space-y-2">
                                <Label htmlFor="requirements">
                                    Requirements
                                </Label>
                                <Textarea
                                    id="requirements"
                                    value={form.data.requirements ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'requirements',
                                            e.target.value || null,
                                        )
                                    }
                                    disabled={form.processing}
                                    rows={4}
                                    maxLength={20000}
                                    aria-invalid={!!form.errors.requirements}
                                    aria-describedby={
                                        form.errors.requirements
                                            ? 'requirements-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="requirements-error"
                                    message={form.errors.requirements}
                                />
                            </div>

                            {/* Category */}
                            <div className="space-y-2">
                                <Label htmlFor="category_id">
                                    Category{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={
                                        form.data.category_id?.toString() ?? ''
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'category_id',
                                            value ? parseInt(value, 10) : null,
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger
                                        id="category_id"
                                        aria-invalid={!!form.errors.category_id}
                                        aria-describedby={
                                            form.errors.category_id
                                                ? 'category_id-error'
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Select a category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {jobCategories.map(
                                            (category: CatalogueOption) => (
                                                <SelectItem
                                                    key={category.id}
                                                    value={category.id.toString()}
                                                >
                                                    {category.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="category_id-error"
                                    message={form.errors.category_id}
                                />
                            </div>

                            {/* Employment Type */}
                            <div className="space-y-2">
                                <Label htmlFor="employment_type_id">
                                    Employment Type{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={
                                        form.data.employment_type_id?.toString() ??
                                        ''
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'employment_type_id',
                                            value ? parseInt(value, 10) : null,
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger
                                        id="employment_type_id"
                                        aria-invalid={
                                            !!form.errors.employment_type_id
                                        }
                                        aria-describedby={
                                            form.errors.employment_type_id
                                                ? 'employment_type_id-error'
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Select employment type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employmentTypes.map(
                                            (type: CatalogueOption) => (
                                                <SelectItem
                                                    key={type.id}
                                                    value={type.id.toString()}
                                                >
                                                    {type.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="employment_type_id-error"
                                    message={form.errors.employment_type_id}
                                />
                            </div>

                            {/* Workplace Arrangement */}
                            <div className="space-y-2">
                                <Label htmlFor="workplace_arrangement_id">
                                    Workplace Arrangement
                                </Label>
                                <Select
                                    value={
                                        form.data.workplace_arrangement_id?.toString() ??
                                        ''
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'workplace_arrangement_id',
                                            value ? parseInt(value, 10) : null,
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger
                                        id="workplace_arrangement_id"
                                        aria-invalid={
                                            !!form.errors
                                                .workplace_arrangement_id
                                        }
                                        aria-describedby={
                                            form.errors.workplace_arrangement_id
                                                ? 'workplace_arrangement_id-error'
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Select workplace arrangement" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {workplaceArrangements.map(
                                            (arrangement: CatalogueOption) => (
                                                <SelectItem
                                                    key={arrangement.id}
                                                    value={arrangement.id.toString()}
                                                >
                                                    {arrangement.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="workplace_arrangement_id-error"
                                    message={
                                        form.errors.workplace_arrangement_id
                                    }
                                />
                            </div>

                            {/* Experience Level */}
                            <div className="space-y-2">
                                <Label htmlFor="experience_level_id">
                                    Experience Level
                                </Label>
                                <Select
                                    value={
                                        form.data.experience_level_id?.toString() ??
                                        ''
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'experience_level_id',
                                            value ? parseInt(value, 10) : null,
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger
                                        id="experience_level_id"
                                        aria-invalid={
                                            !!form.errors.experience_level_id
                                        }
                                        aria-describedby={
                                            form.errors.experience_level_id
                                                ? 'experience_level_id-error'
                                                : undefined
                                        }
                                    >
                                        <SelectValue placeholder="Select experience level" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {experienceLevels.map(
                                            (level: CatalogueOption) => (
                                                <SelectItem
                                                    key={level.id}
                                                    value={level.id.toString()}
                                                >
                                                    {level.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="experience_level_id-error"
                                    message={form.errors.experience_level_id}
                                />
                            </div>

                            {/* Salary Section */}
                            <div className="space-y-4 rounded-lg border border-border p-4">
                                <h3 className="font-medium">
                                    Salary (Optional)
                                </h3>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="salary_min">
                                            Minimum Salary
                                        </Label>
                                        <Input
                                            id="salary_min"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.data.salary_min}
                                            onChange={(e) =>
                                                form.setData(
                                                    'salary_min',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={form.processing}
                                            aria-invalid={
                                                !!form.errors.salary_min
                                            }
                                            aria-describedby={
                                                form.errors.salary_min
                                                    ? 'salary_min-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="salary_min-error"
                                            message={form.errors.salary_min}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="salary_max">
                                            Maximum Salary
                                        </Label>
                                        <Input
                                            id="salary_max"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={form.data.salary_max}
                                            onChange={(e) =>
                                                form.setData(
                                                    'salary_max',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={form.processing}
                                            aria-invalid={
                                                !!form.errors.salary_max
                                            }
                                            aria-describedby={
                                                form.errors.salary_max
                                                    ? 'salary_max-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="salary_max-error"
                                            message={form.errors.salary_max}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="salary_currency">
                                            Currency{' '}
                                            {hasSalary && (
                                                <span className="text-red-500">
                                                    *
                                                </span>
                                            )}
                                        </Label>
                                        <Input
                                            id="salary_currency"
                                            type="text"
                                            maxLength={3}
                                            placeholder="USD"
                                            value={form.data.salary_currency}
                                            onChange={(e) =>
                                                form.setData(
                                                    'salary_currency',
                                                    e.target.value.toUpperCase(),
                                                )
                                            }
                                            disabled={form.processing}
                                            aria-invalid={
                                                !!form.errors.salary_currency
                                            }
                                            aria-describedby={
                                                form.errors.salary_currency
                                                    ? 'salary_currency-error'
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            id="salary_currency-error"
                                            message={
                                                form.errors.salary_currency
                                            }
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="pay_period_id">
                                            Pay Period{' '}
                                            {hasSalary && (
                                                <span className="text-red-500">
                                                    *
                                                </span>
                                            )}
                                        </Label>
                                        <Select
                                            value={
                                                form.data.pay_period_id?.toString() ??
                                                ''
                                            }
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'pay_period_id',
                                                    value
                                                        ? parseInt(value, 10)
                                                        : null,
                                                )
                                            }
                                            disabled={form.processing}
                                        >
                                            <SelectTrigger
                                                id="pay_period_id"
                                                aria-invalid={
                                                    !!form.errors.pay_period_id
                                                }
                                                aria-describedby={
                                                    form.errors.pay_period_id
                                                        ? 'pay_period_id-error'
                                                        : undefined
                                                }
                                            >
                                                <SelectValue placeholder="Select pay period" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {payPeriods.map(
                                                    (
                                                        period: CatalogueOption,
                                                    ) => (
                                                        <SelectItem
                                                            key={period.id}
                                                            value={
                                                                period.id
                                                                    ? period.id.toString()
                                                                    : ''
                                                            }
                                                        >
                                                            {period.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            id="pay_period_id-error"
                                            message={form.errors.pay_period_id}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="salary_negotiable"
                                        checked={form.data.salary_negotiable}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'salary_negotiable',
                                                checked === true,
                                            )
                                        }
                                        disabled={form.processing}
                                    />
                                    <Label
                                        htmlFor="salary_negotiable"
                                        className="cursor-pointer font-normal"
                                    >
                                        Salary is negotiable
                                    </Label>
                                </div>
                            </div>

                            {/* Location */}
                            <div className="space-y-2">
                                <Label htmlFor="location">Location</Label>
                                <Input
                                    id="location"
                                    type="text"
                                    value={form.data.location}
                                    onChange={(e) =>
                                        form.setData('location', e.target.value)
                                    }
                                    disabled={form.processing}
                                    maxLength={255}
                                    aria-invalid={!!form.errors.location}
                                    aria-describedby={
                                        form.errors.location
                                            ? 'location-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="location-error"
                                    message={form.errors.location}
                                />
                            </div>

                            {/* Application Deadline */}
                            <div className="space-y-2">
                                <Label htmlFor="application_deadline">
                                    Application Deadline
                                </Label>
                                <Input
                                    id="application_deadline"
                                    type="date"
                                    value={form.data.application_deadline}
                                    onChange={(e) =>
                                        form.setData(
                                            'application_deadline',
                                            e.target.value,
                                        )
                                    }
                                    disabled={form.processing}
                                    aria-invalid={
                                        !!form.errors.application_deadline
                                    }
                                    aria-describedby={
                                        form.errors.application_deadline
                                            ? 'application_deadline-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="application_deadline-error"
                                    message={form.errors.application_deadline}
                                />
                            </div>

                            <div className="flex gap-4 pt-4">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="min-w-32"
                                >
                                    {form.processing && (
                                        <Spinner className="mr-2" />
                                    )}
                                    Save Changes
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    asChild
                                    disabled={form.processing}
                                >
                                    <Link href={companiesIndex()}>Cancel</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
