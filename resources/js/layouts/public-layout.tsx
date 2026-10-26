import type { ReactNode } from 'react';
import { SiteFooter } from '@/components/public/site-footer';
import { SiteHeader } from '@/components/public/site-header';

type PublicLayoutProps = {
    children: ReactNode;
};

export default function PublicLayout({ children }: PublicLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col bg-background">
            <a
                href="#main-content"
                className="absolute top-3 left-3 z-50 -translate-y-24 rounded-md bg-primary px-4 py-3 text-sm font-medium text-primary-foreground opacity-0 shadow-lg focus:translate-y-0 focus:opacity-100 focus:ring-2 focus:ring-foreground focus:outline-none"
            >
                Skip to content
            </a>
            <SiteHeader />
            <main
                id="main-content"
                tabIndex={-1}
                className="flex-1 focus:outline-none"
            >
                {children}
            </main>
            <SiteFooter />
        </div>
    );
}
