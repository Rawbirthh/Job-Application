'use client';

import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    store as savedSearchesStore,
    update as savedSearchesUpdate,
} from '@/routes/candidate/saved-searches';
import type { SaveSearchDialogForm, SaveSearchDialogProps } from '@/types';
import { ALERT_FREQUENCIES } from '@/types/saved-search';

export default function SaveSearchDialog({
    open,
    onOpenChange,
    mode,
    savedSearch,
    filterOptions,
}: SaveSearchDialogProps) {
    const defaultForm: SaveSearchDialogForm = {
        name: '',
        search: '',
        category_id: null,
        employment_type_id: null,
        workplace_arrangement_id: null,
        experience_level_id: null,
        alerts_enabled: true,
        alert_frequency: 'daily',
    };

    const saveForm = useForm<SaveSearchDialogForm>(defaultForm);

    const wasOpenRef = useRef(false);

    // Synchronise server-owned row data into the form when editing.
    // reset() only accepts field keys in this Inertia version, so set each
    // field explicitly.
    useEffect(() => {
        if (!open || mode !== 'edit' || !savedSearch) {
            return;
        }
        if (wasOpenRef.current) {
            return;
        }
        wasOpenRef.current = true;

        saveForm.setData('name', savedSearch.name ?? '');
        saveForm.setData('search', savedSearch.search ?? '');
        saveForm.setData(
            'category_id',
            savedSearch.category?.id ? String(savedSearch.category.id) : null,
        );
        saveForm.setData(
            'employment_type_id',
            savedSearch.employment_type?.id
                ? String(savedSearch.employment_type.id)
                : null,
        );
        saveForm.setData(
            'workplace_arrangement_id',
            savedSearch.workplace_arrangement?.id
                ? String(savedSearch.workplace_arrangement.id)
                : null,
        );
        saveForm.setData(
            'experience_level_id',
            savedSearch.experience_level?.id
                ? String(savedSearch.experience_level.id)
                : null,
        );
        saveForm.setData('alerts_enabled', savedSearch.alerts_enabled);
        saveForm.setData('alert_frequency', savedSearch.alert_frequency);
    }, [open, mode, savedSearch, saveForm]);

    useEffect(() => {
        if (!open) {
            wasOpenRef.current = false;
        }
    }, [open]);

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        // Convert 'all' sentinel to null so backend validation passes
        // (backend expects integer or null, not the string 'all')
        if (saveForm.data.category_id === 'all') {
            saveForm.setData('category_id', null);
        }
        if (saveForm.data.employment_type_id === 'all') {
            saveForm.setData('employment_type_id', null);
        }
        if (saveForm.data.workplace_arrangement_id === 'all') {
            saveForm.setData('workplace_arrangement_id', null);
        }
        if (saveForm.data.experience_level_id === 'all') {
            saveForm.setData('experience_level_id', null);
        }

        if (mode === 'create') {
            saveForm.post(savedSearchesStore.url(), {
                preserveScroll: true,
                onSuccess: () => {
                    saveForm.reset();
                    onOpenChange(false);
                },
            });
        } else if (savedSearch) {
            saveForm.put(
                savedSearchesUpdate.url({ savedSearch: savedSearch.id }),
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        saveForm.reset();
                        onOpenChange(false);
                    },
                },
            );
        }
    };

    const title = mode === 'create' ? 'Save this search' : 'Edit saved search';

    const searchesError = (
        saveForm.errors as Record<string, string[] | undefined>
    )['searches']?.join(', ');

    return (
        <>
            <Head title={title} />

            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>
                            Save the current public job-search filters so you
                            can rerun them in one click. When alerts are enabled
                            you'll receive an in-app notification whenever new
                            matching jobs are published.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="saved-search-name">Name</Label>
                                <Input
                                    id="saved-search-name"
                                    placeholder="e.g. React developer jobs"
                                    value={saveForm.data.name}
                                    onChange={(event) =>
                                        saveForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={120}
                                />
                                <InputError
                                    message={
                                        saveForm.errors.name as
                                            | string
                                            | undefined
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Optional. Leave blank to use the criteria
                                    summary.
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="saved-search-keywords">
                                    Keywords / location
                                </Label>
                                <Input
                                    id="saved-search-keywords"
                                    placeholder="e.g. React, remote"
                                    value={saveForm.data.search}
                                    onChange={(event) =>
                                        saveForm.setData(
                                            'search',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={180}
                                />
                                <InputError
                                    message={
                                        saveForm.errors.search as
                                            | string
                                            | undefined
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-2 pt-1">
                            <Label htmlFor="saved-search-category">
                                Category
                            </Label>
                            <Select
                                value={saveForm.data.category_id ?? undefined}
                                onValueChange={(value) =>
                                    saveForm.setData('category_id', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All categories" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All categories
                                    </SelectItem>
                                    {filterOptions.categories.map((option) => (
                                        <SelectItem
                                            key={option.id}
                                            value={String(option.id)}
                                        >
                                            {option.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2 pt-1">
                            <Label htmlFor="saved-search-employment-type">
                                Employment type
                            </Label>
                            <Select
                                value={
                                    saveForm.data.employment_type_id ??
                                    undefined
                                }
                                onValueChange={(value) =>
                                    saveForm.setData(
                                        'employment_type_id',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All types
                                    </SelectItem>
                                    {filterOptions.employmentTypes.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={String(option.id)}
                                            >
                                                {option.name}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2 pt-1">
                            <Label htmlFor="saved-search-workplace-arrangement">
                                Workplace arrangement
                            </Label>
                            <Select
                                value={
                                    saveForm.data.workplace_arrangement_id ??
                                    undefined
                                }
                                onValueChange={(value) =>
                                    saveForm.setData(
                                        'workplace_arrangement_id',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All arrangements" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All arrangements
                                    </SelectItem>
                                    {filterOptions.workplaceArrangements.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={String(option.id)}
                                            >
                                                {option.name}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2 pt-1">
                            <Label htmlFor="saved-search-experience-level">
                                Experience level
                            </Label>
                            <Select
                                value={
                                    saveForm.data.experience_level_id ??
                                    undefined
                                }
                                onValueChange={(value) =>
                                    saveForm.setData(
                                        'experience_level_id',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All levels" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All levels
                                    </SelectItem>
                                    {filterOptions.experienceLevels.map(
                                        (option) => (
                                            <SelectItem
                                                key={option.id}
                                                value={String(option.id)}
                                            >
                                                {option.name}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2 border-t border-border pt-1 pt-4">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="saved-search-alerts"
                                    checked={saveForm.data.alerts_enabled}
                                    onCheckedChange={(checked) =>
                                        saveForm.setData(
                                            'alerts_enabled',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="saved-search-alerts">
                                    Alert me about new matching jobs
                                </Label>
                            </div>

                            {saveForm.data.alerts_enabled && (
                                <div className="ml-8 space-y-2 border-l border-border pt-1 pl-4">
                                    <div className="space-y-2">
                                        <Label>Frequency</Label>
                                        <Select
                                            value={
                                                saveForm.data.alert_frequency
                                            }
                                            onValueChange={(value) =>
                                                saveForm.setData(
                                                    'alert_frequency',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Daily" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {ALERT_FREQUENCIES.map(
                                                    (frequency) => (
                                                        <SelectItem
                                                            key={
                                                                frequency.value
                                                            }
                                                            value={
                                                                frequency.value
                                                            }
                                                        >
                                                            {frequency.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            )}

                            {searchesError && (
                                <InputError
                                    message={searchesError}
                                    className="text-sm text-red-600"
                                />
                            )}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => onOpenChange(false)}
                                disabled={saveForm.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={saveForm.processing}
                            >
                                {mode === 'create'
                                    ? 'Save search'
                                    : 'Save changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
