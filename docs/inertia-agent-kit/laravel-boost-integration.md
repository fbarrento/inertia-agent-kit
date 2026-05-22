# Laravel Boost Integration

Status: draft spec  
Date: 2026-05-22  
Owned surface: Inertia Agent Kit as a Laravel package that integrates with
Laravel Boost instead of rebuilding generic Laravel agent tools.

## Product Boundary

Inertia Agent Kit (IAK) is an Inertia product first. This spec covers its
Laravel package path, where Laravel is the first backend integration and Boost
is the generic Laravel agent substrate.

The Laravel package should help an agent understand, change, and verify
Inertia user flows with structured context and feedback.

IAK does not own generic Laravel application inspection. It must not rebuild
tools for application info, logs, routes, database schema, database queries,
documentation search, or generic MCP server setup. Those belong to Laravel
Boost.

IAK owns the Inertia-specific layer:

- discovering Inertia pages, page components, layouts, forms, validation
  surfaces, navigation paths, and page-prop contracts;
- exposing the canonical `iak.feedback.v1` protocol for agent-visible
  diagnostics and verification results;
- teaching agents the required loop for changing Inertia flows safely;
- providing package-specific Boost guidelines and skills;
- optionally contributing IAK-specific MCP tools through Boost or Laravel MCP,
  if the current Boost APIs support third-party tool registration.

Boost remains the required Laravel substrate. IAK instructions should tell
agents to use Boost for Laravel facts before guessing.

## Composer Package Shape

Recommended package name:

```json
{
  "name": "inertia-agent-kit/laravel",
  "type": "library",
  "autoload": {
    "psr-4": {
      "InertiaAgentKit\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "InertiaAgentKit\\InertiaAgentKitServiceProvider"
      ]
    }
  }
}
```

Runtime dependencies should stay narrow:

- `php`: supported PHP versions for the Laravel versions IAK targets.
- `illuminate/support`, `illuminate/console`, `illuminate/routing`: match the
  supported Laravel versions.
- `inertiajs/inertia-laravel`: required, because IAK is not a generic Laravel
  package.

Boost dependency policy needs one deliberate choice:

- Preferred: IAK is installed as a dev package and requires `laravel/boost`
  as a dev dependency in the consuming app:

  ```bash
  composer require inertia-agent-kit/laravel laravel/boost --dev
  ```

- Alternative: IAK lists `laravel/boost` in `suggest` and `iak:install`
  refuses to install agent resources until Boost is present. This avoids
  making Boost a transitive production dependency if a consumer installs IAK
  incorrectly outside `require-dev`.

The first public release should prefer explicit `--dev` installation in the
root app. Composer cannot force a transitive dependency to remain dev-only for
consumers, so putting `laravel/boost` in IAK's normal `require` section would
be risky unless IAK is clearly dev-only and documented as such.

## Package Layout

Target package layout:

```text
config/inertia-agent-kit.php
resources/boost/guidelines/core.blade.php
resources/boost/skills/inertia-agent-kit/SKILL.md
routes/inertia-agent-kit.php
src/
  InertiaAgentKitServiceProvider.php
  Console/InstallCommand.php
  Console/DoctorCommand.php
  Feedback/FeedbackEnvelope.php
  Inertia/PageMap.php
  Inertia/PageContractInspector.php
  Mcp/Tools/
tests/
```

The package should avoid publishing application code by default. Generated or
published files should be limited to config, optional local overrides, and
agent resources that Boost manages.

## Service Provider Responsibilities

`InertiaAgentKitServiceProvider` should:

- merge `config/inertia-agent-kit.php`;
- publish the config file under a clear tag such as
  `inertia-agent-kit-config`;
- register `iak:install` and `iak:doctor` when running in console;
- register local-only diagnostic routes behind config-gated middleware, for
  example `local`, `auth`, or a package-specific gate;
- bind Inertia-specific services such as page-map discovery, page contract
  inspection, and feedback normalization;
- expose package metadata needed by Boost guidelines and skills;
- register IAK-specific MCP tools only through a verified public Boost or
  Laravel MCP extension point.

The provider should not write agent files directly. Boost already owns
generated MCP configuration, guideline files, skill installation, and
`boost.json` lifecycle.

## Install Command Responsibilities

`php artisan iak:install` should be an orchestration and validation command,
not a replacement for `php artisan boost:install`.

It should:

- confirm the command is running inside a Laravel app;
- confirm `inertiajs/inertia-laravel` is installed;
- detect whether `laravel/boost` is installed;
- explain the required install command if Boost is missing;
- publish IAK config if it is absent;
- optionally publish local example overrides under `.ai/` only when requested;
- call or prompt for `php artisan boost:install` when Boost has not been set
  up;
- call or prompt for `php artisan boost:update --discover` when Boost is
  already installed and IAK was added afterward;
- run `iak:doctor` at the end and print or link to feedback records when
  diagnostics are produced.

It should not:

- modify `composer.json` unless the user passes an explicit flag;
- manually edit `CLAUDE.md`, `AGENTS.md`, `.mcp.json`, `boost.json`, or other
  Boost-generated files;
- create a second generic MCP server for Laravel app inspection;
- install front-end scaffolding into the app unless a later IAK feature
  requires a user-owned adapter.

## Boost Guideline Integration

Boost supports third-party package guidelines via:

```text
resources/boost/guidelines/core.blade.php
```

IAK's core guideline should be concise and always-loaded. It should cover:

- IAK's product boundary: use Boost for Laravel facts and IAK for Inertia
  flow context;
- the required agent loop;
- the compatible feedback protocol;
- security boundaries for local-only diagnostic endpoints;
- when to activate the IAK skill.

The guideline should not duplicate Laravel, Inertia, React, Vue, Svelte,
Tailwind, Pest, or Pint rules already shipped by Boost. It should point agents
back to Boost's documentation search when exact framework behavior is needed.

## Boost Skill Integration

Boost supports third-party package skills via:

```text
resources/boost/skills/inertia-agent-kit/SKILL.md
```

The skill should be loaded on demand when an agent is changing or debugging an
Inertia user flow. It should contain operational patterns that are too large
for the always-loaded guideline:

- how to inspect an Inertia page map;
- how to trace a form from page component to controller action, validation,
  redirect, flash/session state, and rendered error props;
- how to use IAK feedback records during implementation;
- how to verify navigation, validation, authorization, and loading/error
  states;
- how to report changed files, checks, blockers, and remaining risk.

The skill should explicitly instruct agents to use Boost MCP tools for:

- application and package versions;
- routes and Artisan commands, if available through the current Boost release;
- logs and last error inspection;
- database schema and safe read queries;
- Laravel, Inertia, Tailwind, Pest, and related documentation search.

## IAK Contributions Beyond Boost

IAK should add only capabilities that are specific to Inertia workflows:

- Page map: a structured index of Inertia route names, controllers, page
  components, layouts, and inferred props.
- Page contracts: normalized descriptions of props, shared data, validation
  errors, flash messages, authorization expectations, and expected redirects.
- Flow traces: a route-to-page-to-form-to-action trace for common user tasks.
- Feedback records: structured diagnostics and verification output that agents
  can quote, store, or use as next-step input.
- Inertia-aware checks: missing props, stale component names, unreachable page
  components, mismatched form fields, validation errors not surfaced in the
  component, and redirects that do not land on an Inertia page.
- Optional front-end helper hooks or test utilities, only if they are
  user-owned and clearly scoped to local verification.

IAK should not add:

- a generic docs search tool;
- a generic database schema reader;
- a generic log reader;
- a generic Laravel route lister if Boost already exposes one;
- an alternate package guideline/skill installer;
- an agent memory system unrelated to Inertia work.

## Compatible Feedback Protocol

The canonical feedback protocol is `iak.feedback.v1`, owned by
`docs/inertia-agent-kit/feedback-protocol.md`. This Boost integration must not
define a second feedback shape.

The Laravel package should implement or expose the same protocol over every
surface it owns:

- HTTP routes under the local-only `/__iak/feedback` prefix;
- CLI commands such as `iak feedback list`, `iak feedback show`, and
  `iak feedback resolve`;
- IAK-specific MCP tools, if the current Boost / Laravel MCP APIs support
  registering them cleanly;
- diagnostic commands such as `iak:doctor` when they need to create
  agent-consumable feedback records.

Compatibility rules:

- Feedback records use `schema: "iak.feedback.v1"`.
- Records are stored in the local `.iak/feedback/` layout defined by the
  feedback protocol.
- Producers include app overlay, Storybook, automated tests, agents, and
  compatible external producers such as Instruckt.
- Resolution must remain evidence-bearing. The Laravel package should not
  provide a shortcut that lets an agent close feedback with prose alone.
- MCP tool results may summarize records for token economy, but they must
  preserve record ids and fetch full records/artifacts on demand.
- If Boost exposes a compatible feedback/reporting tool in the installed
  version, IAK may mirror or forward records there as transport. The canonical
  record still remains `iak.feedback.v1`.
- Agent final responses should summarize the same operational fields required
  by the protocol and by this lane: changed files, checks run, blockers, and
  remaining risk.

## Required Agent Loop

IAK's guideline and skill should require this loop for code-changing tasks:

1. Establish context.
   Use Boost to inspect app versions, installed packages, relevant docs,
   routes, logs, schema, and configuration where needed.

2. Identify the Inertia surface.
   Use IAK to find the route, controller/action, page component, form/action,
   props, validation contract, and redirect/flash behavior.

3. Make the smallest coherent change.
   Keep code edits inside the app-owned surface. Do not publish or rewrite
   generated Boost files.

4. Verify the flow.
   Run focused tests, static checks, and IAK diagnostics. Use browser-level
   verification when the change affects rendered behavior.

5. Report using the feedback protocol.
   Include changed files, checks run, blockers, and remaining risk. If an IAK
   feedback record was produced, preserve its material findings.

Agents should not skip from route discovery to code edits when the affected
Inertia page contract is unclear.

## Install Flow

New app setup:

```bash
composer require inertia-agent-kit/laravel laravel/boost --dev
php artisan boost:install
php artisan iak:install
php artisan iak:doctor
```

Existing Boost app:

```bash
composer require inertia-agent-kit/laravel --dev
php artisan boost:update --discover
php artisan iak:install
php artisan iak:doctor
```

Existing IAK app after package update:

```bash
composer update inertia-agent-kit/laravel
php artisan boost:update --discover
php artisan iak:doctor
```

The install documentation should explain that Boost-generated files may be
regenerated by `boost:install` and `boost:update`, while IAK config is managed
by Laravel's normal config publish flow.

## Security And Environment Boundary

IAK diagnostic routes and tools should default to local development only.

Minimum boundaries:

- disabled unless `app()->environment('local', 'testing')` or explicitly
  enabled;
- protected by signed local middleware, auth, or a package-specific gate when
  exposed over HTTP;
- never expose secrets, raw environment values, session payloads, API tokens,
  or unrestricted database query execution;
- prefer read-only diagnostics;
- make mutating tools opt-in and explicit.

This keeps IAK aligned with Boost as a local agent-assistance package rather
than a production feature surface.

## Open Questions For Current Boost APIs

These require verification against the installed Laravel Boost version before
implementation:

- What minimum `laravel/boost` version should IAK support?
- Does Boost currently provide a public API for third-party packages to
  register MCP tools into `php artisan boost:mcp`, or should IAK register
  tools through `laravel/mcp` separately?
- Does Boost discover third-party guidelines and skills from packages
  installed only in the root app's `require-dev` section?
- Is `boost:update --discover` the correct command for discovering newly
  installed third-party package guidelines and skills after the first
  `boost:install`?
- Are `resources/boost/guidelines/core.blade.php` and
  `resources/boost/skills/{skill-name}/SKILL.md` the only supported package
  resource paths, or are package-specific/versioned guideline paths also
  supported?
- What is the exact Agent Skills frontmatter supported by the current Boost
  release beyond required `name` and `description`?
- Which Boost MCP tools exist in the target version? The 13.x docs list app
  info, browser logs, database connections/query/schema, absolute URL, last
  error, log entries, and docs search, but implementation should verify the
  exact tool names and schemas.
- Does the current Boost release expose a feedback/reporting MCP tool? If it
  does, IAK should map `iak.feedback.v1` onto it. If it does not, IAK should
  keep feedback as an IAK command/tool concern.
- Can a package trigger or compose `boost:install` programmatically through a
  stable API, or should `iak:install` only call the Artisan command / instruct
  the user?
- How should IAK avoid duplicate or stale installed skills when Boost
  regenerates agent resources?

## Source Notes

Boost assumptions in this spec were checked against the Laravel 13.x Boost
documentation on 2026-05-22:

- Boost installs with `composer require laravel/boost --dev` followed by
  `php artisan boost:install`.
- `boost:update` refreshes generated resources, and `boost:update --discover`
  scans for newly installed package resources.
- Boost owns generated MCP configuration, guidelines, skills, and `boost.json`.
- Third-party package guidelines live at
  `resources/boost/guidelines/core.blade.php`.
- Third-party package skills live at
  `resources/boost/skills/{skill-name}/SKILL.md`.
- Boost distinguishes always-loaded guidelines from on-demand skills.
