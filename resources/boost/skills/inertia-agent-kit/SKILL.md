---
name: inertia-agent-kit
description: Use when changing, auditing, verifying, or handing off Laravel Inertia UI work with IAK manifest, scaffolding, feedback, design-system, or Storybook evidence.
---

# Inertia Agent Kit

Use this skill for non-trivial Laravel/Inertia frontend work: user-flow
refactors, page prop tracing, `iak:make-resource` review,
`iak.feedback.v1` resolution, `iak:audit` or `iak:verify` failures, reusable
component changes, and handoff evidence.

## Product Boundary

Boost stays first for generic Laravel facts: versions, docs,
route facts, app URLs, logs, browser logs, last errors, database/schema, safe
reads, MCP setup, and resource discovery.

IAK starts after that context is known. Use IAK for Inertia/frontend
conventions, manifest context, page and resource roles, `iak:make-resource`
scaffolding, generated TypeScript discipline, audit, feedback,
verify, design-system contracts, Storybook/HITL evidence, and final JSON
handoff evidence.

Do not manually edit Boost-generated files such as `CLAUDE.md`, `AGENTS.md`,
`.mcp.json`, `boost.json`, or generated Boost guidelines/skills. Use
`boost:install` or `boost:update --discover` when apps need Boost-managed
resources refreshed.

## Package Refactor Rules

For IAK itself, target Laravel 12/13. Flat roles:
`src/Actions/*` is one-responsibility `handle()` with constructor injection and
no private helpers; `src/Data/*` implements `JsonSerializable`; `src/Enum/*`
owns fixed vocabularies, no private string const lists; `src/Console/*` is
input/output; `src/Support/*` is small reusable helpers/adapters. Mirror every production class under
`tests/Unit`, avoid global Pest helpers, keep 100.0% coverage, and gate with
PHPStan at max level plus Rector dry-run.

## Required Workflow

1. Establish Laravel context with Boost before guessing about routes,
   controllers, packages, framework APIs, logs, browser logs, or database state.
2. Read the IAK manifest and local conventions before opening broad source
   trees. If the manifest is missing or stale, run or recommend
   `IAK_AGENT=1 php artisan iak:init --json`.
3. Identify the affected Inertia route, page component, feature component,
   layout, reusable component, generated type surface, and design-system or
   Storybook evidence surface.
4. Make the smallest coherent frontend change that preserves the local role
   split and existing conventions.
5. Run focused IAK checks with JSON output where possible:
   `php artisan iak:audit --json`, `php artisan iak:verify --json`, and
   `php artisan iak:feedback ... --json`.
6. For final handoff, create and validate an `iak.handoff.v1` artifact with
   `php artisan iak:handoff ... --json`.
7. Report changed files, checks run, artifact paths, unresolved feedback,
   blockers, and remaining risk.

## Page And Resource Roles

Use `php artisan iak:make-resource <name> --json` when creating a new resource
workflow or when local conventions are unclear. Treat generated output as a
starting point, then adapt only within the existing app structure.

Keep route/page boundaries explicit:

- Pages own route-level props, layout selection, redirects, flash/session
  surfaces, and composition of feature components.
- Features own workflow-specific state, form wiring, loading/error states, and
  action affordances.
- Reusable components stay app-agnostic and receive typed props rather than
  reaching into page globals.
- Layouts own persistent navigation and shared chrome, not page-specific
  business rules.

When tracing a form, move from route and controller facts supplied by Boost to
the Inertia page, feature component, form/action helper, validation response,
redirect, flash/session state, and rendered props.

## Generated Types And Text

Backend-derived TypeScript is not hand-authored UI state. Preserve generated
aliases and import paths, and regenerate through the configured IAK or app
pipeline when the backend contract changes.

Formatting, labels, dates, money, pluralization, and translations should follow
the backend-owned or local app convention already represented in the manifest.
Do not copy framework documentation or invent a parallel formatting layer from
this skill.

## Design-System And Story Evidence

Prefer established design-system tokens, semantic `ds-` utilities, and local
component contracts over one-off styling. If a reusable component changes,
update or add Storybook stories, fixtures, and states that show the expected
variants, empty states, errors, and loading behavior.

Store large evidence as artifact paths from `iak:audit`, `iak:verify`, tests,
Storybook, screenshots, or feedback records. Do not paste screenshots, DOM
dumps, logs, generated type files, or schema bodies into the handoff.

## Feedback Workflow

Use the canonical `iak.feedback.v1` protocol only. Do not define a second
feedback schema.

1. List or inspect records with `php artisan iak:feedback list --json` or
   `php artisan iak:feedback show <id> --json`.
2. Reproduce the target surface using Boost for app URLs, route facts, logs, or
   browser logs when needed.
3. Fix the Inertia/frontend issue and run focused checks.
4. Resolve only with evidence, for example
   `php artisan iak:feedback resolve <id> --evidence=<path> --json`.

## Audit And Verify Workflow

Use `IAK_AGENT=1` when command output is intended for an agent. Prefer focused
commands first, then broader checks before handoff:

- `IAK_AGENT=1 php artisan iak:audit --json`
- `IAK_AGENT=1 php artisan iak:verify --json`
- `IAK_AGENT=1 php artisan iak:verify --feedback=<id> --json`

Treat failures as work items with file targets and evidence. Use Boost for the
Laravel facts needed to interpret a failure; use IAK for the Inertia page,
manifest, feedback, design-system, and Storybook implications.

## Do Not Use IAK For

- Generic Laravel debugging that Boost already covers.
- Framework API reference for Laravel, Inertia, Tailwind, Pest, Storybook,
  Spatie Data, or Wayfinder.
- Database exploration or query authoring, except to point back to Boost.
- Route discovery, URL generation, logs, browser logs, app info, or last-error
  inspection, except to point back to Boost.
- Replacing `php artisan boost:install`, `boost:update --discover`, or Boost's
  generated resource lifecycle.
- Implementing broad MCP behavior before the Artisan command surface is stable.

## Handoff Checklist

Create final handoffs as JSON artifacts, then validate them before responding:

```bash
php artisan iak:handoff create --task="..." --changed-file=feature:modify:resources/js/features/vehicles/vehicle-table.tsx --verify=.iak/runs/<run-id>/verify.json --tests=.iak/runs/<run-id>/tests.json --json
php artisan iak:handoff validate .iak/runs/<run-id>/handoff.json --json
```

Reference the handoff path in chat. Do not paste large logs, screenshots, DOM
dumps, generated types, or schema bodies.

End with:

- Changed files.
- IAK commands and other checks run.
- Handoff artifact path when final handoff is needed.
- Artifact paths for audit, verify, feedback, Storybook, screenshots, or tests.
- Feedback IDs resolved or left open.
- Blockers and remaining risk.
