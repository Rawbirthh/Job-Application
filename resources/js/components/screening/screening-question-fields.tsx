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
import InputError from '@/components/input-error';
import type { ScreeningQuestionForApply } from '@/types';

type AnswerValue = {
    answer_text?: string | null;
    answer_boolean?: boolean | null;
    selected_option?: string | null;
};

type Props = {
    question: ScreeningQuestionForApply;
    value: AnswerValue;
    onChange: (patch: AnswerValue) => void;
    error?: string;
    disabled?: boolean;
};

export function ScreeningQuestionFields({
    question,
    value,
    onChange,
    error,
    disabled,
}: Props) {
    const label = question.label;
    const required = question.is_required;

    return (
        <div className="space-y-2">
            <Label htmlFor={`sq-${question.id}`}>
                {label}{' '}
                {required && <span className="text-destructive">*</span>}
            </Label>

            {question.type === 'short_text' && (
                <Input
                    id={`sq-${question.id}`}
                    value={value.answer_text ?? ''}
                    onChange={(e) => onChange({ answer_text: e.target.value })}
                    maxLength={255}
                    disabled={disabled}
                />
            )}

            {question.type === 'long_text' && (
                <Textarea
                    id={`sq-${question.id}`}
                    value={value.answer_text ?? ''}
                    onChange={(e) => onChange({ answer_text: e.target.value })}
                    rows={4}
                    maxLength={5000}
                    disabled={disabled}
                />
            )}

            {question.type === 'yes_no' && (
                <Select
                    value={
                        value.answer_boolean === null
                            ? ''
                            : value.answer_boolean
                              ? 'true'
                              : 'false'
                    }
                    onValueChange={(v) =>
                        onChange({
                            answer_boolean: v === '' ? null : v === 'true',
                        })
                    }
                    disabled={disabled}
                >
                    <SelectTrigger id={`sq-${question.id}`}>
                        <SelectValue placeholder="Select yes or no" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="true">Yes</SelectItem>
                        <SelectItem value="false">No</SelectItem>
                    </SelectContent>
                </Select>
            )}

            {question.type === 'single_choice' && (
                <Select
                    value={value.selected_option ?? ''}
                    onValueChange={(v) => onChange({ selected_option: v })}
                    disabled={disabled}
                >
                    <SelectTrigger id={`sq-${question.id}`}>
                        <SelectValue placeholder="Select an option" />
                    </SelectTrigger>
                    <SelectContent>
                        {(question.options ?? []).map((opt) => (
                            <SelectItem key={opt} value={opt}>
                                {opt}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}

            <InputError message={error} />
        </div>
    );
}
