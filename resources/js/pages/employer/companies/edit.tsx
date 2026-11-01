import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFlashToast } from '@/hooks/use-flash-toast';
import {
    index as companiesIndex,
    show as companiesShow,
    update as companiesUpdate,
} from '@/routes/employer/companies';
import type { Company, CompanyForm } from '@/types';

type Props = {
    company: Company;
};

export default function EmployerCompanyEdit({ company }: Props) {
    useFlashToast();

    const form = useForm<CompanyForm>({
        name: company.name,
        slug: company.slug,
        overview: company.overview,
        industry: company.industry,
        website: company.website,
        size_band: company.size_band,
        location: company.location,
        logo: null,
        remove_logo: false,
        is_active: company.is_active,
    });

    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const handleLogoChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        form.setData('logo', file);
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        setPreviewUrl(file ? URL.createObjectURL(file) : null);
        if (file) {
            form.setData('remove_logo', false);
        }
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.put(companiesUpdate.url(company.id), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const currentLogo = form.data.remove_logo
        ? null
        : (previewUrl ?? company.logo_url);

    return (
        <>
            <Head title={`Edit ${company.name}`} />

            <div className="flex flex-1 flex-col gap-6 p-6 md:p-8">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={companiesShow(company.id)}>
                            <ArrowLeft className="size-4" />
                            Back to company
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                        Edit Company Profile
                    </h1>
                    <p className="text-sm text-muted-foreground md:text-base">
                        Update the details for {company.name}.
                    </p>
                </div>

                <Card className="border-border">
                    <CardHeader>
                        <CardTitle className="text-lg">
                            Company details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-4"
                            aria-busy={form.processing}
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    Company name{' '}
                                    <span
                                        className="text-red-600"
                                        aria-hidden="true"
                                    >
                                        *
                                    </span>
                                </Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    required
                                    minLength={2}
                                    maxLength={150}
                                    disabled={form.processing}
                                    aria-invalid={
                                        form.errors.name ? true : undefined
                                    }
                                    aria-describedby={
                                        form.errors.name
                                            ? 'name-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="name-error"
                                    message={form.errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">Slug</Label>
                                <Input
                                    id="slug"
                                    value={form.data.slug}
                                    onChange={(e) =>
                                        form.setData('slug', e.target.value)
                                    }
                                    maxLength={100}
                                    disabled={form.processing}
                                    aria-invalid={
                                        form.errors.slug ? true : undefined
                                    }
                                    aria-describedby="slug-help slug-error"
                                />
                                <p
                                    id="slug-help"
                                    className="text-xs text-muted-foreground"
                                >
                                    Unique identifier. Leave as-is to keep the
                                    current slug.
                                </p>
                                <InputError
                                    id="slug-error"
                                    message={form.errors.slug}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="overview">Overview</Label>
                                <Textarea
                                    id="overview"
                                    value={form.data.overview ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'overview',
                                            e.target.value === ''
                                                ? null
                                                : e.target.value,
                                        )
                                    }
                                    maxLength={5000}
                                    rows={4}
                                    disabled={form.processing}
                                    aria-invalid={
                                        form.errors.overview ? true : undefined
                                    }
                                    aria-describedby="overview-error"
                                />
                                <InputError
                                    id="overview-error"
                                    message={form.errors.overview}
                                />
                            </div>

                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="industry">Industry</Label>
                                    <Input
                                        id="industry"
                                        value={form.data.industry ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'industry',
                                                e.target.value === ''
                                                    ? null
                                                    : e.target.value,
                                            )
                                        }
                                        maxLength={100}
                                        disabled={form.processing}
                                        aria-invalid={
                                            form.errors.industry
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby="industry-error"
                                    />
                                    <InputError
                                        id="industry-error"
                                        message={form.errors.industry}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="size_band">Size band</Label>
                                    <Input
                                        id="size_band"
                                        value={form.data.size_band ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'size_band',
                                                e.target.value === ''
                                                    ? null
                                                    : e.target.value,
                                            )
                                        }
                                        maxLength={50}
                                        disabled={form.processing}
                                        aria-invalid={
                                            form.errors.size_band
                                                ? true
                                                : undefined
                                        }
                                        aria-describedby="size_band-error"
                                    />
                                    <InputError
                                        id="size_band-error"
                                        message={form.errors.size_band}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="website">Website</Label>
                                <Input
                                    id="website"
                                    type="url"
                                    value={form.data.website ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'website',
                                            e.target.value === ''
                                                ? null
                                                : e.target.value,
                                        )
                                    }
                                    maxLength={255}
                                    placeholder="https://example.com"
                                    disabled={form.processing}
                                    aria-invalid={
                                        form.errors.website ? true : undefined
                                    }
                                    aria-describedby="website-error"
                                />
                                <InputError
                                    id="website-error"
                                    message={form.errors.website}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="location">Location</Label>
                                <Input
                                    id="location"
                                    value={form.data.location ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'location',
                                            e.target.value === ''
                                                ? null
                                                : e.target.value,
                                        )
                                    }
                                    maxLength={255}
                                    disabled={form.processing}
                                    aria-invalid={
                                        form.errors.location ? true : undefined
                                    }
                                    aria-describedby="location-error"
                                />
                                <InputError
                                    id="location-error"
                                    message={form.errors.location}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="logo">Logo</Label>
                                {currentLogo && (
                                    <img
                                        src={currentLogo}
                                        alt="Current logo"
                                        className="size-16 rounded-md object-cover"
                                    />
                                )}
                                <input
                                    id="logo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={handleLogoChange}
                                    disabled={form.processing}
                                    className="block w-full text-sm text-muted-foreground file:mr-3 file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm file:font-medium"
                                    aria-describedby="logo-help logo-error"
                                />
                                <p
                                    id="logo-help"
                                    className="text-xs text-muted-foreground"
                                >
                                    JPEG, PNG, or WebP. Maximum 2 MB. Selecting
                                    a new file replaces the current logo.
                                </p>
                                <InputError
                                    id="logo-error"
                                    message={form.errors.logo}
                                />
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="remove_logo"
                                    checked={form.data.remove_logo}
                                    onCheckedChange={(checked) => {
                                        const value = Boolean(checked);
                                        form.setData('remove_logo', value);
                                        if (value) {
                                            form.setData('logo', null);
                                            if (previewUrl) {
                                                URL.revokeObjectURL(previewUrl);
                                                setPreviewUrl(null);
                                            }
                                        }
                                    }}
                                    disabled={form.processing}
                                />
                                <Label
                                    htmlFor="remove_logo"
                                    className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Remove current logo
                                </Label>
                            </div>
                            <InputError message={form.errors.remove_logo} />

                            <div className="flex items-center space-x-2 pt-2">
                                <Checkbox
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            Boolean(checked),
                                        )
                                    }
                                    disabled={form.processing}
                                />
                                <Label
                                    htmlFor="is_active"
                                    className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    Active (visible on public pages)
                                </Label>
                            </div>
                            <InputError message={form.errors.is_active} />

                            <div className="flex items-center gap-2 pt-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing && (
                                        <Spinner className="mr-2" />
                                    )}
                                    Save changes
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    asChild
                                    disabled={form.processing}
                                >
                                    <Link href={companiesShow(company.id)}>
                                        Cancel
                                    </Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EmployerCompanyEdit.layout = {
    breadcrumbs: [
        {
            title: 'Company Profiles',
            href: companiesIndex(),
        },
        {
            title: 'Edit',
            href: companiesIndex(),
        },
    ],
};
