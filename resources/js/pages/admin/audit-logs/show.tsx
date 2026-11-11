import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as auditLogsIndex } from '@/routes/admin/audit-logs';
import type { AdminAuditLogShowProps } from '@/types';
import { auditActionLabel, auditTargetTypeLabel } from '@/types/audit-log';

export default function AdminAuditLogShow({
    auditLog,
}: AdminAuditLogShowProps) {
    const actor = auditLog.actor?.name ?? auditLog.actor_email ?? 'System';

    const actionLabel =
        auditLog.action_label || auditActionLabel(auditLog.action);

    const targetTypeLabel =
        auditLog.target_type_label ||
        auditTargetTypeLabel(auditLog.target_type);

    const target =
        auditLog.target_label ??
        (auditLog.target_id !== null ? `#${auditLog.target_id}` : '—');

    return (
        <>
            <Head title={`Audit entry #${auditLog.id}`} />

            <div className="max-w-4xl space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">
                                Audit entry #{auditLog.id}
                            </h1>
                            <Badge variant="outline">{actionLabel}</Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {targetTypeLabel}: {target}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={auditLogsIndex.url()}>
                            Back to audit log
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-6 rounded-lg border border-border p-6 shadow-sm sm:grid-cols-2">
                    <div>
                        <span className="text-sm font-medium text-muted-foreground">
                            Actor
                        </span>
                        <p className="text-base font-semibold">
                            {actor}
                            {auditLog.actor && auditLog.actor_email ? (
                                <span className="font-normal text-muted-foreground">
                                    ({auditLog.actor_email})
                                </span>
                            ) : null}
                        </p>
                    </div>
                    <div>
                        <span className="text-sm font-medium text-muted-foreground">
                            Action
                        </span>
                        <p className="text-base font-semibold">{actionLabel}</p>
                    </div>
                    <div>
                        <span className="text-sm font-medium text-muted-foreground">
                            Target type
                        </span>
                        <p className="text-base font-semibold">
                            {targetTypeLabel}
                        </p>
                    </div>
                    <div>
                        <span className="text-sm font-medium text-muted-foreground">
                            Target
                        </span>
                        <p className="text-base font-semibold break-words">
                            {target}
                        </p>
                    </div>
                    <div>
                        <span className="text-sm font-medium text-muted-foreground">
                            Occurred at
                        </span>
                        <p className="text-base font-semibold">
                            {new Date(auditLog.occurred_at).toLocaleString()}
                        </p>
                    </div>
                    <div>
                        <span className="text-sm font-medium text-muted-foreground">
                            IP address
                        </span>
                        <p className="text-base font-semibold">
                            {auditLog.ip_address ?? '—'}
                        </p>
                    </div>
                    <div className="sm:col-span-2">
                        <span className="text-sm font-medium text-muted-foreground">
                            Request id
                        </span>
                        <p className="font-mono text-base font-semibold break-all">
                            {auditLog.request_id ?? '—'}
                        </p>
                    </div>
                </div>

                <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                    <h2 className="text-lg font-semibold">Metadata</h2>

                    {auditLog.metadata.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No additional details.
                        </p>
                    ) : (
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {auditLog.metadata.map((entry) => (
                                <div key={entry.key}>
                                    <dt className="text-sm font-medium text-muted-foreground">
                                        {entry.key}
                                    </dt>
                                    <dd className="mt-1 text-sm break-words whitespace-pre-wrap">
                                        {entry.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </div>
            </div>
        </>
    );
}
