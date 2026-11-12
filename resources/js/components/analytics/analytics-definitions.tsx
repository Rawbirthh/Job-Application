import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type { AnalyticsDefinition } from '@/types/analytics';

export type AnalyticsDefinitionsProps = {
    definitions: AnalyticsDefinition[];
    /** Whether the disclosure starts expanded. */
    defaultOpen?: boolean;
};

/**
 * The disclosure that documents how every metric on the page is counted.
 *
 * The definitions are sent by the server so the documented formula and the
 * executed query can never drift apart.
 */
export default function AnalyticsDefinitions({
    definitions,
    defaultOpen = false,
}: AnalyticsDefinitionsProps) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <Card className="border-border">
            <CardHeader>
                <CardTitle className="text-base">
                    How these numbers are calculated
                </CardTitle>
                <CardDescription>
                    Every metric on this page uses the formulas below.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Collapsible open={open} onOpenChange={setOpen}>
                    <CollapsibleTrigger className="flex items-center gap-2 text-sm font-medium text-primary underline-offset-4 hover:underline">
                        <ChevronDown
                            aria-hidden="true"
                            className={cn(
                                'size-4 transition-transform',
                                open ? 'rotate-180' : '',
                            )}
                        />
                        {open
                            ? 'Hide the metric definitions'
                            : 'Show the metric definitions'}
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-4">
                        <dl className="space-y-4">
                            {definitions.map((definition) => (
                                <div key={definition.id}>
                                    <dt className="text-sm font-medium text-foreground">
                                        {definition.label}
                                    </dt>
                                    <dd className="mt-0.5 text-sm text-muted-foreground">
                                        {definition.description}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </CollapsibleContent>
                </Collapsible>
            </CardContent>
        </Card>
    );
}
