# CLAUDE.md

This file gives agent guidance for working on Inertia Agent Kit itself.

## What This Repo Is

Inertia Agent Kit is an AI-native convention, feedback, audit, and
verification kit for Laravel Inertia frontends.

The current product implementation is a Laravel package:

- `composer.json` defines the package `fbarrento/inertia-agent-kit`.
- `src/InertiaAgentKitServiceProvider.php` registers the package config and
  Artisan commands.
- The flat refactor target is `src/Actions`, `src/Data`, `src/Enum`,
  `src/Console`, and `src/Support`.
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
vendor/bin/phpstan analyse --debug --memory-limit=1G
vendor/bin/rector --dry-run --no-progress-bar
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
Laravel PAO is installed as a dev dependency; when Codex or another detected
agent runs Pest, PHPStan, Rector, or Artisan, PAO emits compact agent-optimized
output. Human terminal output remains unchanged.
PHPStan max level and Rector dry-run are required refactor gates.

## Agent Runtime Rules

Worker prompts must be strict and self-contained. Include the todo scope,
owned files, forbidden files, acceptance gates, and the required final report:
changed files, checks run, blockers, and remaining risk.

Codex workers:

- Use `gpt-5.3-codex-spark`.
- Do not use extra-high reasoning by default. Use the standard/default
  reasoning effort unless the lead explicitly approves a harder task.
- If a worker starts with the wrong model or reasoning effort, stop and respawn
  it instead of continuing.

Claude workers:

- Use Sonnet or Opus 4.6.
- Prompts must explicitly say that project instructions are hard constraints,
  not preferences.
- Claude workers must stop and report a blocker when instructions conflict;
  they must not improvise around folder names, test shape, coverage, or JSON
  handoff rules.

## Package Architecture Rules

The refactor supports Laravel 12 and 13 only. Do not add Laravel 11-specific
code or compatibility paths.

- `src/Actions/*`: one responsibility, constructor injection, and exactly one
  public workflow method: `handle()`. Constructors are allowed. Private helper
  methods and local closure helpers inside `handle()` are not allowed; extract
  a smaller action or support/data class and inject it instead. Do not hide
  fixed vocabulary in private const arrays.
  Laravel contextual attributes such as `#[Config(...)]` are allowed when they
  remove manual config plumbing.
- `src/Data/*`: JSON schema output objects implementing `JsonSerializable` and
  preserving the current command/artifact contracts. Implement
  `Illuminate\Contracts\Support\Arrayable` when commands, writers, or tests
  need array interop.
- `src/Enum/*`: fixed vocabularies for statuses, changed-file roles/actions,
  artifact kinds, and similar sets. Avoid duplicated private string const
  lists.
- `src/Console/*`: thin Artisan input/output only. Commands delegate parsing,
  validation, file IO, schema assembly, and persistence to actions.
- `src/Support/*`: small reusable helpers and mechanical adapters only. Do not
  put command workflows or domain policy here.

## Test Rules

Every production class needs a matching mirrored unit test file:

- `src/Actions/Foo.php` -> `tests/Unit/Actions/FooTest.php`
- `src/Data/FooData.php` -> `tests/Unit/Data/FooDataTest.php`
- `src/Enum/Foo.php` -> `tests/Unit/Enum/FooTest.php`
- `src/Support/Foo.php` -> `tests/Unit/Support/FooTest.php`

The package coverage target is exactly 100.0%. For worker lanes, each class
added or touched by that worker must report 100.0% coverage before the lane is
accepted.

Do not define global helper functions in Pest test files. Pest files are not
namespaced, so duplicate helper function names can break the suite. Use local
closures, `beforeEach()` state, datasets, or utility classes under
`tests/Utils`.

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
