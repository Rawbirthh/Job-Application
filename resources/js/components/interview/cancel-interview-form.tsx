import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { EmployerInterviewRound, InterviewCancelFormData } from '@/types';
import { GlobalFormErrors } from './global-form-errors';

type Props = {
    round: EmployerInterviewRound;
    onSuccess: () => void;
    onCancel: () => void;
};

export function CancelInterviewForm({ round, onSuccess, onCancel }: Props) {
    const form = useForm<InterviewCancelFormData>({
        current_status: round.status,
        current_scheduled_at: round.scheduled_at,
        cancellation_reason: '',
    });

    const reasonId = `employer-interview-${round.id}-cancellation-reason`;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const currentStatus = round.status;
        const currentScheduledAt = round.scheduled_at;

        form.transform((data) => ({
            ...data,
            current_status: currentStatus,
            current_scheduled_at: currentScheduledAt,
        }));

        form.post(`/employer/interviews/${round.id}/cancel`, {
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <GlobalFormErrors
                errors={[
                    form.errors.current_status,
                    form.errors.current_scheduled_at,
                ]}
                title="This interview could not be cancelled. Refresh the page and try again."
            />

            <p className="text-sm text-muted-foreground">
                The candidate will be notified and the reason will be visible to
                them.
            </p>

            <div className="space-y-2">
                <Label htmlFor={reasonId}>Reason for cancellation</Label>
                <Textarea
                    id={reasonId}
                    value={form.data.cancellation_reason}
                    onChange={(event) =>
                        form.setData('cancellation_reason', event.target.value)
                    }
                    placeholder="Explain why this interview is being cancelled"
                    rows={4}
                    minLength={3}
                    maxLength={500}
                    required
                    disabled={form.processing}
                    aria-invalid={
                        form.errors.cancellation_reason ? true : undefined
                    }
                    aria-describedby={
                        form.errors.cancellation_reason
                            ? `${reasonId}-error`
                            : undefined
                    }
                />
                <p className="text-xs text-muted-foreground">
                    3–500 characters.
                </p>
                <InputError
                    id={`${reasonId}-error`}
                    message={form.errors.cancellation_reason}
                />
            </div>

            <div className="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                    disabled={form.processing}
                >
                    Keep interview
                </Button>
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner />}
                    {form.processing ? 'Cancelling...' : 'Cancel interview'}
                </Button>
            </div>
        </form>
    );
}
