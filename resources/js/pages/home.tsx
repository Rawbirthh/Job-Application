import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    History,
    LayoutDashboard,
    ListChecks,
    MessagesSquare,
    ScanSearch,
    ScrollText,
    ShieldCheck,
} from 'lucide-react';
import { FaqList, type FaqItem } from '@/components/public/faq-list';
import { HeroArt } from '@/components/public/hero-art';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

type HomeStats = {
    openRoles: number;
    hiringCompanies: number;
};

const whyCards = [
    {
        title: 'Progress you can see',
        description:
            'Every application is tracked through clear stages, from first click to final decision — with a full history you can look back on.',
        icon: ListChecks,
    },
    {
        title: 'Conversations, not silence',
        description:
            'Message the employer right inside each application — in real time when they are online, and always there when you come back.',
        icon: MessagesSquare,
    },
    {
        title: 'A profile with purpose',
        description:
            'Bring your experience, skills, and resume together in one place — used to surface roles that actually fit you.',
        icon: ScanSearch,
    },
] as const;

const candidateSteps = [
    {
        title: 'Create your profile',
        description:
            'Add your experience, skills, and resume. Keep it ready for the roles that matter.',
    },
    {
        title: 'Find a better fit',
        description:
            'Browse open roles and filter by keyword, location, category, and workplace setup — salary is shown when the employer discloses it.',
    },
    {
        title: 'Apply and keep track',
        description:
            'Follow every stage in real time, get notified when things move, and message the employer inside the application.',
    },
] as const;

const employerSteps = [
    {
        title: 'Set up your company',
        description:
            'Create your company profile, invite your team, and assign roles and permissions that fit how you hire.',
    },
    {
        title: 'Publish your roles',
        description:
            'Define category, experience level, salary, and screening questions — and start receiving qualified applicants.',
    },
    {
        title: 'Run your pipeline',
        description:
            'Review applicants on a visual board, schedule interviews, keep private notes and scorecards, and decide with confidence.',
    },
] as const;

const engineeringHighlights = [
    {
        title: 'Real-time messaging',
        description:
            'Every application carries a persistent conversation, delivered live over Laravel Reverb and kept in sync when you were offline.',
        icon: MessagesSquare,
    },
    {
        title: 'Visual recruitment pipeline',
        description:
            'Employers move applicants through each hiring stage on a drag-and-drop board — with keyboard control and one shared status engine.',
        icon: LayoutDashboard,
    },
    {
        title: 'Rule-based recommendations',
        description:
            'Roles are scored against skills, experience level, and preferences — transparent rules, no black-box magic.',
        icon: ScanSearch,
    },
    {
        title: 'Custom role-based access',
        description:
            'A database-backed role and permission system for candidates, employers, and platform admins — enforced on the server, every time.',
        icon: ShieldCheck,
    },
    {
        title: 'Full application history',
        description:
            'Every status change is recorded with who, when, and why — with private, author-only recruiter notes and evaluations.',
        icon: History,
    },
    {
        title: 'Audit-logged administration',
        description:
            'An append-only audit log and moderation tools for the platform team — content leaves the site through review, never silently.',
        icon: ScrollText,
    },
] as const;

const employerCapabilities = [
    'Company profile with team members and per-person permissions',
    'Job postings with categories, experience levels, salary, and screening questions',
    'Applicant pipeline with interview scheduling built in',
    'Private recruiter notes and structured evaluations',
    'Hiring analytics and real-time notifications',
] as const;

const techStack = [
    'Laravel 13',
    'React 19',
    'Inertia 3',
    'TypeScript',
    'Tailwind CSS 4',
    'MySQL',
    'Laravel Reverb',
] as const;

const eyebrowClassName =
    'text-xs font-medium tracking-[0.18em] text-muted-foreground uppercase';

const sectionHeadingClassName =
    'mt-3 text-[clamp(2rem,4vw,2.75rem)] leading-tight font-semibold tracking-[-0.03em]';

const heroInlineLinkClassName =
    'font-medium text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-foreground focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-foreground focus-visible:outline-none';

export default function Home() {
    const { auth, name, stats } = usePage().props;
    const isAuthenticated = Boolean(auth.user);
    const homeStats = stats as HomeStats | undefined;

    const faqItems: FaqItem[] = [
        {
            question: `What is ${name}?`,
            answer: `${name} is a job portal and recruitment platform with three audiences: candidates who want to find and follow the right roles, employers who want to hire well, and a platform team that keeps the marketplace fair and safe.`,
        },
        {
            question: 'Can I search for jobs today?',
            answer: 'Yes. Browse open roles on the jobs page and filter by keyword, location, category, employment type, and workplace setup — salary is shown when the employer discloses it. You can also save searches and get alerted about new matches.',
        },
        {
            question: 'How do I get started as a candidate?',
            answer: 'Create an account, complete your profile — experience, skills, and an uploaded resume — then start applying. Your dashboard keeps every application, interview, and message in one place.',
        },
        {
            question: 'I’m hiring. How do I post a role?',
            answer: 'Create an account, set up your company profile, and publish roles with categories, experience levels, salary, and screening questions. Applications flow straight into your hiring pipeline with interview scheduling built in.',
        },
        {
            question: 'What happens after I apply?',
            answer: 'You will see every status change in real time, receive notifications as things move, and can message the employer directly inside the application — all in one thread.',
        },
        {
            question: 'What can employers see about me?',
            answer: 'Once you apply, the employer sees the application and the resume version you submitted with it. Recruiter notes and evaluations are private to the hiring team and are never shown to candidates.',
        },
        {
            question: 'Where can I manage my account?',
            answer: 'Sign in and open your Dashboard. Profile, security, two-factor authentication, and passkeys live in the account settings menu.',
        },
    ];

    return (
        <>
            <Head title="Find work that fits the life you want.">
                <meta
                    name="description"
                    content="Browse open roles, apply with a profile that shows your skills, and follow every application in real time — a calmer, clearer way to get hired and to hire."
                />
                <meta
                    property="og:title"
                    content="Find work that fits the life you want."
                />
                <meta
                    property="og:description"
                    content="Browse open roles, apply with a profile that shows your skills, and follow every application in real time — a calmer, clearer way to get hired and to hire."
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
                            Browse open roles, apply with a profile that shows
                            your skills, and follow every application from first
                            click to final decision — and hire well if you are
                            on the other side of the table.
                        </p>

                        <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Button
                                asChild
                                size="lg"
                                className="h-11 px-6 focus-visible:ring-2 focus-visible:ring-foreground"
                            >
                                <Link href="/jobs">
                                    Browse jobs
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
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="lg"
                                variant="ghost"
                                className="h-11 px-6 focus-visible:ring-2 focus-visible:ring-foreground"
                            >
                                <a href="#how-it-works">See how it works</a>
                            </Button>
                        </div>

                        {homeStats && homeStats.openRoles > 0 && (
                            <p className="mt-6 inline-flex items-center gap-2 rounded-full border border-border bg-background/70 px-4 py-2 text-sm text-muted-foreground backdrop-blur-sm">
                                <span
                                    aria-hidden="true"
                                    className="size-2 rounded-full bg-emerald-500"
                                />
                                {homeStats.openRoles.toLocaleString()} open
                                roles across{' '}
                                {homeStats.hiringCompanies.toLocaleString()}{' '}
                                companies — live right now
                            </p>
                        )}

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
                            From looking to landing — and from posting to hired.
                        </h2>
                    </div>

                    <div className="mt-12 grid gap-10 md:grid-cols-2 md:gap-12">
                        <div>
                            <p className={eyebrowClassName}>For candidates</p>
                            <ol className="mt-5 space-y-6">
                                {candidateSteps.map((step, index) => (
                                    <li
                                        key={step.title}
                                        className="flex items-start gap-4"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-full border border-home-accent/40 bg-home-accent-soft text-sm font-semibold text-foreground">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        <span>
                                            <h3 className="text-lg font-semibold tracking-[-0.01em]">
                                                {step.title}
                                            </h3>
                                            <p className="mt-1.5 text-sm leading-7 text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </div>

                        <div>
                            <p className={eyebrowClassName}>For employers</p>
                            <ol className="mt-5 space-y-6">
                                {employerSteps.map((step, index) => (
                                    <li
                                        key={step.title}
                                        className="flex items-start gap-4"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-full border border-home-accent/40 bg-home-accent-soft text-sm font-semibold text-foreground">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        <span>
                                            <h3 className="text-lg font-semibold tracking-[-0.01em]">
                                                {step.title}
                                            </h3>
                                            <p className="mt-1.5 text-sm leading-7 text-muted-foreground">
                                                {step.description}
                                            </p>
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="engineering"
                aria-labelledby="engineering-heading"
                className="scroll-mt-24 bg-background py-20 md:py-28"
            >
                <div className="mx-auto max-w-6xl px-5 sm:px-6 lg:px-8">
                    <div className="max-w-2xl">
                        <p className={eyebrowClassName}>
                            Engineering highlights
                        </p>
                        <h2
                            id="engineering-heading"
                            className={sectionHeadingClassName}
                        >
                            Built like a real product, because it is.
                        </h2>
                        <p className="mt-4 text-base leading-7 text-muted-foreground">
                            A quick look under the hood — every capability above
                            runs on systems designed to hold up under real use.
                        </p>
                    </div>

                    <div className="mt-12 grid gap-4 sm:grid-cols-2 md:gap-6 lg:grid-cols-3">
                        {engineeringHighlights.map((item) => (
                            <article
                                key={item.title}
                                className="rounded-2xl border border-border bg-card p-6"
                            >
                                <div className="flex size-11 items-center justify-center rounded-xl bg-home-accent-soft text-home-accent">
                                    <item.icon
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <h3 className="mt-5 text-base font-semibold tracking-[-0.01em]">
                                    {item.title}
                                </h3>
                                <p className="mt-2 text-sm leading-7 text-muted-foreground">
                                    {item.description}
                                </p>
                            </article>
                        ))}
                    </div>

                    <p className="mt-8 rounded-2xl border border-border bg-home-surface px-6 py-4 text-center text-sm text-muted-foreground">
                        {techStack.join('  ·  ')}
                    </p>
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
                        <div className="relative grid gap-10 lg:grid-cols-2 lg:gap-12">
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
                                <p className="mt-4 text-base leading-7 text-sidebar-foreground/80">
                                    Everything you need to go from open role to
                                    signed offer — without juggling spreadsheets
                                    and inbox threads.
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
                                            : 'Create your company profile'}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                            </div>

                            <ul className="flex flex-col justify-center gap-4">
                                {employerCapabilities.map((capability) => (
                                    <li
                                        key={capability}
                                        className="flex items-start gap-3 text-sm leading-7 text-sidebar-foreground/90"
                                    >
                                        <Check
                                            className="mt-1 size-4 shrink-0 text-home-accent-soft"
                                            aria-hidden="true"
                                        />
                                        {capability}
                                    </li>
                                ))}
                            </ul>
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
