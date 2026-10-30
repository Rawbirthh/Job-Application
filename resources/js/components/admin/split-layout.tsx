import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type SplitLayoutProps = {
    title: string;
    description?: string;
    formTitle: string;
    formDescription?: string;
    form: ReactNode;
    table: ReactNode;
    className?: string;
};

export default function SplitLayout({
    title,
    description,
    formTitle,
    formDescription,
    form,
    table,
    className,
}: SplitLayoutProps) {
    return (
        <div className={cn('space-y-6 p-4 md:p-6', className)}>
            <div className="space-y-1">
                <h1 className="text-xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Form column (Left side on desktop) */}
                <div className="lg:col-span-5 xl:col-span-4">
                    <div className="sticky top-6 rounded-lg border border-border bg-card p-4 shadow-sm md:p-6">
                        <div className="mb-4 space-y-1">
                            <h2 className="text-lg font-medium tracking-tight">
                                {formTitle}
                            </h2>
                            {formDescription && (
                                <p className="text-sm text-muted-foreground">
                                    {formDescription}
                                </p>
                            )}
                        </div>
                        {form}
                    </div>
                </div>

                {/* Data Table column (Right side on desktop) */}
                <div className="lg:col-span-7 xl:col-span-8">
                    <div className="space-y-4">{table}</div>
                </div>
            </div>
        </div>
    );
}
