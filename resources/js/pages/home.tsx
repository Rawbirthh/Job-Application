import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ListChecks, ScanSearch, UserRound } from 'lucide-react';
import { FaqList, type FaqItem } from '@/components/public/faq-list';
import { HeroArt } from '@/components/public/hero-art';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

const whyCards = [
    {
        title: 'Clarity up front',
        description: 'Built to show the details that matter before you apply.',
        icon: ScanSearch,
    },
    {
        title: 'A profile with purpose',
        description:
            'Bring your experience, skills, and resume together in one place.',
        icon: UserRound,
    },
    {
        title: 'Progress you can see',
        description:
            'A clear view of every application, from first click to final decision.',
        icon: ListChecks,
    },
] as const;

const steps = [
    {
        title: 'Create your profile',
        description:
            'Add your experience, skills, and resume. Keep it ready for the roles that matter.',
    },
    {
        title: 'Find a better fit',
        description:
            'Explore roles with the context you need to make a confident decision.',
    },
    {
        title: 'Apply and keep track',
        description:
            'Follow every application and see where each conversation stands.',
    },
] as const;

const eyebrowClassName =
    'text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase';

const sectionHeadingClassName =
    'mt-3 text-[clamp(2rem,4vw,2.75rem)] leading-tight font-semibold tracking-[-0.03em]';

const heroInlineLinkClassName =
    'font-medium text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-foreground focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none';

export default function Home() {
    const { auth, name } = usePage().props;
    const isAuthenticated = Boolean(auth.user);

    const faqItems: FaqItem[] = [
        {
            question: `What is ${name}?`,
            answer: `${name} is a job portal for people looking for work and for employers who want to hire. You can create an account now; job search and employer tools are part of the first release.`,
        },
        {
            question: 'How do I get started?',
            answer: 'Create an account to get set up. Profile tools for your experience, skills, and resume are part of the first release.',
        },
        {
            question: 'Can I search for jobs today?',
            answer: 'Not yet. Job search, listings, and applications are coming in the first release. This page does not show live roles today.',
        },
        {
            question: 'I’m hiring. How do I post a role?',
            answer: 'Employer onboarding is coming soon. Create an account now and be ready to build your company profile and publish roles when it opens.',
        },
        {
            question: 'Where can I manage my account?',
            answer: 'Sign in and open your Dashboard. Account and security settings are available from the account menu there.',
        },
    ];

    return (
        <>
            <Head title="Find work that fits the life you want.">
                <meta
                    name="description"
                    content="A calmer place to discover roles, understand what they offer, and take the next step — without the noise."
                />
                <meta
                    property="og:title"
                    content="Find work that fits the life you want."
                />
                <meta
                    property="og:description"
                    content="A calmer place to discover roles, understand what they offer, and take the next step — without the noise."
                />
                <meta property="og:type" content="website" />
            </Head>

            <section className="relative overflow-hidden bg-home-surface">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(var(--border)_1px,transparent_1px)] [background-size:24px_24px] opacity-40"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-32 right-0 size-80 rounded-full bg-home-accent-soft/70 blur-3xl"
                />

                <div className="relative mx-auto grid max-w-6xl gap-14 px-5 pt-16 pb-20 sm:px-6 md:pt-24 md:pb-28 lg:grid-cols-12 lg:items-center lg:gap-10 lg:px-8">
                    <div className="lg:col-span-7">
                        <Badge
                            variant="outline"
                            className="rounded-full border-border bg-background/70 px-3 py-1 text-[11px] font-medium tracking-[0.18em] text-muted-foreground uppercase backdrop-blur-sm"
                        >
                            A clearer path to work
                        </Badge>

                        <h1 className="mt-6 text-[clamp(2.75rem,6vw,5rem)] leading-[0.98] font-semibold tracking-[-0.045em] text-balance">
                            Find work that fits the life you want.
                        </h1>

                        <p className="mt-6 max-w-xl text-base leading-7 text-muted-foreground sm:text-lg sm:leading-8">
                            A calmer place to discover roles, understand what
                            they offer, and take the next step — without the
                            noise.
                        </p>

                        <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Button
                                asChild
                                size="lg"
                                className="h-11 px-6 focus-visible:ring-2 focus-visible:ring-foreground"
                            >
                                <Link
                                    href={
                                        isAuthenticated
                                            ? dashboard()
                                            : register()
                                    }
                                >
                                    {isAuthenticated
                                        ? 'Open your dashboard'
                                        : 'Create your profile'}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="h-11 px-6 focus-visible:ring-2 focus-visible:ring-foreground"
                            >
                                <a href="#how-it-works">See how it works</a>
                            </Button>
                        </div>

                        <p className="mt-5 text-sm text-muted-foreground">
                            {isAuthenticated ? (
                                <>
                                    You’re signed in.{' '}
                                    <Link
                                        href={dashboard()}
                                        className={heroInlineLinkClassName}
                                    >
                                        Go to your dashboard
                                    </Link>
                                    .
                                </>
                            ) : (
                                <>
                                    Already have an account?{' '}
                                    <Link
                                        href={login()}
                                        className={heroInlineLinkClassName}
                                    >
                                        Log in
                                    </Link>
                                    .
                                </>
                            )}
                        </p>
                    </div>

                    <div className="lg:col-span-5">
                        <HeroArt />
                    </div>
                </div>
            </section>

            <section
                aria-labelledby="why-heading"
                className="bg-background py-20 md:py-28"
            >
                <div className="mx-auto max-w-6xl px-5 sm:px-6 lg:px-8">
                    <div className="max-w-2xl">
                        <p className={eyebrowClassName}>Why {name}</p>
                        <h2
                            id="why-heading"
                            className={sectionHeadingClassName}
                        >
                            Less searching. More deciding.
                        </h2>
                    </div>

                    <div className="mt-12 grid gap-4 md:grid-cols-3 md:gap-6">
                        {whyCards.map((card) => (
                            <article
                                key={card.title}
                                className="rounded-2xl border border-border bg-card p-6 sm:p-7"
                            >
                                <div className="flex size-11 items-center justify-center rounded-xl bg-home-accent-soft text-home-accent">
                                    <card.icon
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <h3 className="mt-5 text-lg font-semibold tracking-[-0.01em]">
                                    {card.title}
                                </h3>
                                <p className="mt-2 text-sm leading-7 text-muted-foreground">
                                    {card.description}
                                </p>
                            </article>
                        ))}
                    </div>
                </div>
            </section>

            <section
                id="how-it-works"
                aria-labelledby="how-it-works-heading"
                className="scroll-mt-24 bg-home-surface py-20 md:py-28"
            >
                <div className="mx-auto max-w-6xl px-5 sm:px-6 lg:px-8">
                    <div className="max-w-2xl">
                        <p className={eyebrowClassName}>How it works</p>
                        <h2
                            id="how-it-works-heading"
                            className={sectionHeadingClassName}
                        >
                            A simpler path from looking to landing.
                        </h2>
                    </div>

                    <ol className="mt-12 grid gap-8 md:grid-cols-3 md:gap-6">
                        {steps.map((step, index) => (
                            <li
                                key={step.title}
                                className="relative before:absolute before:top-12 before:-bottom-8 before:left-5 before:w-px before:bg-border last:before:hidden md:before:hidden"
                            >
                                <div className="flex items-center gap-4">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full border border-home-accent/40 bg-home-accent-soft text-sm font-semibold text-foreground">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    {index < steps.length - 1 && (
                                        <span
                                            aria-hidden="true"
                                            className="hidden h-px flex-1 bg-border md:block"
                                        />
                                    )}
                                </div>
                                <h3 className="mt-5 text-lg font-semibold tracking-[-0.01em]">
                                    {step.title}
                                </h3>
                                <p className="mt-2 max-w-sm text-sm leading-7 text-muted-foreground md:max-w-none">
                                    {step.description}
                                </p>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            <section
                id="for-employers"
                aria-labelledby="for-employers-heading"
                className="scroll-mt-24 py-20 md:py-28"
            >
                <div className="mx-auto max-w-6xl px-5 sm:px-6 lg:px-8">
                    <div className="relative overflow-hidden rounded-3xl bg-sidebar px-6 py-12 text-sidebar-foreground sm:px-10 md:px-12 md:py-16">
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute -top-24 -right-16 size-72 rounded-full bg-home-accent/10 blur-3xl"
                        />
                        <div className="relative grid gap-8 lg:grid-cols-2 lg:items-center lg:gap-12">
                            <div>
                                <p className="text-xs font-medium tracking-[0.18em] text-sidebar-foreground/70 uppercase">
                                    For employers
                                </p>
                                <h2
                                    id="for-employers-heading"
                                    className="mt-3 text-[clamp(2rem,4vw,2.75rem)] leading-tight font-semibold tracking-[-0.03em] text-balance"
                                >
                                    Meet people who are ready to do the work.
                                </h2>
                            </div>

                            <div>
                                <p className="text-base leading-7 text-sidebar-foreground/80">
                                    {isAuthenticated
                                        ? 'Employer onboarding is coming soon. When it opens, you’ll be able to build your company profile, publish roles, and manage applicants.'
                                        : 'Employer onboarding is coming soon. Create an account now and be ready to build your company profile, publish roles, and manage applicants.'}
                                </p>
                                <Button
                                    asChild
                                    size="lg"
                                    className="mt-6 h-11 bg-sidebar-foreground px-6 text-sidebar hover:bg-sidebar-foreground/90 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-sidebar focus-visible:outline-none"
                                >
                                    <Link
                                        href={
                                            isAuthenticated
                                                ? dashboard()
                                                : register()
                                        }
                                    >
                                        {isAuthenticated
                                            ? 'Open your dashboard'
                                            : 'Create an account'}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="faq"
                aria-labelledby="faq-heading"
                className="scroll-mt-24 bg-home-surface py-20 md:py-28"
            >
                <div className="mx-auto max-w-6xl px-5 sm:px-6 lg:px-8">
                    <div className="grid gap-10 lg:grid-cols-12 lg:gap-12">
                        <div className="lg:col-span-4">
                            <p className={eyebrowClassName}>FAQ</p>
                            <h2
                                id="faq-heading"
                                className={sectionHeadingClassName}
                            >
                                Questions, answered.
                            </h2>
                        </div>

                        <div className="lg:col-span-8">
                            <FaqList items={faqItems} />
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}
