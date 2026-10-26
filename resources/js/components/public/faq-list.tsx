import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

export type FaqItem = {
    question: string;
    answer: string;
};

type FaqListProps = {
    items: readonly FaqItem[];
    className?: string;
};

export function FaqList({ items, className }: FaqListProps) {
    return (
        <div
            className={cn(
                'divide-y divide-border overflow-hidden rounded-2xl border border-border bg-background',
                className,
            )}
        >
            {items.map((item) => (
                <details key={item.question} className="group">
                    <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-left text-base font-medium text-foreground transition-colors hover:bg-home-surface focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none focus-visible:ring-inset [&::-webkit-details-marker]:hidden">
                        {item.question}
                        <ChevronDown
                            aria-hidden="true"
                            className="size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none"
                        />
                    </summary>
                    <div className="px-5 pb-5 text-sm leading-7 text-muted-foreground">
                        <p>{item.answer}</p>
                    </div>
                </details>
            ))}
        </div>
    );
}
