import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import EmployerEvaluationController from '@/actions/App/Http/Controllers/Employer/EmployerEvaluationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { ClipboardCheck, ChevronDown } from 'lucide-react';
import type { ApplicationEvaluationItem } from '@/types';

type Props = {
    applicationId: number;
    evaluations: ApplicationEvaluationItem[];
    canManage: boolean;
};

const CRITERIA = [
    { key: 'relevant_experience', label: 'Relevant experience' },
    { key: 'technical_skills', label: 'Technical skills' },
    { key: 'communication', label: 'Communication' },
    { key: 'interview_performance', label: 'Interview performance' },
] as const;

const RATING_LABELS: Record<number, string> = {
    1: 'Poor',
    2: 'Fair',
    3: 'Good',
    4: 'Very good',
    5: 'Excellent',
};

function ratingLabel(value: number): string {
    return `${value} — ${RATING_LABELS[value] ?? value}`;
}

function formatDate(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function evaluatorName(
    evaluator: ApplicationEvaluationItem['evaluator'],
): string {
    return evaluator?.name ?? 'Former team member';
}

function RatingsRow({ evaluation }: { evaluation: ApplicationEvaluationItem }) {
    return (
        <dl className="grid grid-cols-1 gap-2 sm:grid-cols-2">
            {CRITERIA.map((criterion) => (
                <div
                    key={criterion.key}
                    className="flex items-center justify-between gap-2 rounded-md border border-border bg-background px-3 py-2"
                >
                    <dt className="text-xs text-muted-foreground">
                        {criterion.label}
                    </dt>
                    <dd className="text-xs font-semibold text-foreground">
                        {ratingLabel(evaluation.ratings[criterion.key])}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function EvaluationScorecard({
    applicationId,
    evaluations,
    canManage,
}: Props) {
    const latest = evaluations.length > 0 ? evaluations[0] : null;
    const history = evaluations.slice(1);

    const form = useForm({
        relevant_experience: '',
        technical_skills: '',
        communication: '',
        interview_performance: '',
        comment: '',
    });

    const submitEvaluation = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            relevant_experience:
                data.relevant_experience === ''
                    ? null
                    : Number(data.relevant_experience),
            technical_skills:
                data.technical_skills === ''
                    ? null
                    : Number(data.technical_skills),
            communication:
                data.communication === '' ? null : Number(data.communication),
            interview_performance:
                data.interview_performance === ''
                    ? null
                    : Number(data.interview_performance),
            comment: data.comment,
        }));

        form.post(
            EmployerEvaluationController.store.url({
                application: applicationId,
            }),
            {
                preserveScroll: true,
                onSuccess: () => form.reset(),
            },
        );
    };

    const renderCriterion = (
        key: (typeof CRITERIA)[number]['key'],
        label: string,
    ) => {
        const selectId = `evaluation-${key}`;
        return (
            <div className="space-y-1.5">
                <Label htmlFor={selectId}>{label}</Label>
                <select
                    id={selectId}
                    name={key}
                    value={form.data[key]}
                    onChange={(event) => form.setData(key, event.target.value)}
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option value="" disabled>
                        Select a rating…
                    </option>
                    {[1, 2, 3, 4, 5].map((value) => (
                        <option key={value} value={value}>
                            {ratingLabel(value)}
                        </option>
                    ))}
                </select>
                <InputError message={form.errors[key]} />
            </div>
        );
    };

    return (
        <Card className="border-border">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <ClipboardCheck className="size-5 text-teal-600 dark:text-teal-400" />
                    Evaluation
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="text-xs text-muted-foreground">
                    Recruiter-recorded ratings — not an automated or objective
                    assessment. Visible only to your company team; never shown
                    to the candidate.
                </p>

                {latest ? (
                    <div className="space-y-3 rounded-lg border border-border bg-muted/30 p-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-xs font-semibold text-foreground">
                                Latest evaluation
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {evaluatorName(latest.evaluator)} ·{' '}
                                {formatDate(latest.created_at)}
                            </span>
                        </div>
                        <RatingsRow evaluation={latest} />
                        {latest.comment && (
                            <p className="rounded-md border border-border bg-background p-2 text-sm leading-relaxed whitespace-pre-wrap text-foreground/90">
                                {latest.comment}
                            </p>
                        )}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground italic">
                        No evaluation recorded yet.
                    </p>
                )}

                {history.length > 0 && (
                    <Collapsible>
                        <CollapsibleTrigger className="flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                            Previous evaluations ({history.length})
                            <ChevronDown className="size-3.5" />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="pt-2">
                            <ul className="space-y-3">
                                {history.map((evaluation) => (
                                    <li
                                        key={evaluation.id}
                                        className="space-y-2 rounded-md border border-border p-3"
                                    >
                                        <div className="text-xs text-muted-foreground">
                                            {evaluatorName(
                                                evaluation.evaluator,
                                            )}{' '}
                                            ·{' '}
                                            {formatDate(evaluation.created_at)}
                                        </div>
                                        <RatingsRow evaluation={evaluation} />
                                        {evaluation.comment && (
                                            <p className="text-sm whitespace-pre-wrap text-foreground/90">
                                                {evaluation.comment}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CollapsibleContent>
                    </Collapsible>
                )}

                {canManage && (
                    <form onSubmit={submitEvaluation} className="space-y-3">
                        <Separator />
                        <p className="text-sm font-medium text-foreground">
                            Submit an evaluation
                        </p>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {renderCriterion(
                                'relevant_experience',
                                'Relevant experience',
                            )}
                            {renderCriterion(
                                'technical_skills',
                                'Technical skills',
                            )}
                            {renderCriterion('communication', 'Communication')}
                            {renderCriterion(
                                'interview_performance',
                                'Interview performance',
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="evaluation-comment">
                                Supporting comment (optional)
                            </Label>
                            <Textarea
                                id="evaluation-comment"
                                value={form.data.comment}
                                onChange={(event) =>
                                    form.setData('comment', event.target.value)
                                }
                                maxLength={2000}
                                rows={3}
                                placeholder="Context for the ratings — e.g. concerns raised, strong signals, what to probe in the next round."
                            />
                            <InputError message={form.errors.comment} />
                        </div>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving...' : 'Save Evaluation'}
                        </Button>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
