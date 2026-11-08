import { getViewerTimeZone } from '@/lib/datetime';
import type { InterviewMode, InterviewStatus } from '@/types';

const ACTIVE_STATUSES: readonly InterviewStatus[] = [
    'scheduled',
    'accepted',
    'reschedule_requested',
];

const INTERVIEW_MODES: readonly InterviewMode[] = ['online', 'onsite', 'phone'];

export function isActiveInterviewStatus(status: InterviewStatus): boolean {
    return ACTIVE_STATUSES.includes(status);
}

export function isInterviewMode(value: string): value is InterviewMode {
    return INTERVIEW_MODES.includes(value as InterviewMode);
}

/**
 * Defaults to the viewer's zone when the server offers it, otherwise the first
 * server-provided option, otherwise the viewer zone.
 */
export function resolveDefaultTimeZone(
    timezones: string[],
    preferred?: string,
): string {
    if (preferred !== undefined && preferred !== '') {
        return preferred;
    }

    const viewerTimeZone = getViewerTimeZone();

    return timezones.includes(viewerTimeZone)
        ? viewerTimeZone
        : (timezones[0] ?? viewerTimeZone);
}

/**
 * Builds a select option list that always includes the viewer zone and any
 * preferred/prefilled zone, even if the server's list omits them.
 */
export function resolveTimeZoneOptions(
    timezones: string[],
    preferred?: string,
): string[] {
    const viewerTimeZone = getViewerTimeZone();
    const required = [preferred, viewerTimeZone].filter(
        (zone): zone is string => typeof zone === 'string' && zone !== '',
    );
    const options: string[] = [];

    for (const zone of [...required, ...timezones]) {
        if (!options.includes(zone)) {
            options.push(zone);
        }
    }

    return options;
}
