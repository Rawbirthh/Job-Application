---
name: project-memory
description: Save and restore durable project context and current-work handoff across OpenCode sessions using memory.md
compatibility: opencode
---

Use one root `memory.md`, following the JSM-inspired workflow. Keep it concise and factual. Maintain stable project facts and the latest task handoff in one file rather than creating a separate file per chat.

On save, read the existing memory first; inspect current conversation, `git status`, relevant diff/specs, and actual test evidence. Preserve confirmed durable decisions, update current task state, note files changed and actual tests/checks run, record blockers and the next step, and remove stale information. Do not copy the full transcript or every tool output.

On restore, read the saved file, then verify claims using the current working tree, referenced files, and relevant test/config files. Mark stale or contradicted notes. Summarize context and wait for the user's next action. Do not automatically continue prior work.

Never store hidden reasoning, passwords, access tokens, `.env` contents, private keys, personal data, or speculative facts. Memory is a user-maintained project file, not an actual resurrection of a deleted conversation. Existing source code, Git history, and tests remain the evidence of what is really implemented.
