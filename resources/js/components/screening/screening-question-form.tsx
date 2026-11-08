import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import type { ScreeningQuestionFormData, ScreeningQuestionType } from '@/types';

const TYPE_OPTIONS: { value: ScreeningQuestionType; label: string }[] = [
    { value: 'short_text', label: 'Short text' },
    { value: 'long_text', label: 'Long text' },
    { value: 'yes_no', label: 'Yes / No' },
    { value: 'single_choice', label: 'Single choice' },
];

type Props = {
    data: ScreeningQuestionFormData;
    errors: Partial<Record<keyof ScreeningQuestionFormData, string>>;
    processing: boolean;
    onChange: <K extends keyof ScreeningQuestionFormData>(
        field: K,
        value: ScreeningQuestionFormData[K],
    ) => void;
    onSubmit: (e: React.FormEvent) => void;
    onCancel?: () => void;
    submitLabel?: string;
};

export function ScreeningQuestionForm({
    data,
    errors,
    processing,
    onChange,
    onSubmit,
    onCancel,
    submitLabel = 'Save question',
}: Props) {
    const [optionInput, setOptionInput] = useState('');
    const [optionError, setOptionError] = useState<string | null>(null);

    const options = data.options ?? [];

    const addOption = () => {
        const trimmed = optionInput.trim();
        if (!trimmed) {
            setOptionError('Option cannot be empty.');
            return;
        }
        if (options.includes(trimmed)) {
            setOptionError('This option already exists.');
            return;
        }
        if (options.length >= 10) {
            setOptionError('You can add at most 10 options.');
            return;
        }
        setOptionError(null);
        onChange('options', [...options, trimmed]);
        setOptionInput('');
    };

    const removeOption = (index: number) => {
        onChange(
            'options',
            options.filter((_, i) => i !== index),
        );
    };

    return (
        <form onSubmit={onSubmit} className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor="sq-type">Type</Label>
                <Select
                    value={data.type}
                    onValueChange={(value) =>
                        onChange('type', value as ScreeningQuestionType)
                    }
                    disabled={processing}
                >
                    <SelectTrigger id="sq-type">
                        <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent>
                        {TYPE_OPTIONS.map((opt) => (
                            <SelectItem key={opt.value} value={opt.value}>
                                {opt.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.type} />
            </div>

            <div className="space-y-2">
                <Label htmlFor="sq-label">Question</Label>
                <Textarea
                    id="sq-label"
                    value={data.label}
                    onChange={(e) => onChange('label', e.target.value)}
                    placeholder="Enter the question prompt"
                    rows={3}
                    disabled={processing}
                />
                <InputError message={errors.label} />
            </div>

            {data.type === 'single_choice' && (
                <div className="space-y-2">
                    <Label>Options</Label>
                    <div className="flex gap-2">
                        <Input
                            value={optionInput}
                            onChange={(e) => {
                                setOptionInput(e.target.value);
                                if (optionError) setOptionError(null);
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    addOption();
                                }
                            }}
                            placeholder="Add an option"
                            disabled={processing}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addOption}
                            disabled={processing}
                        >
                            Add
                        </Button>
                    </div>
                    {optionError && (
                        <p className="text-sm font-medium text-destructive">
                            {optionError}
                        </p>
                    )}
                    {options.length > 0 && (
                        <ul className="space-y-1">
                            {options.map((opt, idx) => (
                                <li
                                    key={`${opt}-${idx}`}
                                    className="flex items-center justify-between rounded-md border border-border px-3 py-1.5 text-sm"
                                >
                                    <span>{opt}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeOption(idx)}
                                        disabled={processing}
                                    >
                                        Remove
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <InputError message={errors.options} />
                </div>
            )}

            <div className="flex items-center gap-2">
                <Checkbox
                    id="sq-required"
                    checked={data.is_required}
                    onCheckedChange={(checked) =>
                        onChange('is_required', Boolean(checked))
                    }
                    disabled={processing}
                />
                <Label htmlFor="sq-required">Required</Label>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="sq-active"
                    checked={data.is_active}
                    onCheckedChange={(checked) =>
                        onChange('is_active', Boolean(checked))
                    }
                    disabled={processing}
                />
                <Label htmlFor="sq-active">Active</Label>
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
