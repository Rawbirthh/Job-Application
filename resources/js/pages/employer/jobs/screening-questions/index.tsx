import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ScreeningQuestionForm } from '@/components/screening/screening-question-form';
import { ScreeningQuestionList } from '@/components/screening/screening-question-list';
import { TemplateApplyDialog } from '@/components/screening/template-apply-dialog';
import type {
    BankScreeningQuestion,
    JobScreeningQuestion,
    JobScreeningQuestionsIndexProps,
    ScreeningQuestionFormData,
} from '@/types';
import {
    store as jobQuestionsStore,
    update as jobQuestionsUpdate,
    destroy as jobQuestionsDestroy,
    applyTemplate as jobQuestionsApplyTemplate,
    reorder as jobQuestionsReorder,
} from '@/routes/employer/jobs/screening-questions';

const emptyForm: ScreeningQuestionFormData = {
    type: 'short_text',
    label: '',
    options: [],
    is_required: false,
    is_active: true,
};

export default function EmployerJobScreeningQuestionsIndex({
    job,
    questions: initialQuestions,
    templates,
    bank_questions,
}: JobScreeningQuestionsIndexProps) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [showCreate, setShowCreate] = useState(false);
    const [showBankAdd, setShowBankAdd] = useState(false);

    const form = useForm<ScreeningQuestionFormData>(emptyForm);
    const bankForm = useForm<{ question_id: string }>({ question_id: '' });

    // keep questions in sync on Inertia reload? Use initial prop; after mutations page reloads, component remounts. Fine.

    const resetForm = () => {
        form.setData(emptyForm);
        form.clearErrors();
        setEditingId(null);
        setShowCreate(false);
    };

    const startEdit = (q: (typeof initialQuestions)[number]) => {
        form.setData({
            type: q.type,
            label: q.label,
            options: q.options ?? [],
            is_required: q.is_required,
            is_active: q.is_active,
        });
        form.clearErrors();
        setEditingId(q.id);
        setShowCreate(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            form.put(jobQuestionsUpdate.url(editingId), {
                preserveScroll: true,
                onSuccess: () => resetForm(),
            });
        } else {
            form.post(jobQuestionsStore.url(job), {
                preserveScroll: true,
                onSuccess: () => {
                    form.setData(emptyForm);
                    setShowCreate(false);
                },
            });
        }
    };

    // For inline create, need send full payload; useForm data includes all fields. Good.

    const handleBankAdd = (e: React.FormEvent) => {
        e.preventDefault();
        if (!bankForm.data.question_id) return;
        router.post(
            jobQuestionsStore.url(job),
            { question_id: Number(bankForm.data.question_id) },
            {
                preserveScroll: true,
                onSuccess: () => bankForm.setData('question_id', ''),
            },
        );
    };

    const handleDelete = (q: { id: number; label: string }) => {
        if (confirm(`Delete question "${q.label}"?`)) {
            router.delete(jobQuestionsDestroy.url(q.id), {
                preserveScroll: true,
            });
        }
    };

    const handleToggleActive = (q: {
        id: number;
        is_active: boolean;
        type: string;
        label: string;
        options: string[] | null;
        is_required: boolean;
        sort_order: number;
    }) => {
        router.put(
            jobQuestionsUpdate.url(q.id),
            {
                type: q.type,
                label: q.label,
                options: q.options ?? [],
                is_required: q.is_required,
                is_active: !q.is_active,
                sort_order: q.sort_order,
            },
            { preserveScroll: true },
        );
    };

    const handleApplyTemplate = (templateId: number) => {
        router.post(
            jobQuestionsApplyTemplate.url(job),
            { template_id: templateId },
            { preserveScroll: true },
        );
    };

    const moveQuestion = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= initialQuestions.length) return;
        const next = [...initialQuestions];
        const [item] = next.splice(index, 1);
        next.splice(target, 0, item);
        const ids = next.map((q) => q.id);
        router.post(
            jobQuestionsReorder.url(job),
            { question_ids: ids },
            { preserveScroll: true },
        );
    };

    // Use initialQuestions for rendering; after reorder mutation page reloads.
    const list = initialQuestions;

    const getLockedMessage = (
        q: BankScreeningQuestion | JobScreeningQuestion,
    ) => {
        if (
            'answers_count' in q &&
            ((q as JobScreeningQuestion).answers_count ?? 0) > 0
        ) {
            return 'This question has submitted answers; type, label, and options are locked.';
        }
        return null;
    };

    return (
        <>
            <Head title={`Screening Questions - ${job.title}`} />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Screening Questions
                        </h1>
                        <p className="text-sm text-muted-foreground md:text-base">
                            {job.title} ({job.status})
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <TemplateApplyDialog
                            templates={templates}
                            onApply={handleApplyTemplate}
                        />
                        <Button
                            variant="outline"
                            onClick={() => setShowBankAdd((v) => !v)}
                        >
                            Add from bank
                        </Button>
                        <Button onClick={() => setShowCreate(true)}>
                            <Plus className="size-4" />
                            New question
                        </Button>
                    </div>
                </div>

                {showBankAdd && (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Add from bank
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={handleBankAdd}
                                className="flex flex-col gap-3 sm:flex-row sm:items-end"
                            >
                                <div className="flex-1 space-y-2">
                                    <Label>Bank question</Label>
                                    <Select
                                        value={bankForm.data.question_id}
                                        onValueChange={(v) =>
                                            bankForm.setData('question_id', v)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a bank question" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {bank_questions.map((q) => (
                                                <SelectItem
                                                    key={q.id}
                                                    value={String(q.id)}
                                                >
                                                    {q.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={bankForm.errors.question_id}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={bankForm.processing}
                                >
                                    Add
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {showCreate && (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                {editingId
                                    ? 'Edit question'
                                    : 'Create question'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ScreeningQuestionForm
                                data={form.data}
                                errors={
                                    form.errors as Partial<
                                        Record<
                                            keyof ScreeningQuestionFormData,
                                            string
                                        >
                                    >
                                }
                                processing={form.processing}
                                onChange={(field, value) =>
                                    form.setData({
                                        ...form.data,
                                        [field]: value,
                                    } as Partial<typeof form.data>)
                                }
                                onSubmit={handleSubmit}
                                onCancel={resetForm}
                                submitLabel={
                                    editingId
                                        ? 'Update question'
                                        : 'Add question'
                                }
                            />
                        </CardContent>
                    </Card>
                )}

                <Card className="border-border">
                    <CardHeader>
                        <CardTitle className="text-lg">Job questions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <ScreeningQuestionList
                            questions={list}
                            onEdit={(q) =>
                                startEdit(
                                    q as (typeof initialQuestions)[number],
                                )
                            }
                            onToggleActive={(q) =>
                                handleToggleActive(
                                    q as (typeof initialQuestions)[number],
                                )
                            }
                            onDelete={(q) =>
                                handleDelete(q as { id: number; label: string })
                            }
                            onMoveUp={(q) => {
                                const idx = list.findIndex(
                                    (x) =>
                                        x.id ===
                                        (q as (typeof list)[number]).id,
                                );
                                if (idx > 0) moveQuestion(idx, -1);
                            }}
                            onMoveDown={(q) => {
                                const idx = list.findIndex(
                                    (x) =>
                                        x.id ===
                                        (q as (typeof list)[number]).id,
                                );
                                if (idx >= 0 && idx < list.length - 1)
                                    moveQuestion(idx, 1);
                            }}
                            getLockedMessage={getLockedMessage}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
