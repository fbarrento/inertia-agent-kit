# Inertia Agent Kit

Inertia Agent Kit is an AI-native convention, feedback, audit, and
verification kit for Laravel Inertia frontends.

It gives frontend agents Laravel-like constraints: pages mirror resource
controllers, features stay resource-local, backend-derived types come from
generated artifacts, formatting and translation stay on the backend, styling
uses semantic design-system tokens, and handoff requires JSON evidence.

## Commands

Install from the repository while the package is pre-release:

```bash
composer config repositories.inertia-agent-kit vcs git@github.com:fbarrento/inertia-agent-kit.git
composer require fbarrento/inertia-agent-kit:dev-main --dev
```

```bash
php artisan iak:init --json
php artisan iak:make-resource vehicles --json
php artisan iak:audit --json
php artisan iak:feedback list --json
php artisan iak:feedback show fbk_... --json
php artisan iak:feedback resolve fbk_... --summary "..." --evidence=.iak/runs/run_.../verify.json --json
php artisan iak:verify --json
```

## First Milestone

The current Laravel package implementation provides the first PHP loop:

- `iak:init` writes `iak.config.json`, `.iak/config.json`, rules, manifest
  directories, schema placeholders, feedback and run stores, and init state.
- `iak:make-resource vehicles` creates thin page adapters, resource-local
  feature components, colocated stories, fixtures, and generated type imports.
- `iak:audit --json` catches arbitrary Tailwind values, raw hex colors,
  primitive color utilities, forbidden global behavior buckets, missing stories,
  and feature types that do not import generated backend contracts.
- `iak:feedback` lists, shows, and resolves local `iak.feedback.v1` records
  under `.iak/feedback`.
- `iak:verify --json` runs or consumes audit evidence, blocks on unresolved
  feedback, writes `.iak/runs/<run-id>/verify.json`, and records placeholder
  screenshot metadata without running browser automation yet.

Run the PHP package tests:

```bash
composer test
```

The original Node CLI prototype remains in `src/iak.mjs` and `bin/iak.mjs` as a
reference during the port. The product surface is now the Laravel package and
Artisan commands.

## Local Artifacts

IAK writes local agent/runtime state under `.iak/`. The repository ignores
feedback and verification run artifacts by default:

- `.iak/feedback/`
- `.iak/runs/`

Committed source should include configuration, docs, schemas, and package code,
not local browser screenshots, console logs, or feedback work queues.

## Specs

The orchestration and lane specs live in `docs/inertia-agent-kit/`. Start with:

- `docs/inertia-agent-kit/implementation-rfc.md`
- `docs/inertia-agent-kit/scaffolding-commands.md`
- `docs/inertia-agent-kit/audit-checks.md`
- `docs/inertia-agent-kit/feedback-protocol.md`
- `docs/inertia-agent-kit/verification-loop.md`
- `docs/inertia-agent-kit/mcp-tools-and-resources.md`
