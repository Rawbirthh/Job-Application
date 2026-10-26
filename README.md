# OpenCode Agentic Starter — Laravel + React + Inertia + TypeScript

A provider-neutral OpenCode setup inspired by the workflow in [jsmastery-pro/jsm-agent-skill](https://github.com/jsmastery-pro/jsm-agent-skill), adapted for Laravel, MySQL, React, Inertia, TypeScript, Tailwind, and shadcn/ui.

## What is included

- `opencode.json`: model/provider-neutral OpenCode configuration, context compaction, one-level subagent delegation, and approval prompts for risky shell actions.
- `AGENTS.md`: shared engineering laws and the mandatory implementation/testing gate.
- `.opencode/agents/`: backend, frontend, test, and read-only code-review specialists.
- `.opencode/commands/`: `/audit`, `/architect`, `/develop`, `/review`, `/test`, `/remember`, `/recover`, `/imprint`.
- `.opencode/skills/`: detailed domain guidance loaded on demand.
- `memory.md`: a persistent handoff summary, intentionally compatible with the JSM `/remember save` / `/remember restore` workflow.
- `docs/ui-registry.md`: optional visual-consistency record used by `/imprint`.

## Install into a new Laravel repository

1. Unzip this bundle.
2. Copy `opencode.json`, `AGENTS.md`, `memory.md`, the entire `.opencode/` directory, and `docs/ui-registry.md` into your Laravel project root.
3. If your project already has an `opencode.json`, `AGENTS.md`, `.opencode/`, or `memory.md`, back it up and merge the contents rather than blindly overwriting existing configuration or rules.
4. From the Laravel repository root, run `opencode debug config` and `opencode agent list`. Confirm `backend-engineer`, `frontend-engineer`, `test-engineer`, and `code-reviewer` appear. You can call one directly with `@backend-engineer` (or another name), but normal `/develop`, `/test`, `/recover`, and `/review` workflows should route to specialists when their scope applies. If names are missing, ensure the hidden `.opencode/` directory was copied completely, then restart OpenCode.
5. Run `/audit` first so the agent records the actual Laravel/PHP/Node versions, starter kit, current test runner, auth setup, custom role architecture, and repository conventions before feature work.

No provider or model is pinned; configure your preferred provider through OpenCode's usual authentication/configuration process. No API keys or secrets are included.

## Recommended working loop

1. `/architect <feature>` — inspect the codebase and requirements, write a feature spec/plan, identify routes, authorization, validation, data shape, migrations/indexes, tests, risks, and acceptance criteria. This command must stop and wait for your approval before changing application code.
2. Approve or amend the spec in the conversation.
3. `/develop <feature or approved spec>` — implement the approved feature, add/update tests, execute relevant checks, fix failures caused by the change, and inspect the final diff. Specialist routing is explicit: backend slices go to `backend-engineer`, frontend slices to `frontend-engineer`, meaningful/critical tests and bug regressions to `test-engineer`, and `/review` always invokes the read-only `code-reviewer`. The primary Build agent coordinates and verifies all returned work. For trivial tasks, irrelevant specialists are skipped.
4. `/review` — read-only independent review for plan alignment, security, performance, data integrity, UI states, and test coverage.
5. `/imprint` after notable UI components — capture existing layout, spacing, typography, tokens, and shadcn conventions in `docs/ui-registry.md`.
6. `/remember save` before starting a clean session. Start a new session and run `/remember restore` to restore the saved project/work handoff.
7. `/recover` when a change is going in circles or the root cause is unclear.

Use `/test` any time you want tests written or expanded for a named feature, changed files, or current diff.

## Testing framework recommendation

For a brand-new Laravel app, the default recommendation is Pest-style tests when compatible with the dependencies installed by the Laravel scaffold. Pest is built on PHPUnit; Laravel officially supports running either style through `php artisan test`. If the repository is already using PHPUnit, the agents follow that convention instead of migrating it automatically. Dependency installation/upgrades require your approval.

Most application confidence should come from feature tests that exercise HTTP requests, authorization, validation, persistence, and the main user journey. Use unit tests for isolated logic where they provide useful signal. Add a regression test for bug fixes and security tests whenever access boundaries or untrusted input change.

References: [Laravel 13 testing docs](https://laravel.com/framework/docs/13.x/testing), [Pest writing tests](https://pestphp.com/docs/writing-tests), [OpenCode agents](https://docs.opencode.ai/docs/agents/), [OpenCode commands](https://docs.opencode.ai/docs/commands/), [OpenCode skills](https://docs.opencode.ai/docs/skills/).

## Important behavior notes

- Slash commands are invoked by entering the command at the OpenCode prompt, such as `/architect Build a subscription module`. Mentioning `/architect` in ordinary prose is not guaranteed to invoke the slash command.
- `/remember save` writes a durable summary to the repository's `memory.md`; `/remember restore` reads it, checks relevant repository evidence, summarizes it, and pauses for your next instruction. It does not resurrect the full deleted transcript or hidden reasoning. Keep the file free of secrets and verify it before relying on it.
- The testing gate is enforced through shared instructions, the development/test commands, and review workflow. As with any model-driven setup, prompts alone are not a hard guarantee. For a true merge gate, also configure CI to fail when tests/static checks fail.
- `opencode.json` is a starter baseline. If the installed OpenCode version changes config schemas or your team needs tighter command-specific policies, verify the resolved config before relying on it.

## Initial optional commands

- `/audit` — document the current stack and architecture without changing application code.
- `/architect Add a billing page` — create a spec and stop for approval.
- `/develop Implement the approved billing spec` — implementation plus tests and verification.
- `/review` — inspect current changes without editing them.
- `/remember save` — save the key project/session context before switching sessions.
- `/remember restore` — restore and verify saved context at the start of a clean session.
