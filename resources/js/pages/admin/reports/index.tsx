import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import ReportStatusBadge from '@/components/reports/report-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as reportsIndex,
    show as reportShow,
} from '@/routes/admin/reports';
import type {
    AdminReportsIndexProps,
    ReportReasonValue,
    ReportStatusFilterValue,
    ReportSubjectFilterValue,
} from '@/types';
import {
    reportReasonLabel,
    reportSubjectLabel,
    REPORT_REASONS,
    REPORT_STATUSES,
    REPORT_SUBJECTS,
} from '@/types/report';

export default function AdminReportsIndex({
    reports,
    filters,
}: AdminReportsIndexProps) {
    const [status, setStatus] = useState<ReportStatusFilterValue>(
        filters.status || 'pending',
    );
    const [subject, setSubject] = useState<ReportSubjectFilterValue>(
        filters.subject || 'all',
    );
    const [reason, setReason] = useState<ReportReasonValue | 'all'>(
        filters.reason || 'all',
    );

    const handleFilter = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            reportsIndex.url(),
            { status, subject, reason },
            { preserveState: true },
        );
    };

    return (
        <div className="space-y-6 p-6">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">
                    Report Moderation
                </h1>
                <p className="text-sm text-muted-foreground">
                    Review reports about jobs and employers, then investigate,
                    resolve, or dismiss them.
                </p>
            </div>

            <form
                onSubmit={handleFilter}
                className="flex flex-wrap items-end gap-4"
            >
                <div className="w-full sm:w-48">
                    <Label htmlFor="status">Status</Label>
                    <Select
                        value={status}
                        onValueChange={(value) =>
                            setStatus(value as ReportStatusFilterValue)
                        }
                    >
                        <SelectTrigger id="status" className="w-full">
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {REPORT_STATUSES.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="w-full sm:w-48">
                    <Label htmlFor="subject">Subject</Label>
                    <Select
                        value={subject}
                        onValueChange={(value) =>
                            setSubject(value as ReportSubjectFilterValue)
                        }
                    >
                        <SelectTrigger id="subject" className="w-full">
                            <SelectValue placeholder="Select subject" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All subjects</SelectItem>
                            {REPORT_SUBJECTS.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="w-full sm:w-48">
                    <Label htmlFor="reason">Reason</Label>
                    <Select
                        value={reason}
                        onValueChange={(value) =>
                            setReason(value as ReportReasonValue | 'all')
                        }
                    >
                        <SelectTrigger id="reason" className="w-full">
                            <SelectValue placeholder="Select reason" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All reasons</SelectItem>
                            {REPORT_REASONS.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <Button type="submit">Filter</Button>
            </form>

            <div className="rounded-lg border border-border bg-card shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="p-4 font-medium">Subject</th>
                                <th className="p-4 font-medium">Reason</th>
                                <th className="p-4 font-medium">Status</th>
                                <th className="p-4 font-medium">Reporter</th>
                                <th className="p-4 font-medium">Submitted</th>
                                <th className="p-4 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {reports.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        No reports match this filter.
                                    </td>
                                </tr>
                            ) : (
                                reports.data.map((report) => (
                                    <tr
                                        key={report.id}
                                        className="hover:bg-muted/50"
                                    >
                                        <td className="p-4 font-medium text-foreground">
                                            {report.subject_title || '—'}
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                            >
                                                {reportSubjectLabel(
                                                    report.subject_type,
                                                )}
                                            </Badge>
                                        </td>
                                        <td className="p-4 text-muted-foreground">
                                            {reportReasonLabel(report.reason)}
                                        </td>
                                        <td className="p-4">
                                            <ReportStatusBadge
                                                status={report.status}
                                            />
                                        </td>
                                        <td className="p-4 text-muted-foreground">
                                            {report.reporter?.name || '—'}
                                        </td>
                                        <td className="p-4 text-muted-foreground">
                                            {report.created_at
                                                ? new Date(
                                                      report.created_at,
                                                  ).toLocaleDateString()
                                                : '—'}
                                        </td>
                                        <td className="p-4 text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={reportShow.url({
                                                        report: report.id,
                                                    })}
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {reports.total > 0 && (
                <Pagination
                    currentPage={reports.current_page}
                    lastPage={reports.last_page}
                    perPage={reports.per_page}
                    total={reports.total}
                    prevPageUrl={reports.prev_page_url}
                    nextPageUrl={reports.next_page_url}
                />
            )}
        </div>
    );
}
