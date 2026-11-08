import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { toDateTimeLocalValue } from '@/lib/datetime';
import type {
    EmployerInterviewRound,
    InterviewRescheduleFormData,
    InterviewScheduleFormData,
} from '@/types';
import { GlobalFormErrors } from './global-form-errors';
import {
    isInterviewMode,
    resolveDefaultTimeZone,
    resolveTimeZoneOptions,
} from './interview-utils';

type CreateProps = {
    mode: 'create';
    applicationId: number;
    timezones: string[];
    onSuccess: () => void;
    onCancel: () => void;
};

type UpdateProps = {
    mode: 'update';
    round: EmployerInterviewRound;
    timezones: string[];
    onSuccess: () => void;
    onCancel: () => void;
};

type Props = CreateProps | UpdateProps;

function initialValues(props: Props): InterviewScheduleFormData {
    if (props.mode === 'update') {
        const { round } = props;
        const proposedTimeZone = round.requested_timezone ?? round.timezone;
        const sourceTimeIso =
            round.requested_scheduled_at ?? round.scheduled_at;

        return {
            scheduled_at_local: toDateTimeLocalValue(
                sourceTimeIso,
                proposedTimeZone,
            ),
            timezone: proposedTimeZone,
            duration_minutes: String(round.duration_minutes),
            mode: round.mode,
            location_details: round.location_details,
            candidate_instructions: round.candidate_instructions ?? '',
            internal_notes: round.internal_notes ?? '',
        };
    }

    return {
        scheduled_at_local: '',
        timezone: resolveDefaultTimeZone(props.timezones),
        duration_minutes: '60',
        mode: 'online',
        location_details: '',
        candidate_instructions: '',
        internal_notes: '',
    };
}

export function ScheduleInterviewForm(props: Props) {
    const { mode, timezones, onSuccess, onCancel } = props;
    const isUpdate = mode === 'update';
    const initial = initialValues(props);
    const timeZoneOptions = resolveTimeZoneOptions(
        timezones,
        isUpdate ? initial.timezone : undefined,
    );

    const form = useForm<InterviewRescheduleFormData>({
        ...initial,
        current_status: isUpdate ? props.round.status : 'scheduled',
        current_scheduled_at: isUpdate ? props.round.scheduled_at : '',
    });

    const fieldId = (name: string) =>
        `${isUpdate ? `employer-interview-${props.round.id}` : 'employer-interview-create'}-${name}`;

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (props.mode === 'create') {
            const applicationId = props.applicationId;

            form.transform((data) => ({
                scheduled_at_local: data.scheduled_at_local,
                timezone: data.timezone,
                duration_minutes: Number(data.duration_minutes),
                mode: data.mode,
                location_details: data.location_details,
                candidate_instructions: data.candidate_instructions,
                internal_notes: data.internal_notes,
            }));

            form.post(`/employer/applications/${applicationId}/interviews`, {
                preserveScroll: true,
                onSuccess,
            });

            return;
        }

        const interviewId = props.round.id;
        const currentStatus = props.round.status;
        const currentScheduledAt = props.round.scheduled_at;

        form.transform((data) => ({
            ...data,
            current_status: currentStatus,
            current_scheduled_at: currentScheduledAt,
        }));

        form.put(`/employer/interviews/${interviewId}`, {
            preserveScroll: true,
            onSuccess,
        });
    };

    const scheduledAtId = fieldId('scheduled-at');
    const timezoneId = fieldId('timezone');
    const durationId = fieldId('duration');
    const modeId = fieldId('mode');
    const locationId = fieldId('location');
    const instructionsId = fieldId('instructions');
    const notesId = fieldId('notes');

    return (
        <form onSubmit={submit} className="space-y-4">
            <GlobalFormErrors
                errors={[
                    form.errors.current_status,
                    form.errors.current_scheduled_at,
                ]}
                title="This interview could not be saved. Refresh the page and try again."
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={scheduledAtId}>Date and time</Label>
                    <Input
                        id={scheduledAtId}
                        type="datetime-local"
                        value={form.data.scheduled_at_local}
                        onChange={(event) =>
                            form.setData(
                                'scheduled_at_local',
                                event.target.value,
                            )
                        }
                        required
                        step={60}
                        disabled={form.processing}
                        aria-invalid={
                            form.errors.scheduled_at_local ? true : undefined
                        }
                        aria-describedby={
                            form.errors.scheduled_at_local
                                ? `${scheduledAtId}-error`
                                : undefined
                        }
                    />
                    <InputError
                        id={`${scheduledAtId}-error`}
                        message={form.errors.scheduled_at_local}
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor={timezoneId}>Time zone</Label>
                    <Select
                        value={form.data.timezone}
                        onValueChange={(value) =>
                            form.setData('timezone', value)
                        }
                        disabled={form.processing}
                    >
                        <SelectTrigger
                            id={timezoneId}
                            className="w-full"
                            aria-invalid={
                                form.errors.timezone ? true : undefined
                            }
                            aria-describedby={
                                form.errors.timezone
                                    ? `${timezoneId}-error`
                                    : undefined
                            }
                        >
                            <SelectValue placeholder="Select a time zone" />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            {timeZoneOptions.map((timeZone) => (
                                <SelectItem key={timeZone} value={timeZone}>
                                    {timeZone}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        id={`${timezoneId}-error`}
                        message={form.errors.timezone}
                    />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor={durationId}>Duration (minutes)</Label>
                    <Input
                        id={durationId}
                        type="number"
                        inputMode="numeric"
                        min={15}
                        max={480}
                        step={15}
                        value={form.data.duration_minutes}
                        onChange={(event) =>
                            form.setData('duration_minutes', event.target.value)
                        }
                        required
                        disabled={form.processing}
                        aria-invalid={
                            form.errors.duration_minutes ? true : undefined
                        }
                        aria-describedby={
                            form.errors.duration_minutes
                                ? `${durationId}-error`
                                : undefined
                        }
                    />
                    <InputError
                        id={`${durationId}-error`}
                        message={form.errors.duration_minutes}
                    />
                </div>

                <div className="space-y-2">
                    <Label htmlFor={modeId}>Interview mode</Label>
                    <Select
                        value={form.data.mode}
                        onValueChange={(value) => {
                            if (isInterviewMode(value)) {
                                form.setData('mode', value);
                            }
                        }}
                        disabled={form.processing}
                    >
                        <SelectTrigger
                            id={modeId}
                            className="w-full"
                            aria-invalid={form.errors.mode ? true : undefined}
                            aria-describedby={
                                form.errors.mode ? `${modeId}-error` : undefined
                            }
                        >
                            <SelectValue placeholder="Select a mode" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="online">Online</SelectItem>
                            <SelectItem value="onsite">Onsite</SelectItem>
                            <SelectItem value="phone">Phone</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        id={`${modeId}-error`}
                        message={form.errors.mode}
                    />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor={locationId}>Location / meeting details</Label>
                <Input
                    id={locationId}
                    value={form.data.location_details}
                    onChange={(event) =>
                        form.setData('location_details', event.target.value)
                    }
                    placeholder="Meeting link, address, or phone number"
                    maxLength={500}
                    required
                    disabled={form.processing}
                    aria-invalid={
                        form.errors.location_details ? true : undefined
                    }
                    aria-describedby={
                        form.errors.location_details
                            ? `${locationId}-error`
                            : undefined
                    }
                />
                <InputError
                    id={`${locationId}-error`}
                    message={form.errors.location_details}
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor={instructionsId}>
                    Candidate instructions (optional)
                </Label>
                <Textarea
                    id={instructionsId}
                    value={form.data.candidate_instructions}
                    onChange={(event) =>
                        form.setData(
                            'candidate_instructions',
                            event.target.value,
                        )
                    }
                    placeholder="Anything the candidate should prepare or bring"
                    rows={3}
                    maxLength={2000}
                    disabled={form.processing}
                    aria-invalid={
                        form.errors.candidate_instructions ? true : undefined
                    }
                    aria-describedby={
                        form.errors.candidate_instructions
                            ? `${instructionsId}-error`
                            : undefined
                    }
                />
                <InputError
                    id={`${instructionsId}-error`}
                    message={form.errors.candidate_instructions}
                />
            </div>

            <div className="space-y-2">
                <Label htmlFor={notesId}>Internal notes (optional)</Label>
                <Textarea
                    id={notesId}
                    value={form.data.internal_notes}
                    onChange={(event) =>
                        form.setData('internal_notes', event.target.value)
                    }
                    placeholder="Only visible to your team"
                    rows={3}
                    maxLength={2000}
                    disabled={form.processing}
                    aria-invalid={form.errors.internal_notes ? true : undefined}
                    aria-describedby={
                        form.errors.internal_notes
                            ? `${notesId}-error`
                            : undefined
                    }
                />
                <p className="text-xs text-muted-foreground">
                    Internal notes are never shown to the candidate.
                </p>
                <InputError
                    id={`${notesId}-error`}
                    message={form.errors.internal_notes}
                />
            </div>

            <div className="flex flex-col-reverse gap-2 border-t border-border pt-4 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                    disabled={form.processing}
                >
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {form.processing
                        ? isUpdate
                            ? 'Rescheduling...'
                            : 'Scheduling...'
                        : isUpdate
                          ? 'Reschedule interview'
                          : 'Schedule interview'}
                </Button>
            </div>
        </form>
    );
}
