import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { BankScreeningQuestion, JobScreeningQuestion } from '@/types';

type AnyQuestion = BankScreeningQuestion | JobScreeningQuestion;

type Props = {
    questions: AnyQuestion[];
    onEdit?: (question: AnyQuestion) => void;
    onToggleActive?: (question: AnyQuestion) => void;
    onDelete?: (question: AnyQuestion) => void;
    onMoveUp?: (question: AnyQuestion) => void;
    onMoveDown?: (question: AnyQuestion) => void;
    getLockedMessage?: (question: AnyQuestion) => string | null;
};

export function ScreeningQuestionList({
    questions,
    onEdit,
    onToggleActive,
    onDelete,
    onMoveUp,
    onMoveDown,
    getLockedMessage,
}: Props) {
    if (questions.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                No questions yet.
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {questions.map((question, index) => {
                const lockedMessage = getLockedMessage?.(question) ?? null;
                return (
                    <div
                        key={question.id}
                        className="rounded-lg border border-border bg-card p-4"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium text-foreground">
                                        {question.label}
                                    </span>
                                    <Badge variant="outline">
                                        {question.type_label}
                                    </Badge>
                                    {question.is_required && (
                                        <Badge variant="secondary">
                                            Required
                                        </Badge>
                                    )}
                                    {!question.is_active && (
                                        <Badge
                                            variant="outline"
                                            className="text-muted-foreground"
                                        >
                                            Inactive
                                        </Badge>
                                    )}
                                    {'answers_count' in question &&
                                    (question as JobScreeningQuestion)
                                        .answers_count ? (
                                        <Badge variant="outline">
                                            {
                                                (
                                                    question as JobScreeningQuestion
                                                ).answers_count
                                            }{' '}
                                            answers
                                        </Badge>
                                    ) : null}
                                </div>
                                {question.type === 'single_choice' &&
                                    question.options && (
                                        <p className="text-xs text-muted-foreground">
                                            Options:{' '}
                                            {question.options.join(', ')}
                                        </p>
                                    )}
                                {lockedMessage && (
                                    <p className="text-xs text-amber-600 dark:text-amber-400">
                                        {lockedMessage}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-1">
                                {onMoveUp && index > 0 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onMoveUp(question)}
                                    >
                                        Up
                                    </Button>
                                )}
                                {onMoveDown && index < questions.length - 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onMoveDown(question)}
                                    >
                                        Down
                                    </Button>
                                )}
                                {onToggleActive && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onToggleActive(question)}
                                    >
                                        {question.is_active
                                            ? 'Deactivate'
                                            : 'Activate'}
                                    </Button>
                                )}
                                {onEdit && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onEdit(question)}
                                    >
                                        Edit
                                    </Button>
                                )}
                                {onDelete && !lockedMessage && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                        onClick={() => onDelete(question)}
                                    >
                                        Delete
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
