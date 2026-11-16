import type { PipelineStatus } from '@/types/pipeline';

/**
 * Application stage colors.
 *
 * The values intentionally mirror the candidate application history
 * (`resources/js/pages/candidate/applications/index.tsx`) so one hiring stage
 * reads as one color everywhere in the product. Colors are reserved for small
 * accents - the column marker, the count, menu markers and progress bars -
 * because the design registry keeps large surfaces neutral.
 */
export const PIPELINE_STATUS_DOTS: Record<PipelineStatus, string> = {
    submitted: 'bg-sky-500',
    reviewing: 'bg-blue-500',
    shortlisted: 'bg-teal-500',
    interview_scheduled: 'bg-violet-500',
    offer_sent: 'bg-amber-500',
    hired: 'bg-emerald-500',
    rejected: 'bg-red-500',
    withdrawn: 'bg-muted-foreground',
};

/**
 * Soft badge colors for a stage, used for the "drop here" target marker.
 */
export const PIPELINE_STATUS_STYLES: Record<PipelineStatus, string> = {
    submitted: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-400',
    reviewing:
        'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400',
    shortlisted:
        'border-teal-500/40 bg-teal-500/10 text-teal-700 dark:text-teal-400',
    interview_scheduled:
        'border-violet-500/40 bg-violet-500/10 text-violet-700 dark:text-violet-400',
    offer_sent:
        'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    hired: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    rejected: 'border-red-500/40 bg-red-500/10 text-red-700 dark:text-red-400',
    withdrawn: 'border-border bg-muted text-muted-foreground',
};
