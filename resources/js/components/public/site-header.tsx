import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { dashboard, home, login, register } from '@/routes';

const navItems = [
    { label: 'How it works', href: '#how-it-works' },
    { label: 'For employers', href: '#for-employers' },
    { label: 'FAQ', href: '#faq' },
] as const;

type AuthActionsProps = {
    isAuthenticated: boolean;
    fullWidth?: boolean;
    onNavigate?: () => void;
};

const focusRingClassName =
    'focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none';

function AuthActions({
    isAuthenticated,
    fullWidth = false,
    onNavigate,
}: AuthActionsProps) {
    if (isAuthenticated) {
        return (
            <Button
                asChild
                className={cn(focusRingClassName, fullWidth && 'h-11 w-full')}
            >
                <Link href={dashboard()} onClick={onNavigate}>
                    Dashboard
                </Link>
            </Button>
        );
    }

    return (
        <>
            <Button
                asChild
                variant="ghost"
                className={cn(focusRingClassName, fullWidth && 'h-11 w-full')}
            >
                <Link href={login()} onClick={onNavigate}>
                    Log in
                </Link>
            </Button>
            <Button
                asChild
                className={cn(focusRingClassName, fullWidth && 'h-11 w-full')}
            >
                <Link href={register()} onClick={onNavigate}>
                    Create account
                </Link>
            </Button>
        </>
    );
}

export function SiteHeader() {
    const { auth, name } = usePage().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const isAuthenticated = Boolean(auth.user);

    const closeMobileNavigation = () => setMobileOpen(false);

    return (
        <header className="sticky top-0 z-40 border-b border-border/80 bg-background/85 backdrop-blur-md supports-[backdrop-filter]:bg-background/75">
            <div className="mx-auto flex h-[72px] max-w-6xl items-center gap-2 px-5 sm:px-6 lg:px-8">
                <Link
                    href={home()}
                    className="flex min-h-11 items-center gap-2 rounded-lg pr-2 text-foreground focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none"
                >
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-foreground text-background shadow-sm">
                        <AppLogoIcon
                            aria-hidden="true"
                            className="size-5 fill-current"
                        />
                    </span>
                    <span className="max-w-[10rem] truncate text-base font-semibold tracking-[-0.02em] sm:max-w-none">
                        {name}
                    </span>
                </Link>

                <nav
                    aria-label="Primary"
                    className="ml-6 hidden items-center gap-1 lg:flex"
                >
                    {navItems.map((item) => (
                        <a
                            key={item.href}
                            href={item.href}
                            className="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-home-surface hover:text-foreground focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none"
                        >
                            {item.label}
                        </a>
                    ))}
                </nav>

                <div className="ml-auto hidden items-center gap-2 lg:flex">
                    <AuthActions isAuthenticated={isAuthenticated} />
                </div>

                <div className="ml-auto lg:hidden">
                    <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className={cn('size-11', focusRingClassName)}
                                aria-label="Open navigation menu"
                            >
                                <Menu className="size-5" aria-hidden="true" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            className="w-[min(22rem,88vw)] max-w-sm gap-0 p-0 [&>button]:top-3 [&>button]:right-3 [&>button]:flex [&>button]:size-11 [&>button]:items-center [&>button]:justify-center [&>button]:rounded-md [&>button]:focus:ring-foreground"
                        >
                            <SheetHeader className="border-b border-border px-5 py-4 pr-14 text-left">
                                <div className="flex items-center gap-2 pb-1">
                                    <span className="flex size-8 items-center justify-center rounded-lg bg-foreground text-background">
                                        <AppLogoIcon
                                            aria-hidden="true"
                                            className="size-4 fill-current"
                                        />
                                    </span>
                                    <span className="text-sm font-semibold">
                                        {name}
                                    </span>
                                </div>
                                <SheetTitle className="text-base">
                                    Menu
                                </SheetTitle>
                                <SheetDescription>
                                    Navigate {name} and manage your account.
                                </SheetDescription>
                            </SheetHeader>

                            <div className="flex flex-1 flex-col overflow-y-auto px-3 py-4">
                                <nav
                                    aria-label="Mobile"
                                    className="flex flex-col gap-1"
                                >
                                    {navItems.map((item) => (
                                        <a
                                            key={item.href}
                                            href={item.href}
                                            onClick={closeMobileNavigation}
                                            className="flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-foreground transition-colors hover:bg-home-surface focus-visible:bg-home-surface focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none"
                                        >
                                            {item.label}
                                        </a>
                                    ))}
                                </nav>

                                <div className="mt-auto flex flex-col gap-2 border-t border-border px-2 pt-4">
                                    <AuthActions
                                        isAuthenticated={isAuthenticated}
                                        fullWidth
                                        onNavigate={closeMobileNavigation}
                                    />
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
