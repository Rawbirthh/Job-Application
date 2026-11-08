import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Props = {
    templates: { id: number; name: string; questions_count: number }[];
    onApply: (templateId: number) => void;
    processing?: boolean;
    triggerLabel?: string;
};

export function TemplateApplyDialog({
    templates,
    onApply,
    processing,
    triggerLabel = 'Apply template',
}: Props) {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<string>('');

    const handleApply = () => {
        if (!selected) return;
        onApply(Number(selected));
        setOpen(false);
        setSelected('');
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    disabled={processing || templates.length === 0}
                >
                    {triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Apply a template</DialogTitle>
                    <DialogDescription>
                        Template questions will be copied into this job in
                        template order.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-2">
                    <Select value={selected} onValueChange={setSelected}>
                        <SelectTrigger>
                            <SelectValue placeholder="Select a template" />
                        </SelectTrigger>
                        <SelectContent>
                            {templates.map((t) => (
                                <SelectItem key={t.id} value={String(t.id)}>
                                    {t.name} ({t.questions_count} questions)
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        onClick={handleApply}
                        disabled={!selected || processing}
                    >
                        Apply
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
