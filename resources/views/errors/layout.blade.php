{{--
    Shared shell for all HTTP error pages.

    Intentionally self-contained: no @vite, no JS, no Inertia. Error pages must
    still render when the asset pipeline is unavailable (fresh clone, broken
    build, or a 500 caused by the build itself). Colors mirror the semantic
    tokens in resources/css/app.css and docs/ui-registry.md.
--}}
@php
    $code = (string) ($code ?? '500');
    $title = (string) ($title ?? 'Something went wrong');
    $message = (string) ($message ?? 'Please try again in a moment.');
    $icon = (string) ($icon ?? 'alert');

    $iconPaths = [
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m10.85 12.15 8.15-8.15"/><path d="m17 3 4 4"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 15v2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/><path d="M8.5 11h5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'gauge' => '<path d="M3.5 18a9 9 0 1 1 17 0"/><path d="m12 14 4-4"/><circle cx="12" cy="14" r="1"/>',
        'alert' => '<path d="M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'tool' => '<path d="M14.5 6.5a4 4 0 0 0 5.2 5.2L21 8.9a4 4 0 0 1-5.1-5.1l1.6-1.6-4.6 4.6a4 4 0 0 0 1.6 1.7Z"/><path d="m3 21 6-6"/>',
    ];

    $iconPath = $iconPaths[$icon] ?? $iconPaths['alert'];

    $requestId = request()->attributes->get('request_id');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $code }} · {{ $title }} — NextStep</title>

    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" href="/favicon.svg?v=2" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2" sizes="180x180">

    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --bg-accent: #e7f7f4;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #0d9488;
            --primary-soft: #ccfbf1;
            --primary-ink: #021716;
            --ring: #0d9488;
            --shadow: 0 30px 60px -30px rgba(15, 23, 42, .35);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                color-scheme: dark;
                --bg: #0b1220;
                --bg-accent: #10332f;
                --surface: #0f1a2a;
                --border: #1e293b;
                --text: #e2e8f0;
                --muted: #94a3b8;
                --primary: #2dd4bf;
                --primary-soft: #134e4a;
                --primary-ink: #042f2e;
                --ring: #2dd4bf;
                --shadow: 0 30px 60px -30px rgba(0, 0, 0, .65);
            }
        }

        * { box-sizing: border-box; }

        html, body { margin: 0; min-height: 100%; }

        body {
            min-height: 100svh;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--text);
            background:
                radial-gradient(55rem 35rem at 85% -10%, var(--bg-accent), transparent 65%),
                var(--bg);
            -webkit-font-smoothing: antialiased;
        }

        .page {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.5rem clamp(1rem, 4vw, 2.5rem);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: .625rem;
            color: var(--text);
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: -.01em;
        }

        .brand img {
            width: 2rem;
            height: 2rem;
            border-radius: .5rem;
        }

        .brand:focus-visible,
        .footer a:focus-visible {
            outline: 2px solid var(--ring);
            outline-offset: 3px;
            border-radius: .25rem;
        }

        .hint {
            font-size: .8125rem;
            color: var(--muted);
        }

        .main {
            flex: 1;
            display: grid;
            place-items: center;
            padding: 1rem 1rem 4rem;
        }

        .card {
            position: relative;
            width: min(100%, 30rem);
            overflow: hidden;
            padding: clamp(2rem, 5vw, 3rem);
            text-align: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            box-shadow: var(--shadow);
        }

        .card::before {
            content: '';
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
        }

        .badge {
            display: inline-grid;
            place-items: center;
            width: 3.25rem;
            height: 3.25rem;
            margin-bottom: 1.25rem;
            border-radius: 1rem;
            background: var(--primary-soft);
            color: var(--primary-ink);
        }

        .badge svg {
            width: 1.75rem;
            height: 1.75rem;
        }

        .code {
            margin: 0;
            font-size: clamp(4.5rem, 14vw, 7rem);
            line-height: .95;
            font-weight: 600;
            letter-spacing: -.07em;
            color: var(--text);
        }

        @supports ((-webkit-background-clip: text) or (background-clip: text)) {
            .code {
                background: linear-gradient(135deg, var(--primary), #14b8a6 60%, #5eead4);
                -webkit-background-clip: text;
                background-clip: text;
                color: transparent;
            }
        }

        .eyebrow {
            margin: 1rem 0 .5rem;
            font-size: .6875rem;
            font-weight: 600;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--primary);
        }

        h1 {
            margin: 0;
            font-size: 1.375rem;
            font-weight: 600;
            letter-spacing: -.02em;
        }

        .message {
            max-width: 24rem;
            margin: .75rem auto 1.75rem;
            font-size: .9375rem;
            line-height: 1.65;
            color: var(--muted);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .6875rem 1.125rem;
            border: 1px solid var(--primary);
            border-radius: .75rem;
            background: var(--primary);
            color: var(--primary-ink);
            box-shadow: 0 8px 20px -10px var(--primary);
            font: inherit;
            font-size: .875rem;
            font-weight: 600;
            line-height: 1.25rem;
            text-decoration: none;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 26px -10px var(--primary);
        }

        .btn:focus-visible {
            outline: 2px solid var(--ring);
            outline-offset: 2px;
        }

        .reference {
            margin: 1.5rem 0 0;
            font-size: .75rem;
            color: var(--muted);
        }

        .reference code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            padding: .125rem .375rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: .375rem;
        }

        .footer {
            padding: 0 1rem 1.75rem;
            text-align: center;
            font-size: .75rem;
            color: var(--muted);
        }

        .footer a { color: inherit; }

        @media (prefers-reduced-motion: reduce) {
            .btn { transition: none; }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="topbar">
            <a class="brand" href="/" aria-label="NextStep home">
                <img src="/images/nextstep-mark.png" alt="" width="32" height="32">
                <span>NextStep</span>
            </a>
            <span class="hint">Your next opportunity starts here</span>
        </header>

        <main class="main" id="main-content">
            <section class="card" aria-labelledby="error-title">
                <span class="badge" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $iconPath !!}</svg>
                </span>

                <p class="eyebrow">Error {{ $code }}</p>
                <p class="code" aria-hidden="true">{{ $code }}</p>
                <h1 id="error-title">{{ $title }}</h1>
                <p class="message">{{ $message }}</p>

                <div class="actions">
                    <button class="btn" type="button" onclick="if (history.length > 1) { history.back(); } else { window.location.href = '/'; }">Go back</button>
                </div>

                @if ($code === '500' && $requestId)
                    <p class="reference">Reference ID: <code>{{ $requestId }}</code></p>
                @endif
            </section>
        </main>

        <footer class="footer">
            Need a hand? <a href="/jobs">Explore open roles</a> or return to the <a href="/">homepage</a>.
        </footer>
    </div>
</body>
</html>
