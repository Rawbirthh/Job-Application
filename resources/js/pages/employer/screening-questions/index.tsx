import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScreeningQuestionForm } from '@/components/screening/screening-question-form';
import { ScreeningQuestionList } from '@/components/screening/screening-question-list';
import type { ScreeningQuestionsIndexProps } from '@/types';
import {
    store as bankStore,
    update as bankUpdate,
    destroy as bankDestroy,
} from '@/routes/employer/screening-questions';

const emptyForm = {
    type: 'short_text' as const,
    label: '',
    options: [] as string[],
    is_required: false,
    is_active: true,
};

export default function EmployerScreeningQuestionsIndex({
    questions,
}: ScreeningQuestionsIndexProps) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [showForm, setShowForm] = useState(false);

    const form = useForm(emptyForm);

    const resetForm = () => {
        form.setData(emptyForm);
        form.clearErrors();
        setEditingId(null);
        setShowForm(false);
    };

    const startCreate = () => {
        form.setData(emptyForm);
        form.clearErrors();
        setEditingId(null);
        setShowForm(true);
    };

    const startEdit = (q: {
        id: number;
        type: string;
        label: string;
        options: string[] | null;
        is_required: boolean;
        is_active: boolean;
    }) => {
        form.setData({
            type: q.type as typeof emptyForm.type,
            label: q.label,
            options: q.options ?? [],
            is_required: q.is_required,
            is_active: q.is_active,
        });
        form.clearErrors();
        setEditingId(q.id);
        setShowForm(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            form.put(bankUpdate.url(editingId), {
                preserveScroll: true,
                onSuccess: () => resetForm(),
            });
        } else {
            form.post(bankStore.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.setData(emptyForm);
                    form.clearErrors();
                    setShowForm(false);
                },
            });
        }
    };

    const handleDelete = (q: { id: number; label: string }) => {
        if (confirm(`Delete question "${q.label}"?`)) {
            router.delete(bankDestroy.url(q.id), { preserveScroll: true });
        }
    };

    const handleToggleActive = (q: {
        id: number;
        is_active: boolean;
        type: string;
        label: string;
        options: string[] | null;
        is_required: boolean;
    }) => {
        router.put(
            bankUpdate.url(q.id),
            {
                type: q.type,
                label: q.label,
                options: q.options ?? [],
                is_required: q.is_required,
                is_active: !q.is_active,
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Screening Question Bank" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Screening Question Bank
                        </h1>
                        <p className="text-sm text-muted-foreground md:text-base">
                            Manage reusable screening questions for your jobs.
                        </p>
                    </div>
                    <Button onClick={startCreate}>
                        <Plus className="size-4" />
                        New question
                    </Button>
                </div>

                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <div className="space-y-4 lg:sticky lg:top-6">
                        {showForm ? (
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
                                                    keyof typeof form.data,
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
                                                : 'Create question'
                                        }
                                    />
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="border-dashed border-border">
                                <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center">
                                    <p className="text-sm text-muted-foreground">
                                        Select "New question" to create a
                                        reusable screening question.
                                    </p>
                                    <Button onClick={startCreate}>
                                        <Plus className="size-4" />
                                        New question
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Your questions
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ScreeningQuestionList
                                questions={questions.data}
                                onEdit={(q) =>
                                    startEdit(
                                        q as Parameters<typeof startEdit>[0],
                                    )
                                }
                                onToggleActive={(q) =>
                                    handleToggleActive(
                                        q as Parameters<
                                            typeof handleToggleActive
                                        >[0],
                                    )
                                }
                                onDelete={(q) =>
                                    handleDelete(
                                        q as { id: number; label: string },
                                    )
                                }
                                getLockedMessage={(q) => {
                                    const item = q as {
                                        templates_count?: number;
                                        jobs_count?: number;
                                    };
                                    if (
                                        (item.templates_count ?? 0) > 0 ||
                                        (item.jobs_count ?? 0) > 0
                                    ) {
                                        return 'This question is in use; deactivate instead of deleting.';
                                    }
                                    return null;
                                }}
                            />

                            {questions.total > 0 && (
                                <Pagination
                                    currentPage={questions.current_page}
                                    lastPage={questions.last_page}
                                    perPage={questions.per_page}
                                    total={questions.total}
                                    prevPageUrl={questions.prev_page_url}
                                    nextPageUrl={questions.next_page_url}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
