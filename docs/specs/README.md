# Feature specifications

This directory stores durable, feature-specific specifications for Claude Code and OpenCode.

## Naming

- Main feature: `feature-01-project-foundation.md`, `feature-02-authentication.md`, etc.
- Proposed change to an existing feature: `changes/feature-21-withdrawal-rules.md`.

## Status lifecycle

`PROPOSED` → explicit user approval → `APPROVED` → implementation and verification → `IMPLEMENTED`.

The Architect must not mark its own specification `APPROVED`. After reviewing a proposal, the user can say: `I approve docs/specs/<file>.md. Change its status to APPROVED only; do not implement yet.` The Develop workflow must refuse to implement a feature spec or change proposal unless its status is `APPROVED` and its scope matches the request.

Do not overwrite approved specifications when exploring a new business-rule change. Create a separate proposal in `changes/`, then update the canonical feature spec and `project-overview.md` after the approved change has been implemented and verified.
