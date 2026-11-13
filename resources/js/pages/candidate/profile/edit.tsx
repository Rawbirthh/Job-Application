import { FormEvent, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import { useFlashToast } from '@/hooks/use-flash-toast';
import candidate from '@/routes/candidate';
import type {
    CandidateEducation,
    CandidateExperience,
    CandidateProfileEditProps,
    CandidateProfileForm,
} from '@/types';

type Props = CandidateProfileEditProps;

type FormErrors = Record<string, string | undefined>;

function isBlankEducation(row: CandidateEducation): boolean {
    return (
        !row.institution?.trim() &&
        !row.degree?.trim() &&
        !row.field_of_study?.trim() &&
        !row.start_date &&
        !row.end_date &&
        !row.description?.trim()
    );
}

function isBlankExperience(row: CandidateExperience): boolean {
    return (
        !row.company_name?.trim() &&
        !row.job_title?.trim() &&
        !row.location?.trim() &&
        !row.start_date &&
        !row.end_date &&
        !row.is_current &&
        !row.description?.trim()
    );
}

export default function CandidateProfileEdit({ profile, options }: Props) {
    useFlashToast();

    const form = useForm<CandidateProfileForm>({
        headline: profile.headline,
        summary: profile.summary,
        location: profile.location,
        skills: profile.skills ?? [],
        preferred_category_id: profile.preferred_category_id,
        preferred_experience_level_id: profile.preferred_experience_level_id,
        preferred_workplace_arrangement_id:
            profile.preferred_workplace_arrangement_id,
        educations: (profile.educations ?? []).map((education) => ({
            id: education.id,
            institution: education.institution ?? '',
            degree: education.degree ?? null,
            field_of_study: education.field_of_study ?? null,
            start_date: education.start_date ?? null,
            end_date: education.end_date ?? null,
            description: education.description ?? null,
        })),
        experiences: (profile.experiences ?? []).map((experience) => ({
            id: experience.id,
            company_name: experience.company_name ?? '',
            job_title: experience.job_title ?? '',
            location: experience.location ?? null,
            start_date: experience.start_date ?? null,
            end_date: experience.end_date ?? null,
            is_current: experience.is_current ?? false,
            description: experience.description ?? null,
        })),
    });

    const [skillInput, setSkillInput] = useState('');

    const errors = form.errors as FormErrors;

    const addSkill = () => {
        const value = skillInput.trim();
        if (!value) return;
        const exists = form.data.skills.some(
            (skill) => skill.toLowerCase() === value.toLowerCase(),
        );
        if (exists) {
            setSkillInput('');
            return;
        }
        form.setData('skills', [...form.data.skills, value]);
        setSkillInput('');
    };

    const handleSkillKeyDown = (
        event: React.KeyboardEvent<HTMLInputElement>,
    ) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            addSkill();
        }
    };

    const removeSkill = (index: number) => {
        form.setData(
            'skills',
            form.data.skills.filter((_, i) => i !== index),
        );
    };

    const addEducation = () => {
        form.setData('educations', [
            ...form.data.educations,
            {
                institution: '',
                degree: null,
                field_of_study: null,
                start_date: null,
                end_date: null,
                description: null,
            },
        ]);
    };

    const updateEducation = (
        index: number,
        field: keyof CandidateEducation,
        value: string | boolean | null,
    ) => {
        const next = [...form.data.educations];
        next[index] = { ...next[index], [field]: value };
        form.setData('educations', next);
    };

    const removeEducation = (index: number) => {
        form.setData(
            'educations',
            form.data.educations.filter((_, i) => i !== index),
        );
    };

    const addExperience = () => {
        form.setData('experiences', [
            ...form.data.experiences,
            {
                company_name: '',
                job_title: '',
                location: null,
                start_date: null,
                end_date: null,
                is_current: false,
                description: null,
            },
        ]);
    };

    const updateExperience = (
        index: number,
        field: keyof CandidateExperience,
        value: string | boolean | null,
    ) => {
        const next = [...form.data.experiences];
        next[index] = { ...next[index], [field]: value };
        if (field === 'is_current' && value === true) {
            next[index].end_date = null;
        }
        form.setData('experiences', next);
    };

    const removeExperience = (index: number) => {
        form.setData(
            'experiences',
            form.data.experiences.filter((_, i) => i !== index),
        );
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            skills: Array.from(
                new Set(
                    data.skills.map((skill) => skill.trim()).filter(Boolean),
                ),
            ),
            educations: data.educations
                .filter((row) => !isBlankEducation(row))
                .map((row) => ({
                    ...row,
                    institution: row.institution.trim(),
                })),
            experiences: data.experiences
                .filter((row) => !isBlankExperience(row))
                .map((row) => ({
                    ...row,
                    company_name: row.company_name.trim(),
                    job_title: row.job_title.trim(),
                    end_date: row.is_current ? null : row.end_date,
                })),
        }));

        form.put(candidate.profile.update.url(), {
            preserveScroll: true,
        });
    };

    const lastUpdated = profile.updated_at
        ? new Date(profile.updated_at).toLocaleString()
        : null;

    return (
        <>
            <Head title="Edit Candidate Profile" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={candidate.dashboard()}>
                            <ArrowLeft className="size-4" />
                            Back to dashboard
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Candidate Profile
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Maintain your professional headline, summary, skills,
                        education, and work experience.
                    </p>
                    {lastUpdated && (
                        <p className="text-xs text-muted-foreground">
                            Last updated: {lastUpdated}
                        </p>
                    )}
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6"
                    aria-busy={form.processing}
                >
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Basic information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="headline">Headline</Label>
                                <Input
                                    id="headline"
                                    value={form.data.headline ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'headline',
                                            e.target.value === ''
                                                ? null
                                                : e.target.value,
                                        )
                                    }
                                    maxLength={150}
                                    disabled={form.processing}
                                    aria-invalid={
                                        errors.headline ? true : undefined
                                    }
                                    aria-describedby="headline-error"
                                />
                                <InputError
                                    id="headline-error"
                                    message={errors.headline}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="summary">Summary</Label>
                                <Textarea
                                    id="summary"
                                    value={form.data.summary ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'summary',
                                            e.target.value === ''
                                                ? null
                                                : e.target.value,
                                        )
                                    }
                                    maxLength={5000}
                                    rows={5}
                                    disabled={form.processing}
                                    aria-invalid={
                                        errors.summary ? true : undefined
                                    }
                                    aria-describedby="summary-error"
                                />
                                <InputError
                                    id="summary-error"
                                    message={errors.summary}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="location">Location</Label>
                                <Input
                                    id="location"
                                    value={form.data.location ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'location',
                                            e.target.value === ''
                                                ? null
                                                : e.target.value,
                                        )
                                    }
                                    maxLength={255}
                                    disabled={form.processing}
                                    aria-invalid={
                                        errors.location ? true : undefined
                                    }
                                    aria-describedby="location-error"
                                />
                                <InputError
                                    id="location-error"
                                    message={errors.location}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">Skills</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex gap-2">
                                <Input
                                    id="skill-input"
                                    value={skillInput}
                                    onChange={(e) =>
                                        setSkillInput(e.target.value)
                                    }
                                    onKeyDown={handleSkillKeyDown}
                                    maxLength={50}
                                    disabled={form.processing}
                                    placeholder="Type a skill and press Enter"
                                    aria-label="Add skill"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addSkill}
                                    disabled={form.processing}
                                >
                                    <Plus className="size-4" />
                                    Add
                                </Button>
                            </div>
                            <InputError message={errors.skills} />
                            {form.data.skills.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No skills added yet.
                                </p>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    {form.data.skills.map((skill, index) => (
                                        <Badge
                                            key={`${skill}-${index}`}
                                            variant="secondary"
                                            className="flex items-center gap-1"
                                        >
                                            {skill}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeSkill(index)
                                                }
                                                disabled={form.processing}
                                                className="rounded-sm text-muted-foreground hover:text-foreground"
                                                aria-label={`Remove ${skill}`}
                                            >
                                                <X className="size-3" />
                                            </button>
                                        </Badge>
                                    ))}
                                </div>
                            )}
                            {form.data.skills.map((_, index) => (
                                <InputError
                                    key={`skill-error-${index}`}
                                    message={errors[`skills.${index}`]}
                                />
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Job preferences
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                These optional preferences are used to rank
                                recommended jobs. Leave one empty to ignore that
                                signal.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="preferred_category_id">
                                    Preferred job category
                                </Label>
                                <Select
                                    value={
                                        form.data.preferred_category_id
                                            ? String(
                                                  form.data
                                                      .preferred_category_id,
                                              )
                                            : 'none'
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'preferred_category_id',
                                            value === 'none'
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger id="preferred_category_id">
                                        <SelectValue placeholder="No preference" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            No preference
                                        </SelectItem>
                                        {options.categories.map((option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={String(option.id)}
                                            >
                                                {option.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="preferred_category_id-error"
                                    message={errors.preferred_category_id}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="preferred_experience_level_id">
                                    Preferred experience level
                                </Label>
                                <Select
                                    value={
                                        form.data.preferred_experience_level_id
                                            ? String(
                                                  form.data
                                                      .preferred_experience_level_id,
                                              )
                                            : 'none'
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'preferred_experience_level_id',
                                            value === 'none'
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger id="preferred_experience_level_id">
                                        <SelectValue placeholder="No preference" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            No preference
                                        </SelectItem>
                                        {options.experienceLevels.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={String(option.id)}
                                                >
                                                    {option.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="preferred_experience_level_id-error"
                                    message={
                                        errors.preferred_experience_level_id
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="preferred_workplace_arrangement_id">
                                    Preferred work arrangement
                                </Label>
                                <Select
                                    value={
                                        form.data
                                            .preferred_workplace_arrangement_id
                                            ? String(
                                                  form.data
                                                      .preferred_workplace_arrangement_id,
                                              )
                                            : 'none'
                                    }
                                    onValueChange={(value) =>
                                        form.setData(
                                            'preferred_workplace_arrangement_id',
                                            value === 'none'
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                    disabled={form.processing}
                                >
                                    <SelectTrigger id="preferred_workplace_arrangement_id">
                                        <SelectValue placeholder="No preference" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            No preference
                                        </SelectItem>
                                        {options.workplaceArrangements.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.id}
                                                    value={String(option.id)}
                                                >
                                                    {option.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="preferred_workplace_arrangement_id-error"
                                    message={
                                        errors.preferred_workplace_arrangement_id
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-lg">Education</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addEducation}
                                disabled={form.processing}
                            >
                                <Plus className="size-4" />
                                Add education
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {form.data.educations.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No education added yet.
                                </p>
                            )}
                            {form.data.educations.map((education, index) => (
                                <div
                                    key={index}
                                    className="space-y-3 rounded-lg border border-border p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-sm font-medium">
                                            Education {index + 1}
                                        </h3>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                removeEducation(index)
                                            }
                                            disabled={form.processing}
                                            aria-label={`Remove education ${index + 1}`}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`education-${index}-institution`}
                                        >
                                            Institution
                                        </Label>
                                        <Input
                                            id={`education-${index}-institution`}
                                            value={education.institution}
                                            onChange={(e) =>
                                                updateEducation(
                                                    index,
                                                    'institution',
                                                    e.target.value,
                                                )
                                            }
                                            maxLength={150}
                                            disabled={form.processing}
                                            aria-invalid={
                                                errors[
                                                    `educations.${index}.institution`
                                                ]
                                                    ? true
                                                    : undefined
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `educations.${index}.institution`
                                                ]
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`education-${index}-degree`}
                                            >
                                                Degree
                                            </Label>
                                            <Input
                                                id={`education-${index}-degree`}
                                                value={education.degree ?? ''}
                                                onChange={(e) =>
                                                    updateEducation(
                                                        index,
                                                        'degree',
                                                        e.target.value === ''
                                                            ? null
                                                            : e.target.value,
                                                    )
                                                }
                                                maxLength={100}
                                                disabled={form.processing}
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `educations.${index}.degree`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`education-${index}-field`}
                                            >
                                                Field of study
                                            </Label>
                                            <Input
                                                id={`education-${index}-field`}
                                                value={
                                                    education.field_of_study ??
                                                    ''
                                                }
                                                onChange={(e) =>
                                                    updateEducation(
                                                        index,
                                                        'field_of_study',
                                                        e.target.value === ''
                                                            ? null
                                                            : e.target.value,
                                                    )
                                                }
                                                maxLength={100}
                                                disabled={form.processing}
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `educations.${index}.field_of_study`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`education-${index}-start`}
                                            >
                                                Start date
                                            </Label>
                                            <Input
                                                id={`education-${index}-start`}
                                                type="date"
                                                value={
                                                    education.start_date ?? ''
                                                }
                                                onChange={(e) =>
                                                    updateEducation(
                                                        index,
                                                        'start_date',
                                                        e.target.value === ''
                                                            ? null
                                                            : e.target.value,
                                                    )
                                                }
                                                disabled={form.processing}
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `educations.${index}.start_date`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`education-${index}-end`}
                                            >
                                                End date
                                            </Label>
                                            <Input
                                                id={`education-${index}-end`}
                                                type="date"
                                                value={education.end_date ?? ''}
                                                onChange={(e) =>
                                                    updateEducation(
                                                        index,
                                                        'end_date',
                                                        e.target.value === ''
                                                            ? null
                                                            : e.target.value,
                                                    )
                                                }
                                                disabled={form.processing}
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `educations.${index}.end_date`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`education-${index}-description`}
                                        >
                                            Description
                                        </Label>
                                        <Textarea
                                            id={`education-${index}-description`}
                                            value={education.description ?? ''}
                                            onChange={(e) =>
                                                updateEducation(
                                                    index,
                                                    'description',
                                                    e.target.value === ''
                                                        ? null
                                                        : e.target.value,
                                                )
                                            }
                                            maxLength={2000}
                                            rows={3}
                                            disabled={form.processing}
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `educations.${index}.description`
                                                ]
                                            }
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="border-border">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-lg">
                                Work experience
                            </CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addExperience}
                                disabled={form.processing}
                            >
                                <Plus className="size-4" />
                                Add experience
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {form.data.experiences.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No work experience added yet.
                                </p>
                            )}
                            {form.data.experiences.map((experience, index) => (
                                <div
                                    key={index}
                                    className="space-y-3 rounded-lg border border-border p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-sm font-medium">
                                            Experience {index + 1}
                                        </h3>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                removeExperience(index)
                                            }
                                            disabled={form.processing}
                                            aria-label={`Remove experience ${index + 1}`}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    </div>

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`experience-${index}-company`}
                                            >
                                                Company name
                                            </Label>
                                            <Input
                                                id={`experience-${index}-company`}
                                                value={experience.company_name}
                                                onChange={(e) =>
                                                    updateExperience(
                                                        index,
                                                        'company_name',
                                                        e.target.value,
                                                    )
                                                }
                                                maxLength={150}
                                                disabled={form.processing}
                                                aria-invalid={
                                                    errors[
                                                        `experiences.${index}.company_name`
                                                    ]
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `experiences.${index}.company_name`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`experience-${index}-title`}
                                            >
                                                Job title
                                            </Label>
                                            <Input
                                                id={`experience-${index}-title`}
                                                value={experience.job_title}
                                                onChange={(e) =>
                                                    updateExperience(
                                                        index,
                                                        'job_title',
                                                        e.target.value,
                                                    )
                                                }
                                                maxLength={150}
                                                disabled={form.processing}
                                                aria-invalid={
                                                    errors[
                                                        `experiences.${index}.job_title`
                                                    ]
                                                        ? true
                                                        : undefined
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `experiences.${index}.job_title`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`experience-${index}-location`}
                                        >
                                            Location
                                        </Label>
                                        <Input
                                            id={`experience-${index}-location`}
                                            value={experience.location ?? ''}
                                            onChange={(e) =>
                                                updateExperience(
                                                    index,
                                                    'location',
                                                    e.target.value === ''
                                                        ? null
                                                        : e.target.value,
                                                )
                                            }
                                            maxLength={100}
                                            disabled={form.processing}
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `experiences.${index}.location`
                                                ]
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`experience-${index}-start`}
                                            >
                                                Start date
                                            </Label>
                                            <Input
                                                id={`experience-${index}-start`}
                                                type="date"
                                                value={
                                                    experience.start_date ?? ''
                                                }
                                                onChange={(e) =>
                                                    updateExperience(
                                                        index,
                                                        'start_date',
                                                        e.target.value === ''
                                                            ? null
                                                            : e.target.value,
                                                    )
                                                }
                                                disabled={form.processing}
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `experiences.${index}.start_date`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`experience-${index}-end`}
                                            >
                                                End date
                                            </Label>
                                            <Input
                                                id={`experience-${index}-end`}
                                                type="date"
                                                value={
                                                    experience.end_date ?? ''
                                                }
                                                onChange={(e) =>
                                                    updateExperience(
                                                        index,
                                                        'end_date',
                                                        e.target.value === ''
                                                            ? null
                                                            : e.target.value,
                                                    )
                                                }
                                                disabled={
                                                    form.processing ||
                                                    experience.is_current
                                                }
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `experiences.${index}.end_date`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id={`experience-${index}-current`}
                                            checked={experience.is_current}
                                            onCheckedChange={(checked) =>
                                                updateExperience(
                                                    index,
                                                    'is_current',
                                                    Boolean(checked),
                                                )
                                            }
                                            disabled={form.processing}
                                        />
                                        <Label
                                            htmlFor={`experience-${index}-current`}
                                            className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                        >
                                            I currently work here
                                        </Label>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor={`experience-${index}-description`}
                                        >
                                            Description
                                        </Label>
                                        <Textarea
                                            id={`experience-${index}-description`}
                                            value={experience.description ?? ''}
                                            onChange={(e) =>
                                                updateExperience(
                                                    index,
                                                    'description',
                                                    e.target.value === ''
                                                        ? null
                                                        : e.target.value,
                                                )
                                            }
                                            maxLength={2000}
                                            rows={3}
                                            disabled={form.processing}
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `experiences.${index}.description`
                                                ]
                                            }
                                        />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner className="mr-2" />}
                            Save profile
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CandidateProfileEdit.layout = {
    breadcrumbs: [
        {
            title: 'Candidate Profile',
            href: candidate.profile.edit(),
        },
    ],
};
