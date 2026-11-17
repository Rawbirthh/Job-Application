import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, LoaderCircle, MailCheck } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const verificationLinkSent = status === 'verification-link-sent';

    return (
        <>
            <Head title="Verify email" />

            {verificationLinkSent && (
                <div
                    role="status"
                    aria-live="polite"
                    className="mb-4 inline-flex w-full items-center justify-center gap-2 rounded-md border border-emerald-500/30 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                >
                    <CheckCircle2
                        className="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    Verification link sent — check your inbox.
                </div>
            )}

            <div className="space-y-6 text-center">
                <div className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-home-accent-soft text-home-accent">
                    <MailCheck className="size-7" aria-hidden="true" />
                </div>

                <p className="text-sm leading-7 text-muted-foreground">
                    Thanks for signing up. Before you continue, please confirm
                    your email address by clicking the link we just sent you. If
                    you didn&apos;t receive it, we can send another.
                </p>

                <Form {...send.form()}>
                    {({ processing }) => (
                        <Button
                            className="w-full"
                            disabled={processing}
                            data-test="resend-verification-button"
                        >
                            {processing && (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            )}
                            Resend verification email
                        </Button>
                    )}
                </Form>

                <div className="text-sm text-muted-foreground">
                    <TextLink href={dashboard()}>Back to dashboard</TextLink>
                </div>
            </div>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verify your email',
    description: 'We sent a verification link to your email address',
};
