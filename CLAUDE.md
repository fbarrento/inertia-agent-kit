# CLAUDE.md

This file gives agent guidance for working on Inertia Agent Kit itself.

## What This Repo Is

Inertia Agent Kit is an AI-native convention, feedback, audit, and
verification kit for Laravel Inertia frontends.

The current product implementation is a Laravel package:

- `composer.json` defines the package `fbarrento/inertia-agent-kit`.
- `src/InertiaAgentKitServiceProvider.php` registers the package config and
  Artisan commands.
- `src/Console/` contains the command entrypoints.
- `src/Init`, `src/Scaffolding`, `src/Audit`, `src/Feedback`, and command-local
  verify code contain the first implementation slices.
- `resources/stubs/react/` contains React scaffold stubs.
- `resources/boost/` contains Laravel Boost guidance resources.
- `tests/Feature/` contains Pest/Testbench package coverage.
- `docs/inertia-agent-kit/` contains the product and architecture specs.

The earlier Node CLI proof remains in `bin/iak.mjs`, `src/iak.mjs`, and
`test/smoke.mjs` for reference during the port. Do not expand it as the product
surface.

## Commands

```bash
composer test
php artisan iak:init --json
php artisan iak:make-resource vehicles --json
php artisan iak:audit --json
php artisan iak:feedback list --json
php artisan iak:feedback show fbk_... --json
php artisan iak:feedback resolve fbk_... --evidence=.iak/runs/run_.../verify.json --json
php artisan iak:verify --json
php artisan iak:handoff create --task="..." --changed-file=feature:modify:resources/js/features/vehicles/vehicle-table.tsx --verify=.iak/runs/<run-id>/verify.json --tests=.iak/runs/<run-id>/tests.json --json
php artisan iak:handoff validate .iak/runs/<run-id>/handoff.json --json
```

Package tests use Pest with Orchestra Testbench. If dependencies are missing,
install Composer dependencies before treating `composer test` as authoritative.

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

The first Laravel package baseline implements:

- `iak:init`;
- `iak:make-resource vehicles`;
- `iak:audit --json`;
- `iak:feedback list/show/resolve --json`;
- `iak:verify --json`;
- `iak:handoff create/validate --json`;
- Boost guidance resources;
- Testbench/Pest coverage for package boot and command slices.

Still pending:

- real Spatie Data and Wayfinder generation;
- real Pest Browser and Playwright execution;
- Storybook addon;
- MCP server;
- full `iak.handoff.v1` schema coverage beyond first-port create/validate.

Keep new work aligned with `docs/inertia-agent-kit/implementation-rfc.md`.
