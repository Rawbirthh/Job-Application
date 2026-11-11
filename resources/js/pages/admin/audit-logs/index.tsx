import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    index as auditLogsIndex,
    show as auditLogShow,
} from '@/routes/admin/audit-logs';
import type {
    AdminAuditLogsIndexProps,
    AuditActionFilterValue,
    AuditActionSlug,
    AuditOption,
    AuditTargetTypeFilterValue,
    AuditTargetTypeSlug,
} from '@/types';
import { auditActionLabel, auditTargetTypeLabel } from '@/types/audit-log';

const ALL_FILTER_VALUE = 'all';

/** Filter query payload accepted by the admin audit log index. */
type AuditLogFilterPayload = {
    action?: string;
    target_type?: string;
    actor_id?: number;
    search?: string;
    from?: string;
    to?: string;
};

/**
 * Check a filter value against the catalogue sent by the server.
 *
 * The catalogue mirrors the backend enum, so a value taken from it is a
 * known action slug.
 */
function isActionOption(
    value: string,
    options: AuditOption[],
): value is AuditActionSlug {
    return options.some((option) => option.value === value);
}

function isTargetTypeOption(
    value: string,
    options: AuditOption[],
): value is AuditTargetTypeSlug {
    return options.some((option) => option.value === value);
}

/**
 * Narrow a filter value to the action filter vocabulary.
 *
 * Unknown values fall back to "all", mirroring the server-side whitelist.
 */
function toActionFilterValue(
    value: string | null,
    options: AuditOption[],
): AuditActionFilterValue {
    if (value === null || value === ALL_FILTER_VALUE) {
        return ALL_FILTER_VALUE;
    }

    return isActionOption(value, options) ? value : ALL_FILTER_VALUE;
}

/** Narrow a filter value to the target type filter vocabulary. */
function toTargetTypeFilterValue(
    value: string | null,
    options: AuditOption[],
): AuditTargetTypeFilterValue {
    if (value === null || value === ALL_FILTER_VALUE) {
        return ALL_FILTER_VALUE;
    }

    return isTargetTypeOption(value, options) ? value : ALL_FILTER_VALUE;
}

export default function AdminAuditLogsIndex({
    auditLogs,
    filters,
    actions,
    targetTypes,
}: AdminAuditLogsIndexProps) {
    const { errors } = usePage<{
        errors: {
            action?: string;
            target_type?: string;
            search?: string;
            from?: string;
            to?: string;
        };
    }>().props;

    const [action, setAction] = useState<AuditActionFilterValue>(
        toActionFilterValue(filters.action, actions),
    );
    const [targetType, setTargetType] = useState<AuditTargetTypeFilterValue>(
        toTargetTypeFilterValue(filters.target_type, targetTypes),
    );
    const [search, setSearch] = useState(filters.search ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [isFiltering, setIsFiltering] = useState(false);

    const hasFilters =
        filters.action !== null ||
        filters.target_type !== null ||
        filters.actor_id !== null ||
        filters.search !== null ||
        filters.from !== null ||
        filters.to !== null;

    const handleFilter = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const payload: AuditLogFilterPayload = {};

        if (action !== ALL_FILTER_VALUE) {
            payload.action = action;
        }

        if (targetType !== ALL_FILTER_VALUE) {
            payload.target_type = targetType;
        }

        const term = search.trim();

        if (term !== '') {
            payload.search = term;
        }

        if (from !== '') {
            payload.from = from;
        }

        if (to !== '') {
            payload.to = to;
        }

        // The actor filter has no dedicated control; keep an existing one so a
        // crafted query string is not silently dropped.
        if (filters.actor_id !== null) {
            payload.actor_id = filters.actor_id;
        }

        router.get(auditLogsIndex.url(), payload, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setIsFiltering(true),
            onFinish: () => setIsFiltering(false),
        });
    };

    const resetFilters = () => {
        setAction(ALL_FILTER_VALUE);
        setTargetType(ALL_FILTER_VALUE);
        setSearch('');
        setFrom('');
        setTo('');
    };

    return (
        <>
            <Head title="Audit Log" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Audit Log
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Review the append-only trail of administrative and
                        security events recorded by the platform.
                    </p>
                </div>

                <form
                    onSubmit={handleFilter}
                    aria-busy={isFiltering}
                    className="flex flex-wrap items-end gap-4"
                >
                    <div className="w-full sm:w-56">
                        <Label htmlFor="audit-action">Action</Label>
                        <Select
                            value={action}
                            onValueChange={(value) =>
                                setAction(toActionFilterValue(value, actions))
                            }
                        >
                            <SelectTrigger
                                id="audit-action"
                                className="w-full"
                                aria-invalid={errors.action ? true : undefined}
                                aria-describedby={
                                    errors.action
                                        ? 'audit-action-error'
                                        : undefined
                                }
                            >
                                <SelectValue placeholder="Select action" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_FILTER_VALUE}>
                                    All actions
                                </SelectItem>
                                {actions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            id="audit-action-error"
                            message={errors.action}
                            className="mt-2"
                        />
                    </div>

                    <div className="w-full sm:w-52">
                        <Label htmlFor="audit-target-type">Target type</Label>
                        <Select
                            value={targetType}
                            onValueChange={(value) =>
                                setTargetType(
                                    toTargetTypeFilterValue(value, targetTypes),
                                )
                            }
                        >
                            <SelectTrigger
                                id="audit-target-type"
                                className="w-full"
                                aria-invalid={
                                    errors.target_type ? true : undefined
                                }
                                aria-describedby={
                                    errors.target_type
                                        ? 'audit-target-type-error'
                                        : undefined
                                }
                            >
                                <SelectValue placeholder="Select target type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_FILTER_VALUE}>
                                    All target types
                                </SelectItem>
                                {targetTypes.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            id="audit-target-type-error"
                            message={errors.target_type}
                            className="mt-2"
                        />
                    </div>

                    <div className="w-full sm:w-64">
                        <Label htmlFor="audit-search">Search</Label>
                        <Input
                            id="audit-search"
                            name="search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Actor email or target label"
                            autoComplete="off"
                            maxLength={100}
                            aria-invalid={errors.search ? true : undefined}
                            aria-describedby={
                                errors.search ? 'audit-search-error' : undefined
                            }
                        />
                        <InputError
                            id="audit-search-error"
                            message={errors.search}
                            className="mt-2"
                        />
                    </div>

                    <div className="w-full sm:w-44">
                        <Label htmlFor="audit-from">From</Label>
                        <Input
                            id="audit-from"
                            name="from"
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            aria-invalid={errors.from ? true : undefined}
                            aria-describedby={
                                errors.from ? 'audit-from-error' : undefined
                            }
                        />
                        <InputError
                            id="audit-from-error"
                            message={errors.from}
                            className="mt-2"
                        />
                    </div>

                    <div className="w-full sm:w-44">
                        <Label htmlFor="audit-to">To</Label>
                        <Input
                            id="audit-to"
                            name="to"
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            aria-invalid={errors.to ? true : undefined}
                            aria-describedby={
                                errors.to ? 'audit-to-error' : undefined
                            }
                        />
                        <InputError
                            id="audit-to-error"
                            message={errors.to}
                            className="mt-2"
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" disabled={isFiltering}>
                            {isFiltering ? (
                                <Spinner />
                            ) : (
                                <Search aria-hidden="true" />
                            )}
                            Filter
                        </Button>

                        {hasFilters && (
                            <Button type="button" variant="outline" asChild>
                                <Link
                                    href={auditLogsIndex.url()}
                                    onClick={resetFilters}
                                >
                                    <X aria-hidden="true" />
                                    Reset
                                </Link>
                            </Button>
                        )}
                    </div>
                </form>

                <div className="rounded-lg border border-border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Administrative audit trail entries
                            </caption>
                            <thead className="border-b bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th scope="col" className="p-4 font-medium">
                                        When
                                    </th>
                                    <th scope="col" className="p-4 font-medium">
                                        Actor
                                    </th>
                                    <th scope="col" className="p-4 font-medium">
                                        Action
                                    </th>
                                    <th scope="col" className="p-4 font-medium">
                                        Target
                                    </th>
                                    <th scope="col" className="p-4 font-medium">
                                        IP
                                    </th>
                                    <th scope="col" className="p-4 font-medium">
                                        Request id
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {auditLogs.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="p-8 text-center text-muted-foreground"
                                        >
                                            {hasFilters
                                                ? 'No audit entries match this filter.'
                                                : 'No audit entries yet.'}
                                        </td>
                                    </tr>
                                ) : (
                                    auditLogs.data.map((log) => {
                                        const actionLabel =
                                            log.action_label ||
                                            auditActionLabel(log.action);
                                        const targetTypeLabel =
                                            log.target_type_label ||
                                            auditTargetTypeLabel(
                                                log.target_type,
                                            );

                                        return (
                                            <tr
                                                key={log.id}
                                                className="hover:bg-muted/50"
                                            >
                                                <td className="p-4 whitespace-nowrap text-muted-foreground">
                                                    {new Date(
                                                        log.occurred_at,
                                                    ).toLocaleString()}
                                                </td>
                                                <td className="p-4 text-muted-foreground">
                                                    {log.actor?.name ??
                                                        log.actor_email ??
                                                        'System'}
                                                </td>
                                                <td className="p-4">
                                                    <Link
                                                        href={auditLogShow.url(
                                                            log.id,
                                                        )}
                                                        className="text-primary underline-offset-4 hover:underline"
                                                        aria-label={`View audit entry ${log.id}: ${actionLabel}`}
                                                    >
                                                        <Badge variant="outline">
                                                            {actionLabel}
                                                        </Badge>
                                                    </Link>
                                                </td>
                                                <td className="p-4">
                                                    <div className="font-medium text-foreground">
                                                        {targetTypeLabel}
                                                    </div>
                                                    {log.target_label ? (
                                                        <div className="text-xs text-muted-foreground">
                                                            {log.target_label}
                                                        </div>
                                                    ) : log.target_id !==
                                                      null ? (
                                                        <div className="text-xs text-muted-foreground">
                                                            #{log.target_id}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="p-4 text-muted-foreground">
                                                    {log.ip_address ?? '—'}
                                                </td>
                                                <td className="p-4 font-mono text-xs text-muted-foreground">
                                                    {log.request_id ?? '—'}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {auditLogs.total > 0 && (
                    <Pagination
                        currentPage={auditLogs.current_page}
                        lastPage={auditLogs.last_page}
                        perPage={auditLogs.per_page}
                        total={auditLogs.total}
                        prevPageUrl={auditLogs.prev_page_url}
                        nextPageUrl={auditLogs.next_page_url}
                    />
                )}
            </div>
        </>
    );
}
