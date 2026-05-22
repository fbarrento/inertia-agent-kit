# Laravel Package Init Command

Status: planning contract
Owner: Inertia Agent Kit
Command: `php artisan iak:init --json`

This document defines the Laravel package port contract for the init command.
It is implementation guidance only; no PHP code is implemented in this wave.

## Purpose

`iak:init` prepares a Laravel + Inertia app for IAK's agent workflow. It writes
the minimum durable config, creates local `.iak/` runtime directories, emits a
manifest skeleton, and reports exactly what changed as one machine-readable JSON
object.

The command is idempotent. A second run should refresh generated runtime
artifacts and report unchanged source-controlled files without rewriting user
edits.

## Command Contract

Canonical command:

```bash
php artisan iak:init --json
```

Supported options for the first Laravel package slice:

| Option | Behavior |
| --- | --- |
| `--json` | Emit one compact JSON object to stdout. No prose, tables, ANSI, spinners, or prompts. |
| `--pretty` | Pretty-print JSON. Still one JSON object. |
| `--force` | Regenerate IAK-owned generated artifacts. It must not overwrite user-edited config unless a previous generated hash proves IAK owns it. |
| `--adapter=react` | Select renderer adapter. React is the only required v1 adapter; unsupported adapters fail with structured JSON. |

`IAK_AGENT=1` is equivalent to passing `--json` and disables interactive
prompts.

Exit codes follow the shared JSON handoff contract:

| Code | Meaning |
| --- | --- |
| `0` | Init completed or was already current. |
| `2` | Usage, config, schema, unsupported adapter, or writable-path error. |
| `3` | Laravel, Inertia, package, or filesystem environment cannot support init. |
| `4` | Unexpected internal error. |

The JSON body is required for non-zero exits whenever the command can produce
one.

## Filesystem Outputs

`iak:init` runs from the Laravel application root. All persisted paths in JSON
are project-relative POSIX paths unless explicitly marked otherwise.

### Source-Controlled Config

The Laravel package has two config surfaces:

| File | Ownership | Behavior |
| --- | --- | --- |
| `config/inertia-agent-kit.php` | User-owned Laravel package config | Publish from package defaults if absent. Preserve existing file. Only overwrite with `--force` when the file contains an IAK generated marker/hash from a previous init. |
| `iak.config.json` | User-owned agent contract | Create if absent from normalized defaults. Preserve existing file. This keeps non-PHP agents and future Node/AST helpers on a stable JSON contract. |

`config/inertia-agent-kit.php` should include package integration knobs such as
enabled environments, adapter default, path to `iak.config.json`, whether local
diagnostic routes are enabled, and Boost integration policy.

`iak.config.json` should use the existing `iak.config.v1` shape: project,
paths, generated outputs, conventions, and command names. The Laravel command
must not create frontend application code.

### Runtime State

Init creates the local runtime tree:

```txt
.iak/
  config.json
  state/
    init.json
  manifest/
    iak.manifest.v1.json
  schemas/
    iak.init.result.v1.schema.json
    iak.manifest.v1.schema.json
  feedback/
  runs/
  rules/
    inertia-agent-kit.md
```

Runtime files are IAK-owned and may be refreshed by `--force`:

- `.iak/config.json` is the resolved runtime snapshot derived from Laravel
  config, `iak.config.json`, installed package versions, adapter detection, and
  path defaults.
- `.iak/state/init.json` records the last init run id, IAK package version,
  Laravel/Inertia/Boost detection result, generated file hashes, and command
  schema version.
- `.iak/manifest/iak.manifest.v1.json` is a deterministic manifest skeleton.
- `.iak/schemas/*` are local copies or generated exports of public schemas used
  by agents and tests.
- `.iak/feedback/` and `.iak/runs/` are empty stores for later commands.
- `.iak/rules/inertia-agent-kit.md` is an optional agent-facing summary of the
  local IAK rules. It must point to Boost when Boost is installed.

The command must not edit `CLAUDE.md`, `AGENTS.md`, `.mcp.json`, `boost.json`,
or other Boost-generated files. Boost owns those surfaces.

### Manifest Skeleton

The init manifest is valid but intentionally sparse. Required top-level shape:

```json
{
  "schema": "iak.manifest.v1",
  "id": "manifest_01j...",
  "status": "valid",
  "summary": "Laravel Inertia React project with IAK initialized.",
  "project": {
    "name": "app",
    "root": ".",
    "laravel": "12.x",
    "inertia": "2.x",
    "renderer": "react",
    "typescript": true
  },
  "adapter": {
    "id": "laravel-inertia-react",
    "version": "0.1.0"
  },
  "conventions": {
    "pagesAreRouteAdapters": true,
    "featureRoot": "resources/js/features",
    "pageRoot": "resources/js/pages",
    "generatedTypes": "@/types/generated",
    "backendOwnsFormatting": true
  },
  "resources": [],
  "commands": {
    "init": "php artisan iak:init --json",
    "manifest": "php artisan iak:manifest --json",
    "makeResource": "php artisan iak:make-resource <resource> --json",
    "audit": "php artisan iak:audit --json",
    "verify": "php artisan iak:verify --json"
  },
  "schemas": {
    "init": ".iak/schemas/iak.init.result.v1.schema.json",
    "manifest": ".iak/schemas/iak.manifest.v1.schema.json"
  },
  "boost": {
    "installed": true,
    "useBoostFor": ["laravel_docs", "app_info", "absolute_urls", "browser_logs", "database_schema", "log_entries"],
    "useIakFor": ["manifest", "tokens", "components", "audit", "verify", "feedback", "handoff"]
  },
  "artifacts": {}
}
```

The manifest must not inline logs, generated type files, source files, CSS,
screenshots, or large docs. It references artifact paths only.

## JSON Output

`--json` emits one object with schema `iak.init.result.v1` and event
`iak.init.completed.v1` on success.

Example success shape:

```json
{
  "schema": "iak.init.result.v1",
  "event": "iak.init.completed.v1",
  "status": "completed",
  "summary": "IAK initialized for Laravel Inertia React.",
  "runId": "run_01j...",
  "project": {
    "name": "app",
    "root": ".",
    "laravel": "12.x",
    "inertia": "2.x",
    "renderer": "react"
  },
  "files": [
    {
      "path": "config/inertia-agent-kit.php",
      "kind": "config",
      "action": "created",
      "sourceControlled": true
    },
    {
      "path": ".iak/manifest/iak.manifest.v1.json",
      "kind": "manifest",
      "action": "created",
      "sourceControlled": false
    }
  ],
  "manifest": {
    "schema": "iak.manifest.v1",
    "path": ".iak/manifest/iak.manifest.v1.json",
    "status": "valid"
  },
  "boost": {
    "installed": true,
    "status": "available",
    "nextAction": null
  },
  "artifacts": {
    "runtimeConfig": {
      "kind": "json",
      "path": ".iak/config.json"
    },
    "initState": {
      "kind": "json",
      "path": ".iak/state/init.json"
    }
  },
  "nextActions": [
    {
      "type": "run",
      "summary": "Check package setup and local conventions.",
      "command": "php artisan iak:doctor --json"
    }
  ],
  "errors": [],
  "meta": {
    "createdAt": "2026-05-22T00:00:00Z",
    "iakVersion": "0.1.0",
    "adapter": "laravel-inertia-react"
  }
}
```

Failure uses the same schema with `event: "iak.init.failed.v1"`,
`status: "failed"`, empty or partial `files`, and populated `errors`.

File actions are one of `created`, `updated`, `unchanged`, `skipped`, or
`failed`.

## Boost Interaction

`iak:init` detects Boost but does not install, update, or edit Boost resources.

If Boost is installed:

- include `boost.installed: true` in runtime config, manifest, and command
  output;
- point agent-facing rules to Boost for Laravel docs, app info, logs, browser
  logs, database/schema tools, absolute URLs, and baseline MCP setup;
- keep IAK rules focused on Inertia/frontend workflow, manifests, scaffolding,
  audit, feedback, verify, and handoff.

If Boost is missing:

- init may still complete with `boost.installed: false`;
- output should include a non-failing next action:
  `composer require laravel/boost --dev && php artisan boost:install`;
- do not generate replacement generic Laravel MCP or documentation tooling.

## Non-Goals

- No PHP implementation in this planning wave.
- No MCP server implementation.
- No resource scaffold output.
- No Storybook addon output.
- No Pest Browser or Playwright execution.
- No Spatie Data or Wayfinder generation.
- No editing Boost-owned files.
- No frontend app code creation.
- No migration or deletion of the Node prototype.

## Future Implementation Sequence

1. Create `InitCommand` under `src/Console` and register it from the
   auto-discovered service provider only when running in console.
2. Add a small command result builder shared by future commands so `--json`
   always produces one object with schema, event, status, files, artifacts,
   next actions, errors, and meta.
3. Implement project detection for Laravel version, Inertia package, renderer
   adapter, TypeScript presence, Boost presence, and project root.
4. Implement config writers for `config/inertia-agent-kit.php` and
   `iak.config.json` with preserve-by-default behavior and generated hash
   markers.
5. Implement `.iak/` directory creation and runtime state writers.
6. Implement manifest skeleton builder from resolved config and detection
   results.
7. Implement optional rules writer under `.iak/rules/` without editing global
   agent files.
8. Add schema export/copy for `iak.init.result.v1` and `iak.manifest.v1`.
9. Wire structured failures before any partial write where possible; when
   partial writes happen, include them in `files` and `errors`.
10. Keep all large or verbose details as artifact paths, not stdout payloads.

## Pest And Testbench Acceptance Tests

Tests should use Pest with Orchestra Testbench.

Required acceptance tests:

- registers `iak:init` in a Testbench app;
- `php artisan iak:init --json` exits `0`, emits parseable JSON, and emits no
  non-JSON stdout;
- JSON contains `schema: "iak.init.result.v1"` and
  `event: "iak.init.completed.v1"`;
- creates `config/inertia-agent-kit.php` when absent;
- creates `iak.config.json` when absent;
- creates `.iak/config.json`, `.iak/state/init.json`,
  `.iak/manifest/iak.manifest.v1.json`, `.iak/schemas/*`, `.iak/feedback/`,
  `.iak/runs/`, and `.iak/rules/inertia-agent-kit.md`;
- manifest skeleton validates required slices: project, adapter, conventions,
  commands, schemas, boost, artifacts;
- second run is idempotent and reports source-controlled config as unchanged;
- existing user-edited config is preserved without `--force`;
- `--force` refreshes generated `.iak/` artifacts but does not overwrite
  user-owned config without a generated ownership marker;
- `IAK_AGENT=1 php artisan iak:init` emits the same JSON shape without
  requiring `--json`;
- unsupported `--adapter=vue` in v1 exits `2` with
  `event: "iak.init.failed.v1"`;
- missing Inertia dependency exits `3` with structured JSON and no frontend app
  code writes;
- Boost installed and Boost missing scenarios are both represented in JSON
  without editing `CLAUDE.md`, `AGENTS.md`, `.mcp.json`, or `boost.json`;
- persisted JSON paths are project-relative and do not contain absolute local
  machine paths.

## Implementation Notes

The init command should favor deterministic files and small payloads. Later
commands can rely on `.iak/config.json` and the manifest instead of rescanning
the full repository at every step.
