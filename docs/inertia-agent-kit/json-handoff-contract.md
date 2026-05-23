# JSON Handoff Contract

Inertia Agent Kit (IAK) workflows are JSON-first for agents. Human prose is a
presentation layer; persisted JSON artifacts are the source of truth for plans,
audits, feedback, verification, and handoffs.

This contract is for command output, run artifacts, package-provided Boost
guidelines, and any IAK MCP tools. Implementations may use Zod or another
runtime validator internally, but must publish JSON Schema for every versioned
schema in this document.

## Goals

- Keep agent context small and resumable.
- Make command output stable enough for non-TS tools to validate.
- Pass artifact paths and IDs between steps instead of embedding large data.
- Require evidence before handoff or feedback resolution.
- Integrate with Laravel Boost for generic Laravel awareness without
  duplicating Boost's tools.

## Agent Mode

`IAK_AGENT=1` means the caller is an automated agent and token budget matters.
It is equivalent to passing `--json` to every command that supports JSON output.

When `IAK_AGENT=1` is set:

- Commands default to JSON output on stdout.
- Commands must not write prose, spinners, ANSI color, progress bars, or tables
  to stdout.
- Human-readable diagnostics may go to stderr, but they must be concise.
- Commands must not prompt interactively. Missing required input is a structured
  error.
- Commands must not fall back to text for unsupported operations. They should
  return `iak.error.v1` or the command's schema with `status: "failed"`.
- Output should be compact by default. Pretty JSON requires `--pretty`.
- Streaming output is opt-in with `--stream-json`; regular `--json` returns one
  final JSON object.

Exit codes:

| Code | Meaning |
| --- | --- |
| `0` | The command completed and the JSON `status` is non-failing. |
| `1` | The command ran and found actionable failures, such as audit violations. |
| `2` | Usage, config, schema, or validation error. |
| `3` | Environment error, missing dependency, missing server, or unavailable route. |
| `4` | Unexpected internal error. |

The JSON body is still required for non-zero exits whenever the process can
produce one.

## Command Contract

Every command intended for agent workflows must support `--json`.

| Command | Schema | Purpose |
| --- | --- | --- |
| `php artisan iak:init --json` | `iak.manifest.v1` | Install or inspect project config and emit the resolved manifest. |
| `php artisan iak:manifest --json` | `iak.manifest.v1` | Read conventions, tokens, components, resources, commands, and schema locations. |
| `php artisan iak:plan --json` | `iak.plan.v1` | Generate a compact file plan before implementation. |
| `php artisan iak:plan validate <path> --json` | `iak.plan.v1` | Validate an agent-produced file plan. |
| `php artisan iak:audit --json` | `iak.audit.v1` | Report architecture, type, story, and style violations. |
| `php artisan iak:verify --json` | `iak.verify.v1` | Run verification and summarize evidence. |
| `php artisan iak:feedback list --json` | `iak.feedback.v1` | List pending or filtered feedback records. |
| `php artisan iak:feedback show <id> --json` | `iak.feedback.v1` | Return one feedback record and artifact references. |
| `php artisan iak:feedback resolve <id> --evidence=<path> --json` | `iak.feedback.v1` | Resolve feedback only when evidence validates. |
| `php artisan iak:handoff create --json` | `iak.handoff.v1` | Create the final handoff artifact for the current run. |
| `php artisan iak:handoff validate <path> --json` | `iak.handoff.v1` | Validate a handoff artifact before final response. |

All paths in JSON output are relative to the Laravel project root unless the
field is explicitly a URL. Commands must include `projectRoot` only in local
machine outputs where an absolute path is useful; persisted artifacts should
prefer relative paths.

## Required Agent Loop

An agent changing UI follows this loop:

1. Read `iak.manifest.v1`.
2. Produce or validate `iak.plan.v1`.
3. Scaffold files with IAK commands when available.
4. Implement within the approved file plan.
5. Run typecheck, lint, and `php artisan iak:audit --json`.
6. Run Storybook tests where applicable.
7. Run Pest Browser or Playwright for browser-visible changes.
8. Inspect screenshot and console artifacts.
9. Resolve human-in-the-loop feedback only with evidence.
10. Create `iak.handoff.v1` with `php artisan iak:handoff create ... --json`
    and validate it with `php artisan iak:handoff validate <path> --json`.

The handoff is invalid if it omits audit result, test result, Storybook story
ID or inspected app URL, screenshot path, browser console result, accessibility
result when available, unresolved feedback count, or changed files grouped by
role.

## Token Budget

Agents should pass summaries, IDs, and artifact references through chat. They
should not paste logs, DOM dumps, screenshots, generated types, or whole file
contents unless a tool specifically asks for the content.

Default size limits:

| Field | Limit |
| --- | --- |
| `summary` | 500 characters |
| `errors[].message` | 500 characters |
| `violations[].message` | 500 characters |
| `nextActions[].summary` | 300 characters |
| `notes[]` | 300 characters each |
| `feedback.items[].message` | 1000 characters |
| Embedded JSON artifact summary | 1000 characters |

Commands may expose `--max-items`, `--max-bytes`, or `--since-run` to reduce
payload size. If output is truncated, include:

```json
{
  "truncated": true,
  "totalItems": 42,
  "returnedItems": 10,
  "artifacts": {
    "full": {
      "kind": "json",
      "path": ".iak/runs/run_01j/full-audit.json"
    }
  }
}
```

## Artifact References

Artifacts store verbose evidence under `.iak/runs/*` or `.iak/feedback/*`.
JSON handoffs reference artifacts by path instead of embedding content.

Canonical artifact reference:

```json
{
  "kind": "screenshot",
  "path": ".iak/runs/run_01j/screenshots/vehicles-index.png",
  "mime": "image/png",
  "sizeBytes": 184221,
  "sha256": "7e8e9c8b3f4f5d7f2b5d7c0e5e4a7a9e8f2c1b0a8d7e6f5c4b3a291817161514",
  "summary": "Vehicle index desktop screenshot after audit fixes."
}
```

Rules:

- `path` is required and must remain inside `.iak/runs/` or `.iak/feedback/`
  unless the artifact is a project file already listed in `changedFiles`.
- `kind` is one of `json`, `log`, `screenshot`, `dom`, `html`, `video`,
  `trace`, `diff`, `schema`, `report`, or `other`.
- `mime`, `sizeBytes`, and `sha256` should be present for persisted evidence.
- Base64 screenshots, DOM dumps, console logs, generated types, and large test
  output are prohibited in normal JSON handoffs.
- Artifact maps may be nested, but leaf values must be artifact references.
- Missing artifacts are validation failures when referenced by required
  evidence.

## Common Fields

Every handoff-critical schema includes these fields where applicable:

```json
{
  "schema": "iak.example.v1",
  "id": "obj_01j",
  "runId": "run_01j",
  "status": "passed",
  "summary": "One sentence result.",
  "artifacts": {},
  "nextActions": [],
  "errors": [],
  "meta": {
    "createdAt": "2026-05-22T15:00:00Z",
    "iakVersion": "0.1.0",
    "adapter": "laravel-inertia-react"
  }
}
```

Status values are schema-specific but should come from this set when possible:
`planned`, `valid`, `invalid`, `pending`, `resolved`, `passed`, `failed`,
`blocked`, `completed`, `skipped`.

Error shape:

```json
{
  "code": "manifest.missing_config",
  "message": "Could not find .iak/config.json.",
  "file": ".iak/config.json",
  "line": null,
  "details": {}
}
```

Next action shape:

```json
{
  "type": "fix",
  "summary": "Replace raw hex with semantic token.",
  "rule": "style.raw_hex",
  "file": "resources/js/features/vehicles/vehicle-table.tsx",
  "line": 42,
  "command": "iak audit --json"
}
```

## `iak.manifest.v1`

The manifest is the first document an agent reads. It summarizes conventions
and points to detailed resources without requiring a repo scan.

Required fields:

- `schema`
- `id`
- `status`
- `project`
- `adapter`
- `conventions`
- `tokens`
- `components`
- `resources`
- `commands`
- `schemas`
- `boost`
- `artifacts`

Example:

```json
{
  "schema": "iak.manifest.v1",
  "id": "manifest_01j",
  "status": "valid",
  "summary": "Laravel Inertia React project with IAK conventions installed.",
  "project": {
    "name": "fleet",
    "root": ".",
    "laravel": "12.x",
    "inertia": "2.x",
    "renderer": "react",
    "typescript": true
  },
  "adapter": {
    "id": "laravel-inertia-react",
    "package": "@inertia-agent-kit/adapter-react",
    "version": "0.1.0"
  },
  "conventions": {
    "pagesAreRouteAdapters": true,
    "featureRoot": "resources/js/features",
    "pageRoot": "resources/js/pages",
    "generatedTypes": "@/types/generated",
    "resourceControllerPattern": "{Resource}Controller"
  },
  "tokens": {
    "policy": "semantic-ds-utilities-only",
    "artifacts": {
      "catalog": {
        "kind": "json",
        "path": ".iak/manifest/tokens.json"
      }
    }
  },
  "components": {
    "primitiveStoryRequired": true,
    "appStoryRequired": true,
    "artifacts": {
      "contracts": {
        "kind": "json",
        "path": ".iak/manifest/components.json"
      }
    }
  },
  "resources": [
    {
      "name": "vehicles",
      "controller": "VehicleController",
      "routes": ["vehicles.index", "vehicles.show"]
    }
  ],
  "commands": {
    "plan": "php artisan iak:plan --json",
    "audit": "php artisan iak:audit --json",
    "verify": "php artisan iak:verify --json",
    "handoffCreate": "php artisan iak:handoff create --json",
    "handoffValidate": "php artisan iak:handoff validate <path> --json"
  },
  "schemas": {
    "plan": ".iak/schemas/iak.plan.v1.schema.json",
    "audit": ".iak/schemas/iak.audit.v1.schema.json",
    "feedback": ".iak/schemas/iak.feedback.v1.schema.json",
    "verify": ".iak/schemas/iak.verify.v1.schema.json",
    "handoff": ".iak/schemas/iak.handoff.v1.schema.json",
    "manifest": ".iak/schemas/iak.manifest.v1.schema.json"
  },
  "boost": {
    "installed": true,
    "guidelines": "resources/boost/guidelines/core.blade.php",
    "skills": [
      "inertia-resource-development",
      "inertia-browser-verification"
    ],
    "useBoostFor": [
      "laravel_docs",
      "app_info",
      "absolute_urls",
      "browser_logs",
      "database_schema",
      "log_entries"
    ],
    "useIakFor": [
      "manifest",
      "tokens",
      "components",
      "audit",
      "verify",
      "feedback",
      "handoff"
    ]
  },
  "artifacts": {}
}
```

Manifest rules:

- Agents read the manifest instead of scanning the whole repo.
- Boost-owned context should be represented as capability names or artifact
  references, not copied into the manifest.
- Component and token catalogs may be separate JSON artifacts referenced by the
  manifest.

## `iak.plan.v1`

The plan records the intended file changes before implementation. It is small
enough to review in chat and strict enough for `iak plan validate`.

Required fields:

- `schema`
- `id`
- `status`
- `task`
- `files`
- `checks`
- `artifacts`
- `nextActions`
- `errors`

Example:

```json
{
  "schema": "iak.plan.v1",
  "id": "plan_01j",
  "status": "planned",
  "task": "Create vehicle index page",
  "resource": "vehicles",
  "controller": "VehicleController@index",
  "route": "vehicles.index",
  "files": [
    {
      "path": "resources/js/pages/vehicles/index.tsx",
      "role": "page",
      "action": "create"
    },
    {
      "path": "resources/js/features/vehicles/vehicle-table.tsx",
      "role": "feature",
      "action": "create"
    },
    {
      "path": "resources/js/features/vehicles/vehicle-table.stories.tsx",
      "role": "story",
      "action": "create"
    }
  ],
  "imports": [
    {
      "from": "@/types/generated/data",
      "symbols": ["App"]
    }
  ],
  "checks": [
    "page_is_thin",
    "generated_types",
    "required_stories",
    "ds_tokens",
    "browser_verify"
  ],
  "assumptions": [
    "Vehicle data shape is available from generated Spatie Data types."
  ],
  "artifacts": {},
  "nextActions": [
    {
      "type": "run",
      "summary": "Validate plan before editing.",
      "command": "iak plan validate .iak/runs/run_01j/plan.json --json"
    }
  ],
  "errors": []
}
```

Plan rules:

- `files[].role` is one of `page`, `feature`, `primitive`, `app-component`,
  `story`, `test`, `type`, `route`, `controller`, `config`, `docs`, or
  `other`.
- `files[].action` is one of `create`, `modify`, `delete`, `rename`, or
  `inspect`.
- Plans should include generated type imports by path and symbol, never copied
  type definitions.
- `iak plan validate` may change `status` to `valid` or `invalid` and append
  rule violations as `errors`.

## `iak.audit.v1`

The audit schema is terse and action-oriented. Agents recover from rule IDs,
locations, and suggested fixes better than prose.

Required fields:

- `schema`
- `runId`
- `status`
- `summary`
- `checks`
- `violations`
- `artifacts`
- `nextActions`
- `errors`

Example:

```json
{
  "schema": "iak.audit.v1",
  "runId": "run_01j",
  "status": "failed",
  "summary": "Audit failed: 2 style violations and 1 missing story.",
  "checks": [
    {
      "id": "style.raw_hex",
      "status": "failed",
      "summary": "Raw hex found outside token files."
    },
    {
      "id": "stories.required",
      "status": "failed",
      "summary": "Feature component is missing a story."
    }
  ],
  "violations": [
    {
      "rule": "style.raw_hex",
      "severity": "error",
      "file": "resources/js/features/vehicles/vehicle-table.tsx",
      "line": 42,
      "column": 18,
      "message": "Raw hex is not allowed outside token files.",
      "suggestion": {
        "kind": "replace",
        "current": "#ffffff",
        "preferred": "bg-ds-surface"
      }
    }
  ],
  "artifacts": {
    "full": {
      "kind": "json",
      "path": ".iak/runs/run_01j/audit.json"
    }
  },
  "nextActions": [
    {
      "type": "fix",
      "summary": "Replace raw hex with a semantic design-system utility.",
      "rule": "style.raw_hex",
      "file": "resources/js/features/vehicles/vehicle-table.tsx",
      "line": 42
    }
  ],
  "errors": []
}
```

Audit rules:

- `violations[].rule` is stable and namespaced.
- `severity` is `error`, `warning`, or `info`.
- `file`, `line`, and `column` should be present whenever the violation maps
  to source.
- Suggestions must be machine-readable where possible.

## `iak.feedback.v1`

Feedback commands return an envelope with feedback records. The same schema is
used for list, show, and resolve operations.

Required fields:

- `schema`
- `status`
- `items`
- `artifacts`
- `nextActions`
- `errors`

Example:

```json
{
  "schema": "iak.feedback.v1",
  "status": "pending",
  "summary": "1 pending feedback item.",
  "items": [
    {
      "id": "fbk_01j",
      "status": "pending",
      "surface": "app",
      "source": "human",
      "target": {
        "url": "http://localhost:5173/invoices",
        "route": "invoices.index",
        "storyId": "features-invoices-invoicetable--default",
        "selector": "[data-iak-part='filter-bar']",
        "coordinates": {
          "x": 412,
          "y": 188
        }
      },
      "viewport": {
        "width": 1440,
        "height": 900,
        "name": "desktop"
      },
      "message": "This should reuse the standard filter bar pattern.",
      "attachments": {
        "screenshot": {
          "kind": "screenshot",
          "path": ".iak/feedback/fbk_01j/screenshot.png",
          "mime": "image/png"
        },
        "dom": {
          "kind": "dom",
          "path": ".iak/feedback/fbk_01j/dom.html",
          "mime": "text/html"
        },
        "console": {
          "kind": "json",
          "path": ".iak/feedback/fbk_01j/console.json",
          "mime": "application/json"
        }
      },
      "context": {
        "gitSha": "abc123",
        "adapter": "laravel-inertia-react",
        "componentCandidates": ["FilterBar", "InvoiceFilters"]
      },
      "resolution": null,
      "createdAt": "2026-05-22T14:30:00Z"
    }
  ],
  "artifacts": {},
  "nextActions": [
    {
      "type": "fix",
      "summary": "Resolve feedback with evidence from a verify run.",
      "command": "iak feedback resolve fbk_01j --evidence .iak/runs/run_01j/handoff.json --json"
    }
  ],
  "errors": []
}
```

Resolution shape:

```json
{
  "status": "resolved",
  "summary": "Reused the standard filter bar and verified the page.",
  "resolvedAt": "2026-05-22T15:10:00Z",
  "evidence": {
    "handoff": {
      "kind": "json",
      "path": ".iak/runs/run_01j/handoff.json"
    },
    "screenshot": {
      "kind": "screenshot",
      "path": ".iak/runs/run_01j/screenshots/invoices-index.png"
    },
    "audit": {
      "kind": "json",
      "path": ".iak/runs/run_01j/audit.json"
    },
    "tests": {
      "kind": "json",
      "path": ".iak/runs/run_01j/tests.json"
    }
  },
  "changedFiles": [
    {
      "path": "resources/js/features/invoices/invoice-filters.tsx",
      "role": "feature"
    }
  ]
}
```

Feedback rules:

- Agents cannot resolve feedback without evidence.
- Resolution evidence must include changed files, commands run, post-fix
  screenshot, audit result, and test or browser verification result.
- Attachments are artifact references, not embedded blobs.
- HTTP and MCP feedback surfaces must return this schema or a strict subset
  with the same field names.

## `iak.verify.v1`

Verification combines audit, tests, Storybook, browser inspection, console
logs, accessibility, and feedback status into one run result.

Required fields:

- `schema`
- `runId`
- `status`
- `summary`
- `checks`
- `evidence`
- `artifacts`
- `nextActions`
- `errors`

Example:

```json
{
  "schema": "iak.verify.v1",
  "runId": "run_01j",
  "status": "passed",
  "summary": "Audit, tests, Storybook, browser, console, and feedback checks passed.",
  "checks": [
    {
      "id": "audit",
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/audit.json"
      }
    },
    {
      "id": "tests",
      "status": "passed",
      "command": "npm test -- --runInBand",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/tests.json"
      }
    },
    {
      "id": "storybook",
      "status": "passed",
      "storyId": "features-vehicles-vehicletable--default"
    },
    {
      "id": "browser",
      "status": "passed",
      "url": "http://localhost:8000/vehicles",
      "artifact": {
        "kind": "screenshot",
        "path": ".iak/runs/run_01j/screenshots/vehicles-index.png"
      }
    },
    {
      "id": "console",
      "status": "passed",
      "errorCount": 0,
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/console.json"
      }
    },
    {
      "id": "accessibility",
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/a11y.json"
      }
    },
    {
      "id": "feedback",
      "status": "passed",
      "unresolved": 0
    }
  ],
  "evidence": {
    "audit": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/audit.json"
      }
    },
    "tests": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/tests.json"
      }
    },
    "browser": {
      "url": "http://localhost:8000/vehicles",
      "screenshot": {
        "kind": "screenshot",
        "path": ".iak/runs/run_01j/screenshots/vehicles-index.png"
      },
      "consoleErrors": 0,
      "accessibility": "passed"
    },
    "feedback": {
      "unresolved": 0
    }
  },
  "artifacts": {
    "verify": {
      "kind": "json",
      "path": ".iak/runs/run_01j/verify.json"
    }
  },
  "nextActions": [],
  "errors": []
}
```

Verify rules:

- Browser-visible changes require a browser check with app URL or Storybook
  story ID, screenshot artifact, console result, and accessibility result when
  available.
- `status: "passed"` is allowed only when all required checks pass or are
  explicitly `skipped` with a reason.
- Console and accessibility details should live in artifacts.
- Boost may provide browser logs, app URLs, and Laravel diagnostics; IAK should
  reference those outputs or artifacts instead of copying them.

## `iak.handoff.v1`

The handoff is the final source of truth for agent-to-agent continuation. A
human final response can summarize it, but the handoff artifact is canonical.

First-port creation and validation use the Laravel package command:

```bash
php artisan iak:handoff create --task="..." --changed-file=feature:modify:resources/js/features/vehicles/vehicle-table.tsx --verify=.iak/runs/<run-id>/verify.json --tests=.iak/runs/<run-id>/tests.json --json
php artisan iak:handoff validate .iak/runs/<run-id>/handoff.json --json
```

Required fields:

- `schema`
- `runId`
- `task`
- `status`
- `changedFiles`
- `evidence`
- `artifacts`
- `notes`
- `nextActions`
- `errors`

Example:

```json
{
  "schema": "iak.handoff.v1",
  "runId": "run_01j",
  "task": "Create vehicle index page",
  "status": "completed",
  "summary": "Vehicle index page implemented and verified.",
  "changedFiles": {
    "page": [
      {
        "path": "resources/js/pages/vehicles/index.tsx",
        "action": "create"
      }
    ],
    "feature": [
      {
        "path": "resources/js/features/vehicles/vehicle-table.tsx",
        "action": "create"
      }
    ],
    "story": [
      {
        "path": "resources/js/features/vehicles/vehicle-table.stories.tsx",
        "action": "create"
      }
    ]
  },
  "evidence": {
    "plan": {
      "status": "valid",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/plan.json"
      }
    },
    "audit": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/audit.json"
      }
    },
    "tests": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/tests.json"
      }
    },
    "storybook": {
      "status": "passed",
      "storyId": "features-vehicles-vehicletable--default"
    },
    "browser": {
      "url": "http://localhost:8000/vehicles",
      "screenshot": {
        "kind": "screenshot",
        "path": ".iak/runs/run_01j/screenshots/vehicles-index.png"
      },
      "consoleErrors": 0,
      "accessibility": "passed"
    },
    "feedback": {
      "unresolved": 0
    }
  },
  "artifacts": {
    "handoff": {
      "kind": "json",
      "path": ".iak/runs/run_01j/handoff.json"
    },
    "verify": {
      "kind": "json",
      "path": ".iak/runs/run_01j/verify.json"
    }
  },
  "notes": [],
  "nextActions": [],
  "errors": []
}
```

Handoff validation rules:

- `schema` must be `iak.handoff.v1`.
- `runId`, `task`, `status`, and `changedFiles` are required.
- `changedFiles` must be an object grouped by role.
- `changedFiles` must not be empty for completed implementation work.
- Every changed file entry must include `path` and `action`.
- Changed file group keys must use the role vocabulary from `iak.plan.v1`.
- Required evidence must include audit, tests, feedback unresolved count, and
  either Storybook story ID or browser app URL.
- Browser-visible changes require screenshot artifact, console result, and
  accessibility result when available.
- `evidence.feedback.unresolved` must be present even when zero.
- `audit.status` and `tests.status` must be present and must not be `failed`
  for `status: "completed"`.
- Referenced artifact paths must exist, stay within allowed artifact roots, and
  include artifact kind.
- Freeform `notes` must respect token budget limits.
- `nextActions` must be empty for `status: "completed"` unless they are
  explicitly non-blocking follow-ups.
- `errors` must be empty for `status: "completed"`.

Validation command:

```bash
php artisan iak:handoff validate .iak/runs/<run-id>/handoff.json --json
```

Validation failure example:

```json
{
  "schema": "iak.handoff.v1",
  "runId": "run_01j",
  "status": "failed",
  "summary": "Handoff validation failed: browser screenshot is missing.",
  "changedFiles": {},
  "evidence": {},
  "artifacts": {},
  "notes": [],
  "nextActions": [
    {
      "type": "fix",
      "summary": "Run browser verification and attach a screenshot artifact.",
      "command": "php artisan iak:verify --json"
    }
  ],
  "errors": [
    {
      "code": "handoff.browser.screenshot_missing",
      "message": "Browser-visible changes require evidence.browser.screenshot."
    }
  ]
}
```

## Laravel Package And Boost Integration

IAK should ship as a Laravel package for Laravel-aware workflows and use
Laravel Boost as the generic agent substrate.

Recommended install flow:

```txt
composer require fbarrento/inertia-agent-kit --dev
php artisan iak:init --json
php artisan boost:install
php artisan boost:update
```

IAK package responsibilities:

- Publish config and `.iak/config.json`.
- Detect the Inertia renderer.
- Publish or verify token and component conventions.
- Register dev-only feedback routes.
- Provide package Boost guidelines and skills.
- Publish JSON Schemas for this contract.
- Create and validate final `iak.handoff.v1` artifacts through
  `php artisan iak:handoff ... --json`.
- Expose IAK-specific MCP tools when needed.

Package architecture during the flat refactor:

- Support Laravel 12 and 13 only.
- `src/Actions/*` classes have one responsibility, one public `handle()` method,
  and constructor-injected dependencies.
- `src/Data/*` classes are JSON schema output objects that implement
  `JsonSerializable` and preserve the exact versioned contract.
- `src/Enum/*` owns fixed vocabularies; do not duplicate private string const
  lists for roles, actions, statuses, artifact kinds, or schema names.
- `src/Console/*` stays thin and delegates validation, file IO, schema
  assembly, and persistence to actions.
- `src/Support/*` contains generic helpers only.
- Refactor readiness requires PHPStan at max level and Rector dry-run in
  addition to focused package tests.

Boost responsibilities:

- Laravel, package, and version-aware documentation.
- App info, absolute URL generation, browser logs, database/schema inspection,
  last error, and log entries.
- Common MCP setup through `php artisan boost:mcp`.

IAK-specific MCP tools should be limited to the domain layer:

```txt
iak_manifest_read()
iak_conventions_read()
iak_tokens_list()
iak_components_list()
iak_component_get(name)
iak_audit_run()
iak_verify_run()
iak_feedback_list_pending()
iak_feedback_get(id)
iak_feedback_get_screenshot(id)
iak_feedback_resolve(id, evidence)
```

JSON rule: Boost context may be referenced by capability, tool result ID, or
artifact path. Do not copy large Boost outputs into IAK handoffs.

## Compatibility

Schema versions are immutable. Breaking changes require a new schema name, such
as `iak.handoff.v2`.

Compatible changes within a version:

- Adding optional fields.
- Adding enum values only when validators are documented to ignore unknown
  values.
- Adding new artifact kinds when consumers preserve unknown kinds.
- Adding new checks, rule IDs, or command names.

Incompatible changes:

- Removing required fields.
- Renaming fields.
- Changing field types.
- Changing the meaning of `status`.
- Requiring embedded content where a reference was previously accepted.

Consumers must preserve unknown object fields when passing artifacts forward.
Validators may warn on unknown fields but should not fail unless strict mode is
requested.
