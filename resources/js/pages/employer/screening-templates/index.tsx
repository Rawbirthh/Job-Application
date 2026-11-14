import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TemplateForm } from '@/components/screening/template-form';
import { cn } from '@/lib/utils';
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

    const editingName = editingId
        ? (templates.data.find((t) => t.id === editingId)?.name ?? 'template')
        : null;

    const renderTemplatesPanel = (className?: string, compact = false) => (
        <Card className={cn('border-border', className)}>
            <CardHeader className="flex flex-row items-center justify-between gap-2 pb-3">
                <CardTitle className="text-base">Your templates</CardTitle>
                <span className="text-xs text-muted-foreground">
                    {templates.total}{' '}
                    {templates.total === 1 ? 'template' : 'templates'}
                </span>
            </CardHeader>
            <CardContent className="space-y-3">
                {templates.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No templates yet.
                    </p>
                ) : (
                    templates.data.map((t) => (
                        <div
                            key={t.id}
                            className="flex flex-col gap-3 rounded-lg border border-border p-4 xl:flex-row xl:items-center xl:justify-between"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {t.name}
                                    </span>
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            'text-xs',
                                            t.is_active
                                                ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                : 'border-border bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {t.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>
                                {t.description && (
                                    <p className="line-clamp-2 text-xs text-muted-foreground">
                                        {t.description}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    {t.questions_count ?? 0} questions
                                </p>
                            </div>
                            <div className="flex shrink-0 flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleToggleActive(t)}
                                >
                                    {t.is_active ? 'Deactivate' : 'Activate'}
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
                    ))
                )}

                {compact
                    ? templates.total > 0 && (
                          <div className="flex items-center justify-between gap-2 border-t border-border pt-3">
                              <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-7 px-2 text-xs"
                                  asChild={templates.prev_page_url !== null}
                                  disabled={templates.prev_page_url === null}
                              >
                                  {templates.prev_page_url ? (
                                      <Link
                                          href={templates.prev_page_url}
                                          rel="prev"
                                      >
                                          <ChevronLeft className="size-3.5" />
                                          Newer
                                      </Link>
                                  ) : (
                                      <>
                                          <ChevronLeft className="size-3.5" />
                                          Newer
                                      </>
                                  )}
                              </Button>
                              <p className="text-xs text-muted-foreground">
                                  Page{' '}
                                  <span className="font-medium text-foreground">
                                      {templates.current_page}
                                  </span>{' '}
                                  of {templates.last_page}
                              </p>
                              <Button
                                  variant="ghost"
                                  size="sm"
                                  className="h-7 px-2 text-xs"
                                  asChild={templates.next_page_url !== null}
                                  disabled={templates.next_page_url === null}
                              >
                                  {templates.next_page_url ? (
                                      <Link
                                          href={templates.next_page_url}
                                          rel="next"
                                      >
                                          Older
                                          <ChevronRight className="size-3.5" />
                                      </Link>
                                  ) : (
                                      <>
                                          Older
                                          <ChevronRight className="size-3.5" />
                                      </>
                                  )}
                              </Button>
                          </div>
                      )
                    : templates.total > 0 && (
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
    );

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
                    <Button onClick={startCreate} size="sm">
                        <Plus className="size-4" />
                        New template
                    </Button>
                </div>

                {showForm && (
                    <div className="grid items-start gap-6 lg:grid-cols-5">
                        {/* Left: builder form */}
                        <Card className="border-border lg:col-span-3">
                            <CardHeader className="flex flex-row items-center justify-between gap-2">
                                <CardTitle className="text-base">
                                    {editingId
                                        ? 'Edit template'
                                        : 'New template'}
                                </CardTitle>
                                {editingId ? (
                                    <Badge
                                        variant="outline"
                                        className="gap-1 border-amber-500/40 bg-amber-500/10 font-normal text-amber-700 dark:text-amber-400"
                                    >
                                        <Pencil className="size-3" />
                                        Editing “{editingName}”
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="border-sky-500/40 bg-sky-500/10 font-normal text-sky-700 dark:text-sky-400"
                                    >
                                        New
                                    </Badge>
                                )}
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

                        {/* Right: your templates */}
                        {renderTemplatesPanel(
                            'lg:sticky lg:top-6 lg:col-span-2',
                            true,
                        )}
                    </div>
                )}

                {!showForm && renderTemplatesPanel()}
            </div>
        </>
    );
}
