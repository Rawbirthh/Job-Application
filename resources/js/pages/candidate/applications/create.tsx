import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ScreeningQuestionFields } from '@/components/screening/screening-question-fields';
import type { ScreeningQuestionForApply } from '@/types';
import { Bookmark, Building, MapPin } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFlashToast } from '@/hooks/use-flash-toast';

interface ResumeData {
    id: number;
    original_filename: string;
    created_at?: string;
}

interface JobDetail {
    id: number;
    title: string;
    description: string;
    location: string | null;
    salary_min: string | null;
    salary_max: string | null;
    salary_currency: string | null;
    salary_negotiable: boolean;
    application_deadline: string | null;
    company: {
        id: number;
        name: string;
        logo_path: string | null;
    } | null;
    employment_type: { name: string } | null;
    workplace_arrangement: { name: string } | null;
    experience_level: { name: string } | null;
}

interface Props {
    job: JobDetail;
    resumes: ResumeData[];
    alreadyApplied: boolean;
    screening_questions?: ScreeningQuestionForApply[];
}

export default function JobApplicationCreate({
    job,
    resumes,
    alreadyApplied,
    screening_questions = [],
}: Props) {
    useFlashToast();

    const [selectedResumeId, setSelectedResumeId] = useState<string>(
        resumes.length > 0 ? String(resumes[0].id) : '',
    );
    const [coverLetter, setCoverLetter] = useState<string>('');
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [answers, setAnswers] = useState<
        Record<
            number,
            {
                answer_text?: string | null;
                answer_boolean?: boolean | null;
                selected_option?: string | null;
            }
        >
    >({});

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrors({});

        const answerPayload = screening_questions.map((q) => {
            const val = answers[q.id] ?? {};
            return {
                question_id: q.id,
                answer_text: val.answer_text ?? null,
                answer_boolean: val.answer_boolean ?? null,
                selected_option: val.selected_option ?? null,
            };
        });

        router.post(
            `/candidate/jobs/${job.id}/apply`,
            {
                candidate_resume_id: selectedResumeId
                    ? Number(selectedResumeId)
                    : null,
                cover_letter: coverLetter,
                answers: answerPayload,
            },
            {
                onError: (errs) => {
                    setErrors(errs);
                    setIsSubmitting(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    return (
        <div className="container mx-auto max-w-4xl px-4 py-8">
            <Head title={`Apply for ${job.title}`} />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Apply for Position
                    </h1>
                    <p className="mt-1 text-muted-foreground">
                        {job.title} at {job.company?.name || 'Company'}
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={`/jobs/${job.id}`}>Back to Job</Link>
                </Button>
            </div>

            {alreadyApplied ? (
                <Card className="py-12 text-center">
                    <CardContent className="flex flex-col items-center justify-center space-y-4">
                        <div className="rounded-full bg-muted p-4">
                            <Bookmark className="h-8 w-8 text-primary" />
                        </div>
                        <h3 className="text-lg font-semibold">
                            You have already applied
                        </h3>
                        <p className="max-w-sm text-sm text-muted-foreground">
                            You have already submitted an application for this
                            position.
                        </p>
                        <Button asChild className="mt-2">
                            <Link href="/jobs">Browse Other Jobs</Link>
                        </Button>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <div className="space-y-4 md:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Job Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div>
                                    <span className="font-semibold">
                                        {job.title}
                                    </span>
                                    {job.company?.name && (
                                        <div className="mt-1 flex items-center gap-1.5 text-muted-foreground">
                                            <Building className="h-4 w-4" />
                                            {job.company.name}
                                        </div>
                                    )}
                                </div>
                                {job.location && (
                                    <div className="flex items-center gap-1.5 text-muted-foreground">
                                        <MapPin className="h-4 w-4" />
                                        {job.location}
                                    </div>
                                )}
                                <div className="flex flex-wrap gap-2 pt-2">
                                    {job.employment_type && (
                                        <Badge variant="outline">
                                            {job.employment_type.name}
                                        </Badge>
                                    )}
                                    {job.workplace_arrangement && (
                                        <Badge variant="outline">
                                            {job.workplace_arrangement.name}
                                        </Badge>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Application Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={handleSubmit}
                                    className="space-y-6"
                                >
                                    <div className="space-y-2">
                                        <label className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
                                            Select Resume{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </label>
                                        {resumes.length === 0 ? (
                                            <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                                                You have not uploaded any
                                                resumes yet.{' '}
                                                <Link
                                                    href="/candidate/profile/resumes"
                                                    className="font-medium underline"
                                                >
                                                    Upload a resume first
                                                </Link>
                                            </div>
                                        ) : (
                                            <select
                                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                value={selectedResumeId}
                                                onChange={(e) =>
                                                    setSelectedResumeId(
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                            >
                                                <option value="" disabled>
                                                    Select a resume...
                                                </option>
                                                {resumes.map((resume) => (
                                                    <option
                                                        key={resume.id}
                                                        value={resume.id}
                                                    >
                                                        {
                                                            resume.original_filename
                                                        }
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                        {errors.candidate_resume_id && (
                                            <p className="text-sm font-medium text-destructive">
                                                {errors.candidate_resume_id}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm leading-none font-medium">
                                            Cover Letter{' '}
                                            <span className="text-xs text-muted-foreground">
                                                (Optional)
                                            </span>
                                        </label>
                                        <textarea
                                            className="flex min-h-[150px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                            placeholder="Write a brief cover letter or note to the hiring manager..."
                                            value={coverLetter}
                                            onChange={(e) =>
                                                setCoverLetter(e.target.value)
                                            }
                                            maxLength={5000}
                                        />
                                        <div className="flex justify-between text-xs text-muted-foreground">
                                            <span>
                                                Maximum 5,000 characters
                                            </span>
                                            <span>
                                                {coverLetter.length}/5000
                                            </span>
                                        </div>
                                        {errors.cover_letter && (
                                            <p className="text-sm font-medium text-destructive">
                                                {errors.cover_letter}
                                            </p>
                                        )}
                                    </div>

                                    {screening_questions.length > 0 && (
                                        <div className="space-y-4">
                                            <h3 className="text-base font-semibold">
                                                Screening Questions
                                            </h3>
                                            {screening_questions.map((q) => {
                                                return (
                                                    <ScreeningQuestionFields
                                                        key={q.id}
                                                        question={q}
                                                        value={
                                                            answers[q.id] ?? {}
                                                        }
                                                        onChange={(patch) =>
                                                            setAnswers(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    [q.id]: {
                                                                        ...prev[
                                                                            q.id
                                                                        ],
                                                                        ...patch,
                                                                    },
                                                                }),
                                                            )
                                                        }
                                                        error={
                                                            errors[
                                                                `answers.${screening_questions.indexOf(q)}.answer_text`
                                                            ] ||
                                                            errors[
                                                                `answers.${screening_questions.indexOf(q)}.answer_boolean`
                                                            ] ||
                                                            errors[
                                                                `answers.${screening_questions.indexOf(q)}.selected_option`
                                                            ] ||
                                                            errors.answers
                                                        }
                                                        disabled={isSubmitting}
                                                    />
                                                );
                                            })}
                                        </div>
                                    )}

                                    {errors.answers && (
                                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                                            {errors.answers}
                                        </div>
                                    )}

                                    {errors.job_posting_id && (
                                        <div className="rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
                                            {errors.job_posting_id}
                                        </div>
                                    )}

                                    <div className="flex justify-end gap-4">
                                        <Button variant="outline" asChild>
                                            <Link href={`/jobs/${job.id}`}>
                                                Cancel
                                            </Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={
                                                isSubmitting ||
                                                resumes.length === 0
                                            }
                                        >
                                            {isSubmitting
                                                ? 'Submitting Application...'
                                                : 'Submit Application'}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            )}
        </div>
    );
}
