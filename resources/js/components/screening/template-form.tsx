import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import type { BankScreeningQuestion } from '@/types';

type TemplateFormData = {
    name: string;
    description: string;
    question_ids: number[];
};

type Props = {
    data: TemplateFormData;
    errors: Partial<Record<'name' | 'description' | 'question_ids', string>>;
    processing: boolean;
    bankQuestions: BankScreeningQuestion[];
    onFieldChange: <K extends keyof TemplateFormData>(
        field: K,
        value: TemplateFormData[K],
    ) => void;
    onSubmit: (e: React.FormEvent) => void;
    onCancel?: () => void;
    submitLabel?: string;
};

export function TemplateForm({
    data,
    errors,
    processing,
    bankQuestions,
    onFieldChange,
    onSubmit,
    onCancel,
    submitLabel = 'Save template',
}: Props) {
    const toggleQuestion = (id: number) => {
        if (data.question_ids.includes(id)) {
            onFieldChange(
                'question_ids',
                data.question_ids.filter((q) => q !== id),
            );
        } else {
            onFieldChange('question_ids', [...data.question_ids, id]);
        }
    };

    const move = (index: number, direction: -1 | 1) => {
        const next = [...data.question_ids];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        const [item] = next.splice(index, 1);
        next.splice(target, 0, item);
        onFieldChange('question_ids', next);
    };

    const selectedQuestions = data.question_ids
        .map((id) => bankQuestions.find((q) => q.id === id))
        .filter((q): q is BankScreeningQuestion => Boolean(q));

    return (
        <form onSubmit={onSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="tpl-name">Name</Label>
                <Input
                    id="tpl-name"
                    value={data.name}
                    onChange={(e) => onFieldChange('name', e.target.value)}
                    disabled={processing}
                />
                <InputError message={errors.name} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="tpl-desc">Description</Label>
                <Textarea
                    id="tpl-desc"
                    value={data.description}
                    onChange={(e) =>
                        onFieldChange('description', e.target.value)
                    }
                    rows={3}
                    disabled={processing}
                />
                <InputError message={errors.description} />
            </div>

            <div className="space-y-2">
                <Label>Questions</Label>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <p className="text-xs text-muted-foreground">
                            Available bank questions
                        </p>
                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-md border border-border p-2">
                            {bankQuestions.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    No bank questions available.
                                </p>
                            )}
                            {bankQuestions.map((q) => (
                                <label
                                    key={q.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={data.question_ids.includes(
                                            q.id,
                                        )}
                                        onChange={() => toggleQuestion(q.id)}
                                        disabled={processing}
                                    />
                                    <span>{q.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <p className="text-xs text-muted-foreground">
                            Selected order
                        </p>
                        <div className="max-h-64 space-y-1 overflow-y-auto rounded-md border border-border p-2">
                            {selectedQuestions.length === 0 && (
                                <p className="text-xs text-muted-foreground">
                                    No questions selected.
                                </p>
                            )}
                            {selectedQuestions.map((q, index) => (
                                <div
                                    key={q.id}
                                    className="flex items-center justify-between gap-2 text-sm"
                                >
                                    <span className="truncate">
                                        {index + 1}. {q.label}
                                    </span>
                                    <div className="flex gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => move(index, -1)}
                                            disabled={processing || index === 0}
                                        >
                                            ↑
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => move(index, 1)}
                                            disabled={
                                                processing ||
                                                index ===
                                                    selectedQuestions.length - 1
                                            }
                                        >
                                            ↓
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
                <InputError message={errors.question_ids} />
            </div>

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving...' : submitLabel}
                </Button>
                {onCancel && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </form>
    );
}
