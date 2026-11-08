import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { index as templatesIndex } from '@/routes/employer/screening-templates';

interface TemplateQuestion {
    id: number;
    type: string;
    type_label: string;
    label: string;
    options: string[] | null;
    is_required: boolean;
    is_active: boolean;
    sort_order: number;
}

interface TemplateData {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    questions: TemplateQuestion[];
}

interface Props {
    template: TemplateData;
}

export default function ScreeningTemplateShow({ template }: Props) {
    useFlashToast();

    return (
        <>
            <Head title={`Template: ${template.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={templatesIndex()}>
                            <ArrowLeft className="size-4" />
                            Back to Templates
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        {template.name}
                    </h1>
                    {template.description && (
                        <p className="text-sm text-muted-foreground">
                            {template.description}
                        </p>
                    )}
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={
                                template.is_active ? 'default' : 'secondary'
                            }
                        >
                            {template.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                        <span className="text-xs text-muted-foreground">
                            {template.questions.length} question
                            {template.questions.length === 1 ? '' : 's'}
                        </span>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Questions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {template.questions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No questions in this template.
                            </p>
                        ) : (
                            template.questions.map((q) => (
                                <div
                                    key={q.id}
                                    className="rounded-md border p-4"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="font-medium">
                                                {q.label}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {q.type_label}
                                                {q.is_required
                                                    ? ' · Required'
                                                    : ' · Optional'}
                                                {!q.is_active
                                                    ? ' · Inactive'
                                                    : ''}
                                            </p>
                                        </div>
                                    </div>
                                    {q.options && q.options.length > 0 && (
                                        <ul className="mt-2 list-disc pl-5 text-sm text-muted-foreground">
                                            {q.options.map((opt) => (
                                                <li key={opt}>{opt}</li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
