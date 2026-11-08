import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ScreeningAnswer } from '@/types';

type Props = {
    answers: ScreeningAnswer[];
};

function formatAnswer(answer: ScreeningAnswer): string {
    switch (answer.question_type) {
        case 'yes_no':
            if (answer.answer_boolean === null) return '—';
            return answer.answer_boolean ? 'Yes' : 'No';
        case 'single_choice':
            return answer.selected_option ?? '—';
        default:
            return answer.answer_text ?? '—';
    }
}

export function ScreeningAnswers({ answers }: Props) {
    if (!answers || answers.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Screening Answers</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground italic">
                        No screening answers recorded.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-lg">Screening Answers</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {answers.map((answer, index) => (
                    <div
                        key={`${answer.question_id}-${index}`}
                        className="space-y-1 border-b border-border pb-3 last:border-b-0 last:pb-0"
                    >
                        <p className="text-sm font-medium text-foreground">
                            {answer.question_label ??
                                `Question ${answer.question_id}`}
                        </p>
                        {answer.question_type === 'single_choice' &&
                            answer.options && (
                                <p className="text-xs text-muted-foreground">
                                    Options: {answer.options.join(', ')}
                                </p>
                            )}
                        <p className="text-sm whitespace-pre-wrap text-foreground/90">
                            {formatAnswer(answer)}
                        </p>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
