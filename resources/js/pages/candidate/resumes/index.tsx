import { Head, Link, router } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    Download,
    Trash2,
    Upload,
    FileUp,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useFlashToast } from '@/hooks/use-flash-toast';
import candidate from '@/routes/candidate';
import type { ResumeIndexProps } from '@/types';

type Props = ResumeIndexProps;

export default function CandidateResumes({ resume }: Props) {
    useFlashToast();

    const uploadForm = useForm<{ file: File | null }>({
        file: null,
    });

    const isBlank = resume === null;

    const handleUploadSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post(candidate.resumes.upload.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
            },
        });
    };

    const handleDelete = () => {
        if (!resume) return;

        if (
            confirm(
                'Are you sure? This will remove your resume from all applications.',
            )
        ) {
            router.delete(candidate.resumes.delete.url(resume.id), {
                preserveScroll: true,
            });
        }
    };

    const handleDownload = () => {
        if (!resume) return;
        window.open(candidate.resume.download.url(resume.id), '_blank');
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(2))} ${sizes[i]}`;
    };

    const lastUpdated = resume?.updated_at
        ? new Date(resume.updated_at).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : null;

    const renderFilePicker = (id: string, labelText: string) => (
        <div className="grid gap-3">
            <Label htmlFor={id}>{labelText}</Label>
            <div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                <input
                    id={id}
                    type="file"
                    accept="application/pdf"
                    className="hidden"
                    onChange={(e) =>
                        uploadForm.setData('file', e.target.files?.[0] ?? null)
                    }
                    disabled={uploadForm.processing}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => document.getElementById(id)?.click()}
                    disabled={uploadForm.processing}
                    className="w-full sm:w-auto"
                >
                    <FileUp className="mr-2 size-4" />
                    Choose PDF file
                </Button>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-foreground">
                        {uploadForm.data.file
                            ? uploadForm.data.file.name
                            : 'No file chosen'}
                    </p>
                    {uploadForm.data.file && (
                        <p className="text-xs text-muted-foreground">
                            {(
                                uploadForm.data.file.size /
                                (1024 * 1024)
                            ).toFixed(2)}{' '}
                            MB • Ready to upload
                        </p>
                    )}
                </div>
            </div>
            <p className="text-xs text-muted-foreground">
                Accepted format: PDF only. Maximum file size: 5MB.
            </p>
            <InputError message={uploadForm.errors.file} />
        </div>
    );

    return (
        <>
            <Head title="Resume Management" />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={candidate.dashboard()}>
                            <ArrowLeft className="size-4" />
                            Back to dashboard
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Resume Management
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Upload and manage your private PDF resume used for job
                        applications.
                    </p>
                    {lastUpdated && (
                        <p className="text-xs text-muted-foreground">
                            Last updated: {lastUpdated}
                        </p>
                    )}
                </div>

                {isBlank ? (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Upload Resume
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={handleUploadSubmit}
                                className="space-y-6"
                            >
                                {renderFilePicker('file', 'Select PDF Resume')}

                                <Button
                                    type="submit"
                                    disabled={
                                        uploadForm.processing ||
                                        !uploadForm.data.file
                                    }
                                >
                                    {uploadForm.processing && (
                                        <Spinner className="mr-2" />
                                    )}
                                    <Upload className="mr-2 size-4" />
                                    Upload resume
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                ) : (
                    <Card className="border-border">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Current Resume
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="flex items-center gap-4 rounded-lg border border-border p-6">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <FileText className="size-8 text-primary" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <h3 className="truncate text-lg font-semibold">
                                        {resume.original_filename}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {resume.mime_type} •{' '}
                                        {formatFileSize(resume.size)}
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button
                                    variant="outline"
                                    onClick={handleDownload}
                                >
                                    <Download className="mr-2 size-4" />
                                    Download
                                </Button>

                                <Button
                                    variant="destructive"
                                    onClick={handleDelete}
                                >
                                    <Trash2 className="mr-2 size-4" />
                                    Delete resume
                                </Button>
                            </div>

                            <div className="border-t border-border pt-6">
                                <h3 className="text-md mb-3 font-semibold">
                                    Replace Resume
                                </h3>
                                <form
                                    onSubmit={handleUploadSubmit}
                                    className="space-y-6"
                                >
                                    {renderFilePicker(
                                        'replace-file',
                                        'Select New PDF Resume',
                                    )}

                                    <Button
                                        type="submit"
                                        disabled={
                                            uploadForm.processing ||
                                            !uploadForm.data.file
                                        }
                                    >
                                        {uploadForm.processing && (
                                            <Spinner className="mr-2" />
                                        )}
                                        <Upload className="mr-2 size-4" />
                                        Replace resume
                                    </Button>
                                </form>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

CandidateResumes.layout = {
    breadcrumbs: [
        {
            title: 'Candidate Resumes',
            href: candidate.resumes.index(),
        },
    ],
};
