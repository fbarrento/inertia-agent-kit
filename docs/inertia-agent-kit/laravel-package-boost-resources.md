# Laravel Package Boost Resources

Status: planning spec
Date: 2026-05-22
Owned surface: package-provided Laravel Boost guideline and skill resources for
`fbarrento/inertia-agent-kit`.

## Purpose

The Laravel package should ship the smallest Boost resource layer needed to
teach agents how to use Inertia Agent Kit (IAK) inside a Laravel/Inertia app.

These resources do not implement commands, MCP tools, scaffolding, or audits.
They guide agents toward the package's Artisan command surface and preserve the
product boundary:

- Boost owns generic Laravel context.
- IAK owns Inertia/frontend workflow discipline.

## Planned Resource Files

| File | Load behavior | Responsibility |
| --- | --- | --- |
| `resources/boost/guidelines/core.blade.php` | Always loaded by Boost after discovery | Concise default rules for using IAK in an agent workflow. |
| `resources/boost/skills/inertia-agent-kit/SKILL.md` | Loaded on demand by the agent skill system | Operational workflow for changing, debugging, auditing, verifying, and handing off Inertia UI work with IAK. |

Do not add package-specific copies under generated app files. Boost owns the
installed/generated agent resource lifecycle, including `boost.json`, MCP setup,
and regenerated agent files.

## Responsibility Boundary

Use Boost for:

- Laravel and package version context.
- Laravel, Inertia, Tailwind, Pest, and related documentation search.
- Application URLs.
- Routes, logs, last errors, browser logs, database connections, schema, and
  safe read queries when Boost exposes those tools.
- Baseline MCP setup and Boost resource discovery.

Use IAK for:

- Inertia page/resource conventions.
- Manifest and convention context.
- Resource scaffolding through `php artisan iak:make-resource`.
- Frontend role discipline for pages, features, layouts, reusable components,
  generated types, and design-system tokens.
- Audit, feedback, verify, and JSON handoff evidence.
- Storybook/HITL rules and feedback protocol.
- Later IAK-specific MCP resources/tools, only after the Artisan commands are
  stable.

The Boost resources must not present IAK as a replacement for Boost. They must
tell agents to ask Boost for Laravel facts before guessing.

## Core Guideline Content

`resources/boost/guidelines/core.blade.php` should be short enough to stay
safe as always-loaded context. Target content categories:

1. **Product boundary**
   State that IAK is the Inertia/frontend convention, feedback, design-system,
   and verification layer for Laravel/Inertia apps. State that Boost remains
   the Laravel substrate.

2. **When IAK applies**
   Apply IAK rules when the task touches Inertia pages, resource workflows,
   frontend components, generated backend-derived TypeScript, design-system
   styling, Storybook states, HITL feedback, audit results, or UI verification.

3. **Required agent loop**
   Establish Laravel context with Boost, read IAK manifest/conventions, identify
   the affected Inertia route/page/feature/component, make the smallest
   coherent change, run focused IAK checks, and report evidence.

4. **Command surface**
   Point to the Artisan command names without documenting every option:
   `iak:init`, `iak:make-resource`, `iak:audit`, `iak:feedback`, and
   `iak:verify`. Mention `--json` and `IAK_AGENT=1` for agent output.

5. **Non-duplication reminder**
   Tell agents not to rely on this guideline for framework facts. Exact
   Laravel, Inertia, Tailwind, Pest, database, route, log, or browser-log
   behavior should come from Boost tools and Boost documentation search.

6. **Skill activation**
   Tell agents to consult the `inertia-agent-kit` skill for any non-trivial
   Inertia UI change, feedback resolution, audit failure, verification failure,
   or handoff.

The guideline should not include:

- Framework tutorials.
- Tailwind class catalogs.
- Pest Browser usage docs.
- Laravel route, validation, database, queue, policy, or logging rules already
  covered by Boost docs.
- Long command examples or generated JSON schemas.
- Full frontend file templates.

## IAK Skill Scope

`resources/boost/skills/inertia-agent-kit/SKILL.md` is the deeper operational
resource. Agents should consult it when:

- implementing or refactoring an Inertia user flow;
- tracing a route to controller action, page component, feature component,
  form/action, validation state, redirect, flash/session state, and rendered
  props;
- creating or reviewing `iak:make-resource` output;
- resolving `iak.feedback.v1` records;
- fixing `iak:audit` failures;
- running `iak:verify` or preparing evidence for handoff;
- changing reusable components that require Storybook stories, fixtures, or
  HITL review;
- touching design-system tokens, semantic `ds-` utilities, or component
  contracts;
- deciding whether later IAK MCP resources are relevant to an Inertia task.

The skill should refuse to cover:

- generic Laravel debugging that Boost already covers;
- database exploration or query authoring except by pointing back to Boost;
- route discovery, URL generation, logs, browser logs, app info, or last-error
  inspection except by pointing back to Boost;
- framework-specific API reference for Laravel, Inertia, Tailwind, Pest,
  Storybook, Spatie Data, or Wayfinder;
- package installation as a full tutorial;
- replacing `php artisan boost:install`, `boost:update --discover`, or Boost's
  generated resource lifecycle;
- implementing broad MCP behavior before the Artisan commands are stable.

Expected skill sections:

- Frontmatter with `name: inertia-agent-kit` and a short trigger-focused
  `description`.
- Product boundary and required Boost-first context step.
- Manifest-first workflow.
- Inertia page/resource role rules.
- Generated type, backend-owned formatting, and translation rules.
- Design-system and Storybook evidence rules.
- Feedback resolution workflow using `iak.feedback.v1`.
- Audit and verify workflow using JSON outputs and artifact paths.
- Final handoff checklist: changed files, checks run, artifacts, blockers, and
  remaining risk.

Keep examples small and IAK-specific. Prefer links or references to IAK schemas
and commands over copied schema bodies.

## Install And Publish Expectations

The Laravel package should be installed as a development dependency in the
consuming Laravel app:

```bash
composer require fbarrento/inertia-agent-kit laravel/boost --dev
php artisan boost:install
php artisan iak:init --json
php artisan iak:audit --json
php artisan iak:verify --json
```

For an app that already has Boost installed:

```bash
composer require fbarrento/inertia-agent-kit --dev
php artisan boost:update --discover
php artisan iak:init --json
```

For an app that updates IAK after initial setup:

```bash
composer update fbarrento/inertia-agent-kit
php artisan boost:update --discover
php artisan iak:audit --json
```

Package expectations:

- The service provider registers IAK Artisan commands and publishes only IAK
  config through Laravel's normal publish flow.
- The service provider does not write Boost-generated agent files directly.
- The Boost resources stay inside package `resources/boost/*` and are
  discovered or refreshed through Boost.
- `iak:init` may validate that Boost is installed and print the exact Boost
  command to run when it is missing.
- `iak:init` should not edit `CLAUDE.md`, `AGENTS.md`, `.mcp.json`,
  `boost.json`, or generated Boost output.
- Package docs should call out that IAK's resources are agent guidance, while
  config and `.iak/*` artifacts are the IAK runtime contract.

## Acceptance Checks

Resource presence:

- `resources/boost/guidelines/core.blade.php` exists in the package.
- `resources/boost/skills/inertia-agent-kit/SKILL.md` exists in the package.
- The skill file includes valid skill frontmatter with the canonical
  `inertia-agent-kit` name.
- The guideline and skill are included in the package distribution and are not
  hidden behind publish-only app stubs.

Boundary checks:

- Both resources say to use Boost for generic Laravel facts.
- Both resources identify IAK as the Inertia/frontend discipline layer.
- Neither resource defines generic MCP tools such as app info, docs search,
  route list, database schema/query, logs, browser logs, absolute URL, or last
  error.
- Neither resource copies Laravel, Inertia, Tailwind, Pest, Storybook, Spatie
  Data, or Wayfinder documentation.
- Neither resource defines a second feedback schema. They reference
  `iak.feedback.v1`.
- Neither resource instructs agents to manually edit Boost-generated files.

Content checks:

- The guideline remains concise and always-loaded. Implementation tests can
  enforce a line or word budget if Boost does not provide one.
- The guideline names the required agent loop and points to the skill for
  expanded workflow.
- The skill tells agents when to read the IAK manifest before opening broad
  source files.
- The skill includes audit, feedback, verify, Storybook, design-system, and
  handoff workflows at a procedural level.
- The skill stores or reports large evidence as artifact paths, not pasted
  screenshots, DOM dumps, logs, generated types, or schema bodies.

Install checks:

- A Testbench package fixture can locate both files under `resources/boost/*`.
- A docs or package test asserts the recommended install path includes
  `laravel/boost --dev`.
- A package test or static check asserts IAK does not write Boost-generated
  files during `iak:init`.

## Implementation Notes

- Follow the scratchpad command decision: `iak:init`, not `iak:install`.
- If older specs mention `iak:install`, implementation agents should reconcile
  that in a separate docs cleanup lane.
- Verify current Laravel Boost behavior before coding discovery tests:
  supported resource paths, skill frontmatter fields, package discovery timing,
  and whether `boost:update --discover` is the correct refresh command.
- Do not start IAK MCP implementation in this resource wave. The skill may
  describe future IAK MCP at a boundary level only.
