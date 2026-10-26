import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, home, login, register } from '@/routes';

const exploreLinks = [
    { label: 'How it works', href: '#how-it-works' },
    { label: 'For employers', href: '#for-employers' },
    { label: 'FAQ', href: '#faq' },
] as const;

const footerLinkClassName =
    'inline-flex min-h-11 items-center rounded-md text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none';

export function SiteFooter() {
    const { auth, name } = usePage().props;
    const isAuthenticated = Boolean(auth.user);
    const currentYear = new Date().getFullYear();

    return (
        <footer className="border-t border-border bg-home-surface">
            <div className="mx-auto max-w-6xl px-5 py-14 sm:px-6 lg:px-8">
                <div className="grid gap-10 md:grid-cols-[minmax(0,1.5fr)_1fr_1fr]">
                    <div>
                        <Link
                            href={home()}
                            className="inline-flex min-h-11 items-center gap-2 rounded-lg text-foreground focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none"
                        >
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-foreground text-background shadow-sm">
                                <AppLogoIcon
                                    aria-hidden="true"
                                    className="size-5 fill-current"
                                />
                            </span>
                            <span className="text-base font-semibold tracking-[-0.02em]">
                                {name}
                            </span>
                        </Link>
                        <p className="mt-4 max-w-xs text-sm leading-6 text-muted-foreground">
                            A clearer path to work.
                        </p>
                    </div>

                    <div>
                        <h2 className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                            Explore
                        </h2>
                        <ul className="mt-3 flex flex-col">
                            {exploreLinks.map((link) => (
                                <li key={link.href}>
                                    <a
                                        href={link.href}
                                        className={footerLinkClassName}
                                    >
                                        {link.label}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div>
                        <h2 className="text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase">
                            Account
                        </h2>
                        <ul className="mt-3 flex flex-col">
                            {isAuthenticated ? (
                                <li>
                                    <Link
                                        href={dashboard()}
                                        className={footerLinkClassName}
                                    >
                                        Dashboard
                                    </Link>
                                </li>
                            ) : (
                                <>
                                    <li>
                                        <Link
                                            href={login()}
                                            className={footerLinkClassName}
                                        >
                                            Log in
                                        </Link>
                                    </li>
                                    <li>
                                        <Link
                                            href={register()}
                                            className={footerLinkClassName}
                                        >
                                            Create account
                                        </Link>
                                    </li>
                                </>
                            )}
                        </ul>
                    </div>
                </div>

                <div className="mt-12 border-t border-border pt-6">
                    <p className="text-sm text-muted-foreground">
                        &copy; {currentYear} {name}.
                    </p>
                </div>
            </div>
        </footer>
    );
}
