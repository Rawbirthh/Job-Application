const FALLBACK_TIME_ZONE = 'UTC';

const UTC_ALIASES = new Set(['utc', 'etc/utc', 'gmt', 'etc/gmt', 'zulu']);

/**
 * Parses an ISO-8601 instant. Naive timestamps (no zone suffix) are treated as
 * UTC because every interview instant is stored and serialized as UTC.
 */
function toDate(iso: string): Date | null {
    const value = iso.trim();

    if (value === '') {
        return null;
    }

    const naive =
        /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/.test(value);
    const normalized = naive ? `${value.replace(' ', 'T')}Z` : value;
    const date = new Date(normalized);

    return Number.isNaN(date.getTime()) ? null : date;
}

function supportsTimeZone(timeZone: string): boolean {
    if (timeZone.trim() === '') {
        return false;
    }

    try {
        new Intl.DateTimeFormat('en-US', { timeZone }).format(new Date());

        return true;
    } catch {
        return false;
    }
}

/**
 * Best-effort browser IANA time zone with a safe `UTC` fallback for
 * environments where `Intl` is unavailable or returns an invalid value.
 */
export function getViewerTimeZone(): string {
    try {
        const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        return typeof timeZone === 'string' && supportsTimeZone(timeZone)
            ? timeZone
            : FALLBACK_TIME_ZONE;
    } catch {
        return FALLBACK_TIME_ZONE;
    }
}

/**
 * Formats an instant in a specific IANA time zone. Falls back to UTC when the
 * supplied zone is invalid so display never throws.
 */
export function formatInTimeZone(
    iso: string,
    timeZone: string,
    options: Intl.DateTimeFormatOptions = {},
): string {
    const date = toDate(iso);

    if (date === null) {
        return iso;
    }

    const zone = supportsTimeZone(timeZone) ? timeZone : FALLBACK_TIME_ZONE;

    return new Intl.DateTimeFormat('en-US', {
        ...options,
        timeZone: zone,
    }).format(date);
}

/**
 * Human-readable interview date/time with an explicit short zone name.
 */
export function formatInterviewDateTime(iso: string, timeZone: string): string {
    return formatInTimeZone(iso, timeZone, {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    });
}

/**
 * Converts an instant to the `YYYY-MM-DDTHH:mm` wall-clock value expected by a
 * `datetime-local` input in the supplied IANA zone.
 */
export function toDateTimeLocalValue(iso: string, timeZone: string): string {
    const date = toDate(iso);

    if (date === null) {
        return '';
    }

    const zone = supportsTimeZone(timeZone) ? timeZone : FALLBACK_TIME_ZONE;

    try {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: zone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(date);

        const values: Partial<Record<Intl.DateTimeFormatPartTypes, string>> =
            {};

        for (const part of parts) {
            values[part.type] = part.value;
        }

        const { year, month, day, hour, minute } = values;

        if (!year || !month || !day || !hour || !minute) {
            return '';
        }

        return `${year}-${month}-${day}T${hour}:${minute}`;
    } catch {
        return '';
    }
}

function normalizeTimeZone(timeZone: string): string {
    return timeZone
        .trim()
        .toLowerCase()
        .replace(/_/g, ' ')
        .replace(/\s+/g, ' ');
}

function resolvedTimeZone(timeZone: string): string | null {
    if (!supportsTimeZone(timeZone)) {
        return null;
    }

    try {
        return (
            new Intl.DateTimeFormat('en-US', { timeZone }).resolvedOptions()
                .timeZone ?? null
        );
    } catch {
        return null;
    }
}

/**
 * Compares two IANA zone identifiers while tolerating casing, underscores vs
 * spaces, UTC aliases, and zones that resolve to the same canonical name
 * (for example `Asia/Calcutta` and `Asia/Kolkata`).
 */
export function sameTimeZone(a: string, b: string): boolean {
    if (a.trim() === '' || b.trim() === '') {
        return false;
    }

    const normalizedA = normalizeTimeZone(a);
    const normalizedB = normalizeTimeZone(b);

    if (normalizedA === normalizedB) {
        return true;
    }

    if (UTC_ALIASES.has(normalizedA) && UTC_ALIASES.has(normalizedB)) {
        return true;
    }

    const resolvedA = resolvedTimeZone(a);
    const resolvedB = resolvedTimeZone(b);

    return resolvedA !== null && resolvedB !== null && resolvedA === resolvedB;
}
