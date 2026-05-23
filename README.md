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
php artisan iak:handoff create --task="..." --changed-file=feature:modify:resources/js/features/vehicles/vehicle-table.tsx --verify=.iak/runs/<run-id>/verify.json --tests=.iak/runs/<run-id>/tests.json --json
php artisan iak:handoff validate .iak/runs/<run-id>/handoff.json --json
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

## Package Architecture

The flat-package refactor targets Laravel 12 and 13 only. Implementation code
should move toward these package roles:

- `src/Actions/*`: one responsibility, constructor-injected dependencies, and
  exactly one public workflow method: `handle()`. Actions should not use
  private helper methods; extract smaller actions or support/data classes
  instead.
- `src/Data/*`: JSON schema output objects implementing `JsonSerializable`.
- `src/Enum/*`: fixed vocabularies; do not duplicate private string const
  lists.
- `src/Console/*`: thin input/output only.
- `src/Support/*`: small reusable helpers and mechanical adapters only.

Every production class needs a mirrored unit test file under `tests/Unit`, and
the package coverage target is exactly 100.0%. Pest tests must not define global
helper functions.

Refactor readiness requires PHPStan at max level and Rector in dry-run mode,
alongside the focused package tests.

## Agent-Optimized Output

The package installs Laravel PAO as a dev dependency so AI agents receive compact
JSON output from Pest, PHPStan, Rector, and supported Artisan commands. PAO
activates only when `laravel/agent-detector` detects an agent environment, so
normal human terminal output is unchanged.

PAO requires PHP 8.3+ in the development toolchain. Runtime package constraints
remain separate from the dev-only output tooling.

## Laravel Boost

IAK ships package-provided Boost resources:

- `resources/boost/guidelines/core.blade.php`
- `resources/boost/skills/inertia-agent-kit/SKILL.md`

Install or refresh Boost in the consuming Laravel app so those resources are
discovered:

```bash
composer require laravel/boost --dev
php artisan boost:install
php artisan boost:update
```

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
