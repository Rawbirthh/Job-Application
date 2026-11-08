import type { ReactNode } from 'react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ScheduleInterviewForm } from './schedule-interview-form';

type Props = {
    applicationId: number;
    timezones: string[];
    children: ReactNode;
};

export function ScheduleInterviewDialog({
    applicationId,
    timezones,
    children,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Schedule an interview</DialogTitle>
                    <DialogDescription>
                        Enter the time as it should appear in the selected time
                        zone. The candidate is notified after the round is
                        created.
                    </DialogDescription>
                </DialogHeader>
                <ScheduleInterviewForm
                    mode="create"
                    applicationId={applicationId}
                    timezones={timezones}
                    onSuccess={() => setOpen(false)}
                    onCancel={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
