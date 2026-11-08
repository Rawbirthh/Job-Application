import AlertError from '@/components/alert-error';

type Props = {
    errors: Array<string | undefined>;
    title: string;
};

/**
 * Renders non-field Inertia errors (for example stale concurrency conflicts or
 * blocked state transitions) that do not have a dedicated input.
 */
export function GlobalFormErrors({ errors, title }: Props) {
    const messages = errors.filter(
        (message): message is string =>
            typeof message === 'string' && message.length > 0,
    );

    if (messages.length === 0) {
        return null;
    }

    return <AlertError errors={messages} title={title} />;
}
