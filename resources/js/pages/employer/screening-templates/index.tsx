import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TemplateForm } from '@/components/screening/template-form';
import type { ScreeningTemplate, ScreeningTemplatesIndexProps } from '@/types';
import {
    store as templatesStore,
    update as templatesUpdate,
    destroy as templatesDestroy,
} from '@/routes/employer/screening-templates';

const emptyForm = {
    name: '',
    description: '',
    question_ids: [] as number[],
};

export default function EmployerScreeningTemplatesIndex({
    templates,
    bank_questions,
}: ScreeningTemplatesIndexProps) {
    const bankQuestions = bank_questions ?? [];

    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

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

    const startEdit = (t: ScreeningTemplate) => {
        form.clearErrors();
        setEditingId(t.id);
        setShowForm(true);
        form.setData({
            name: t.name,
            description: t.description ?? '',
            question_ids: t.question_ids ?? [],
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            form.put(templatesUpdate.url(editingId), {
                preserveScroll: true,
                onSuccess: () => resetForm(),
            });
        } else {
            form.post(templatesStore.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    form.setData(emptyForm);
                    setShowForm(false);
                },
            });
        }
    };

    const handleDelete = (t: ScreeningTemplate) => {
        if (confirm(`Delete template "${t.name}"?`)) {
            router.delete(templatesDestroy.url(t.id), { preserveScroll: true });
        }
    };

    const handleToggleActive = (t: ScreeningTemplate) => {
        router.put(
            templatesUpdate.url(t.id),
            {
                name: t.name,
                description: t.description,
                question_ids: t.question_ids ?? [],
                is_active: !t.is_active,
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Screening Templates" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                            Screening Templates
                        </h1>
                        <p className="text-sm text-muted-foreground md:text-base">
                            Group bank questions into reusable templates.
                        </p>
                    </div>
                    <Button onClick={startCreate}>
                        <Plus className="size-4" />
                        New template
                    </Button>
                </div>

                {showForm && (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                {editingId
                                    ? 'Edit template'
                                    : 'Create template'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <TemplateForm
                                data={form.data}
                                errors={
                                    form.errors as Partial<
                                        Record<
                                            | 'name'
                                            | 'description'
                                            | 'question_ids',
                                            string
                                        >
                                    >
                                }
                                processing={form.processing}
                                bankQuestions={bankQuestions}
                                onFieldChange={(field, value) =>
                                    form.setData({
                                        ...form.data,
                                        [field]: value,
                                    } as Partial<typeof form.data>)
                                }
                                onSubmit={handleSubmit}
                                onCancel={resetForm}
                                submitLabel={
                                    editingId
                                        ? 'Update template'
                                        : 'Create template'
                                }
                            />
                        </CardContent>
                    </Card>
                )}

                <Card className="border-border">
                    <CardHeader>
                        <CardTitle className="text-lg">
                            Your templates
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {templates.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No templates yet.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {templates.data.map((t) => (
                                    <div
                                        key={t.id}
                                        className="flex flex-col gap-3 rounded-lg border border-border p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {t.name}
                                            </div>
                                            {t.description && (
                                                <p className="text-xs text-muted-foreground">
                                                    {t.description}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                {t.questions_count ?? 0}{' '}
                                                questions
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handleToggleActive(t)
                                                }
                                            >
                                                {t.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'}
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => startEdit(t)}
                                            >
                                                <Pencil className="size-4" />
                                                Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => handleDelete(t)}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {templates.total > 0 && (
                            <Pagination
                                currentPage={templates.current_page}
                                lastPage={templates.last_page}
                                perPage={templates.per_page}
                                total={templates.total}
                                prevPageUrl={templates.prev_page_url}
                                nextPageUrl={templates.next_page_url}
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
