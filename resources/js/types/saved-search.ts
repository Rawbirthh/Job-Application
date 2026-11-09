export type SaveSearchFilterOption = {
    id: number;
    name: string;
};

export type SavedSearchCriteria = {
    search: string | null;
    category: number | null;
    employment_type: number | null;
    workplace_arrangement: number | null;
    experience_level: number | null;
};

export type SavedSearchRow = {
    id: number;
    name: string | null;
    search: string | null;
    alerts_enabled: boolean;
    alert_frequency: 'daily' | 'weekly';
    last_alerted_at: string | null;
    created_at: string | null;
    category: { id: number; name: string } | null;
    employment_type: { id: number; name: string } | null;
    workplace_arrangement: { id: number; name: string } | null;
    experience_level: { id: number; name: string } | null;
    criteria: SavedSearchCriteria;
};

export type SavedSearchesPaginated = {
    data: SavedSearchRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type SavedSearchesIndexProps = {
    savedSearches: SavedSearchesPaginated;
    filterOptions: {
        categories: SaveSearchFilterOption[];
        employmentTypes: SaveSearchFilterOption[];
        workplaceArrangements: SaveSearchFilterOption[];
        experienceLevels: SaveSearchFilterOption[];
    };
};

export type SaveSearchDialogForm = {
    name: string;
    search: string;
    category_id: string | null;
    employment_type_id: string | null;
    workplace_arrangement_id: string | null;
    experience_level_id: string | null;
    alerts_enabled: boolean;
    alert_frequency: string;
};

export type SaveSearchDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    savedSearch?: SavedSearchRow | null;
    filterOptions: SavedSearchesIndexProps['filterOptions'];
};

export type AlertFrequencyValue = 'daily' | 'weekly';

export const ALERT_FREQUENCIES: {
    value: AlertFrequencyValue;
    label: string;
}[] = [
    { value: 'daily', label: 'Daily' },
    { value: 'weekly', label: 'Weekly' },
];

/**
 * Build a deterministic, human-readable summary of saved-search criteria,
 * mirroring the same labels used on the public job listing.
 */
export function describeCriteria(
    criteria: SavedSearchCriteria | null,
    options: {
        categories: SaveSearchFilterOption[];
        employmentTypes: SaveSearchFilterOption[];
        workplaceArrangements: SaveSearchFilterOption[];
        experienceLevels: SaveSearchFilterOption[];
    },
): string {
    const parts: string[] = [];

    if (criteria?.search?.trim()) {
        parts.push(criteria.search.trim());
    }

    const lookup = (
        list: SaveSearchFilterOption[],
        id: number | null | undefined,
    ): string | null => {
        if (!id) {
            return null;
        }
        return list.find((option) => option.id === id)?.name ?? null;
    };

    for (const label of [
        lookup(options.categories, criteria?.category),
        lookup(options.employmentTypes, criteria?.employment_type),
        lookup(options.workplaceArrangements, criteria?.workplace_arrangement),
        lookup(options.experienceLevels, criteria?.experience_level),
    ]) {
        if (label) {
            parts.push(label);
        }
    }

    return parts.length ? parts.join(' · ') : 'All jobs';
}
