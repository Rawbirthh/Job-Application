import { Bookmark, CircleUserRound } from 'lucide-react';

const highlights = ['Clear expectations', 'Room to grow', 'Life outside work'];

export function HeroArt() {
    return (
        <div
            aria-hidden="true"
            className="relative mx-auto w-full max-w-md select-none"
        >
            <div className="relative min-h-[27rem] overflow-hidden rounded-[2rem] border border-border bg-home-surface-strong p-5 sm:min-h-[30rem] sm:p-7">
                <div className="absolute inset-0 bg-[radial-gradient(var(--border)_1px,transparent_1px)] [background-size:18px_18px] opacity-60" />
                <div className="absolute -top-20 -right-16 size-56 rounded-full bg-home-accent-soft blur-3xl" />
                <div className="absolute -bottom-24 -left-16 size-56 rounded-full bg-home-accent-soft opacity-70 blur-3xl" />

                <div className="relative flex min-h-[24rem] items-center justify-center sm:min-h-[26rem]">
                    <div className="absolute top-[12%] left-[12%] h-44 w-[76%] rotate-3 rounded-2xl border border-border/70 bg-background/60 motion-reduce:rotate-0" />

                    <div className="relative w-[86%] -rotate-1 rounded-2xl border border-border bg-background p-5 shadow-lg motion-reduce:rotate-0 sm:p-6">
                        <p className="text-[10px] font-medium tracking-[0.18em] text-muted-foreground uppercase">
                            Your next move
                        </p>
                        <p className="mt-3 text-[1.35rem] leading-[1.15] font-semibold tracking-[-0.03em] sm:text-2xl">
                            Work, with room for life.
                        </p>

                        <ul className="mt-5 flex flex-wrap gap-2">
                            {highlights.map((highlight) => (
                                <li
                                    key={highlight}
                                    className="rounded-full border border-border bg-home-surface px-3 py-1.5 text-xs font-medium text-muted-foreground"
                                >
                                    {highlight}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="absolute top-0 left-0 w-32 rounded-xl border border-border bg-background p-3 shadow-md sm:w-36">
                        <div className="flex items-center gap-2">
                            <CircleUserRound className="size-4 text-home-accent" />
                            <p className="text-[10px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                Profile
                            </p>
                        </div>
                        <p className="mt-2 text-xs font-semibold">
                            Profile in progress
                        </p>
                    </div>

                    <div className="absolute right-0 bottom-0 w-32 rounded-xl border border-border bg-background p-3 shadow-md sm:w-36">
                        <div className="flex items-center gap-2">
                            <Bookmark className="size-4 text-home-accent" />
                            <p className="text-[10px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                Saved
                            </p>
                        </div>
                        <p className="mt-2 text-xs font-semibold">
                            Saved for later
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
