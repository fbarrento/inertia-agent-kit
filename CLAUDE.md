# CLAUDE.md

This file gives agent guidance for working on Inertia Agent Kit itself.

## What This Repo Is

Inertia Agent Kit is an AI-native convention, feedback, audit, and
verification kit for Laravel Inertia frontends.

The current implementation is a focused Node CLI proof:

- `bin/iak.mjs` is the executable entrypoint.
- `src/iak.mjs` contains the CLI implementation.
- `test/smoke.mjs` builds a throwaway Laravel + Inertia + React fixture and
  proves the first loop end to end.
- `docs/inertia-agent-kit/` contains the product and architecture specs.

## Commands

```bash
npm test
node bin/iak.mjs
node bin/iak.mjs init --target /path/to/fixture --apply --json
node bin/iak.mjs new resource vehicles --target /path/to/fixture --apply --json
node bin/iak.mjs audit --target /path/to/fixture --json
node bin/iak.mjs verify --target /path/to/fixture --json
```

There is no build step yet. The implementation uses Node built-ins and ESM
`.mjs` files.

## Git And PR Conventions

Commit messages and pull request titles must use Conventional Commits.

Use:

```txt
feat: add resource scaffolding
fix: preserve generated type imports
docs: clarify feedback protocol
test: cover verification evidence
chore: update repository metadata
```

Do not use sentence-style PR titles such as `Initial Inertia Agent Kit
baseline`. The matching Conventional Commit title is:

```txt
feat: add initial Inertia Agent Kit baseline
```

## Product Rules

- Laravel is the first integration; React is the first Inertia adapter.
- Pages mirror Laravel resource controller actions and stay thin.
- Resource behavior belongs under `features/<resource>`.
- Do not create top-level `queries`, `actions`, `forms`, `hooks`, or
  `composables` folders by default.
- Backend-derived TypeScript comes from generated files. Do not ask agents to
  handwrite Laravel DTO copies.
- Backend owns translation, formatted dates, money, enum labels, validation
  copy, and domain-sensitive text.
- Frontend code uses semantic design-system utilities such as `bg-ds-surface`
  and `text-ds-fg`.
- Agent-facing command output should be JSON-first and reference artifacts by
  path instead of embedding large logs, screenshots, DOM, CSS, SVG, or token
  content.

## Current Scope

The first baseline proves:

- `iak init`;
- `iak new resource vehicles`;
- `iak audit --json`;
- `iak feedback create/list/show/resolve --json`;
- `iak verify --json`;
- smoke-test verification with screenshot metadata.

Still pending:

- Laravel package and service provider;
- real Spatie Data and Wayfinder generation;
- real Pest Browser and Playwright execution;
- Storybook addon;
- MCP server;
- `iak.handoff.v1` create/validate commands.

Keep new work aligned with `docs/inertia-agent-kit/implementation-rfc.md`.
