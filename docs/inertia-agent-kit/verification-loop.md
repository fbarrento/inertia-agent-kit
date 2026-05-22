# Outcome Verification Loop

Status: draft spec  
Date: 2026-05-22  
Schema family: `iak.verify.v1`  
Owned surface: `iak verify`, run artifacts, and handoff evidence requirements.

## Purpose

`iak verify` is the outcome verification orchestrator for Inertia Agent Kit. It
does not replace Pest Browser, Playwright, Storybook, `iak audit`, the feedback
queue, Brand OS checks, or Laravel Boost diagnostics. It runs or references
those lower-level tools, captures their outputs as durable artifacts, and
returns one compact JSON result that an agent can use for handoff.

The command answers one question:

```txt
Is this UI change backed by enough evidence to hand off safely?
```

The answer is only `passed` when required checks have run, browser-visible
outcomes were inspected, unresolved related feedback is accounted for, and the
run artifact contains paths to screenshots, console logs, accessibility
results, audit output, test output, and any Brand OS sync evidence required by
the manifest.

## Non-Goals

- Do not reimplement browser automation. Use Pest Browser first for Laravel
  apps, Playwright where configured, and Storybook's test runner or Playwright
  integration for stories.
- Do not duplicate `iak audit --json`. Run it, store its result, and summarize
  status in `iak.verify.v1`.
- Do not duplicate the feedback protocol. Read and validate
  `iak.feedback.v1` records and unresolved counts.
- Do not duplicate Laravel Boost. Use Boost for app URLs, browser logs, app
  info, last errors, logs, docs, and other generic Laravel facts.
- Do not embed screenshots, DOM dumps, traces, large logs, generated types, or
  Brand OS source artifacts in JSON output. Store files and return references.

## Inputs

The verifier reads structured inputs before it scans source files:

1. `iak.manifest.v1`, usually from the manifest path declared by
   `iak.config.json`.
2. Optional `iak.plan.v1` from `--plan`.
3. Optional changed-file list from `--changed-files`.
4. Story manifest, component manifest, and token manifest artifact references
   from `iak.manifest.v1`.
5. `.iak/feedback/*` records through the feedback protocol.
6. `.iak/brand.lock.json` when the manifest's brand slice is enabled.
7. Configured project commands for lint, typecheck, unit tests, browser tests,
   Storybook tests, audit, brand sync, and handoff validation.

All persisted paths in verify output are project-relative POSIX paths. Absolute
paths are allowed only in local debug metadata and must not be required by an
agent handoff.

## Command Surface

Every verify invocation supports `--json`, `--pretty`, and `IAK_AGENT=1`.
Agent mode must produce one final JSON object on stdout, no tables, no spinners,
and no interactive prompts.

Recommended command shapes:

```txt
iak verify --json
iak verify --mode focused --changed-files .iak/runs/run_01j/changed-files.json --json
iak verify --mode full --json
iak verify --mode ci --json
iak verify --mode handoff --plan .iak/runs/run_01j/plan.json --json
iak verify --surface storybook --stories features-vehicles-vehicletable--default --json
iak verify --surface app --route vehicles.index --browser pest --json
iak verify --surface app --url http://localhost:8000/vehicles --browser playwright --json
iak verify --mode feedback --feedback fbk_01j --json
```

### Modes

| Mode | Purpose | Required behavior |
| --- | --- | --- |
| `auto` | Default. Infer scope from changed files, plan, feedback, and manifest. | Run focused checks when scope is known; fall back to full audit plus configured smoke checks. |
| `focused` | Verify the changed resources, routes, stories, selectors, and files. | Requires `--changed-files`, `--plan`, `--route`, `--stories`, `--url`, or `--feedback`. |
| `full` | Verify the project-wide IAK contract. | Run full audit, configured test suites, required Storybook checks, feedback validation, and brand checks. |
| `ci` | Non-interactive CI verification. | Same JSON rules as agent mode. Missing servers, stale brand state, unresolved related feedback, and configured warnings-as-errors fail. |
| `handoff` | Strict gate before `iak.handoff.v1`. | Requires changed-file grouping, audit, tests, relevant Storybook or browser evidence, feedback count, and brand evidence when enabled. |
| `feedback` | Verify evidence for resolving one or more feedback records. | Requires post-fix screenshot evidence for app or Storybook feedback and validates resolution prerequisites. |

### Scope Flags

| Flag | Meaning |
| --- | --- |
| `--surface app|storybook|all` | Limit browser execution to routed app pages, Storybook stories, or both. |
| `--browser auto|pest|playwright` | Select browser executor. `auto` prefers Pest Browser in Laravel apps and falls back to configured Playwright. |
| `--route <name>` | Verify one Inertia route by manifest/Boost-resolved URL. Repeatable. |
| `--url <url>` | Verify one concrete app URL. Repeatable. |
| `--stories <storyId>` | Verify one or more Storybook story IDs. Repeatable or comma-separated. |
| `--feedback <id>` | Verify the route, story, selector, and artifacts related to a feedback record. Repeatable. |
| `--changed-files <path>` | Read a JSON array or object of changed paths with roles/actions. |
| `--plan <path>` | Read an `iak.plan.v1` file and infer required checks. |
| `--run-id <id>` | Reuse an existing run id. Fails if the run directory is locked by another process. |
| `--update-baseline` | Explicitly update configured visual or trace baselines when the project supports them. Never implied by agent mode. |
| `--skip <check-id>` | Skip an optional check only when config allows it. Required checks may not be skipped for handoff mode. |

## Verification Flow

`iak verify` runs this loop:

1. Resolve manifest, adapter, commands, schemas, and token budget.
2. Create or lock `.iak/runs/<run-id>/`.
3. Persist invocation metadata and input scope.
4. Classify changed files by role, resource, route, story, and browser
   visibility.
5. Run configured static checks: typecheck, lint, and `iak audit --json`.
6. Run Storybook verification for changed or required stories.
7. Run Pest Browser or Playwright for route-visible changes.
8. Capture screenshots, console logs, DOM snapshots, traces, videos, and
   accessibility reports when the executor can provide them.
9. Read pending/in-progress feedback records related to changed routes,
   stories, selectors, component candidates, or resources.
10. Run Brand OS sync/audit checks when brand support is enabled.
11. Normalize all results into `iak.verify.v1`.
12. Write `.iak/runs/<run-id>/verify.json`.
13. Optionally write `.iak/runs/<run-id>/handoff.json` when `--mode handoff`
    or `iak handoff create` is configured to compose from the verify result.

Every subprocess result is recorded with command, exit code, duration, status,
artifact path, and truncated summary. Large raw outputs go to files under the
run directory.

## Tool Orchestration

### Audit Integration

`iak verify` always runs or consumes `iak audit --json` unless the manifest says
audit is disabled for the project. The audit output remains the source of truth
for rule violations.

Required behavior:

- write the audit result to `.iak/runs/<run-id>/audit/audit.json`;
- mirror a stable convenience path at `.iak/runs/<run-id>/audit.json` when
  useful for older handoff examples;
- pass focused changed-file input to audit when available;
- fail verify when audit status is `failed` or `blocked`;
- preserve audit `nextActions[]` in verify `nextActions[]`;
- never rewrite audit rule ids, locations, severities, or suggestions.

Focused audit invocation example:

```txt
iak audit --json --changed-files .iak/runs/run_01j/input/changed-files.json
```

### Pest Browser Execution

Pest Browser is the preferred app-page browser executor for Laravel apps. It
owns real Laravel/Inertia flows: routed page rendering, form submission,
validation, authorization, redirects, flash/session behavior, and server-owned
copy/formatting.

`iak verify --browser pest` should:

- use Boost or configured commands to resolve absolute URLs for route names;
- run configured Pest Browser tests for affected routes or flows;
- pass run id and artifact root to the test environment where possible;
- collect screenshots, console logs, DOM snapshots, videos, traces, and a11y
  reports emitted by the Pest Browser layer;
- record each route, test file, test name, viewport, and artifact reference;
- fail when browser tests fail, routes cannot be resolved, screenshots are
  missing for browser-visible changes, or console errors are unallowlisted.

Pest Browser should not be forced for component-only Storybook changes unless
the component change is route-visible or handoff mode requires an app-page
smoke check.

### Playwright Execution

Playwright is the lower-level browser executor and cross-adapter fallback. It
is valid when:

- the app does not use Pest Browser;
- Storybook screenshots or interactions are easier through Playwright;
- the adapter supplies Playwright specs for React, Vue, or Svelte;
- a focused URL or story verification needs direct browser control.

`iak verify --browser playwright` should:

- run configured Playwright projects and grep/scope when provided;
- capture screenshots after the page or story reaches a stable ready marker;
- capture console entries, page errors, failed network requests, traces, and
  videos when configured;
- run `axe` or the configured a11y integration when available;
- normalize results into the same `browser` or `storybook` evidence shape used
  by Pest Browser and Storybook.

Playwright must not become a second audit engine. Convention findings remain
owned by `iak audit`.

### Storybook Execution

Storybook verification is required when changed files affect:

- `components/ui/*` primitives;
- `components/app/*` reusable app components;
- eligible `features/<resource>/*` components;
- story files, fixture files, component contracts, tokens, themes, bridge CSS,
  or Storybook decorators;
- feedback records whose surface is `storybook`.

`iak verify --surface storybook` should:

- validate story presence and required states through audit or story manifest
  extraction;
- run Storybook build or preview smoke checks when configured;
- run Storybook test runner for changed and required stories;
- execute `play` functions and interaction tests when present;
- capture screenshots per story ID and viewport;
- capture console output per story ID;
- run accessibility checks when configured;
- verify Storybook preview uses IAK token/theme CSS and adapter decorators;
- record active brand status when Brand OS support is enabled.

A passing Storybook check proves component-state evidence. It does not prove a
Laravel route works. Browser-visible route changes still require app-page
evidence through Pest Browser or Playwright.

### Feedback Checks

`iak verify` reads feedback through the canonical `iak.feedback.v1` protocol.
It does not define a second queue.

Required behavior:

- list pending and in-progress feedback records;
- in focused mode, block on records related to changed routes, stories,
  selectors, component candidates, resources, or feedback ids in scope;
- in full and CI modes, validate all feedback records and report unrelated
  pending records according to project policy;
- fail handoff mode when `evidence.feedback.unresolved` is absent;
- fail handoff mode when related unresolved feedback count is greater than
  zero;
- validate that resolved, duplicate, and `wont_fix` records have required
  evidence or reasons;
- include feedback artifact references, not embedded feedback attachments.

Feedback resolution remains a separate command:

```txt
iak feedback resolve <id> --evidence .iak/runs/<run-id>/handoff.json --json
```

The verify result can be used as evidence, but verify must not close feedback
records on its own unless the caller invokes an explicit feedback command.

### Brand Sync And Audit

When the manifest brand slice is enabled, verify must include brand evidence.

Required behavior:

- run `iak brand sync --check --json` or consume an equivalent brand status
  artifact;
- run `iak brand audit --json` or include brand findings from `iak audit`;
- include `.iak/brand.lock.json` status;
- include active brand name in Storybook and browser evidence when available;
- fail CI and handoff modes when brand status is `stale`, `failed`, or the
  lock/artifacts are missing;
- warn or fail visual Storybook changes without brand-rendered evidence
  according to project policy;
- include unresolved brand-related feedback counts.

Verification evidence shape:

```json
{
  "brand": {
    "status": "current",
    "brand": "Acme Coffee",
    "lock": ".iak/brand.lock.json",
    "sync": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/brand/sync.json"
      }
    },
    "audit": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/brand/audit.json"
      }
    },
    "feedback": {
      "unresolvedBrandItems": 0
    }
  }
}
```

## Capture Requirements

Browser-visible changes require artifact-backed inspection. A passing verify
result is invalid without the applicable artifacts below.

### Screenshots

Capture screenshots for:

- every app URL or route in scope;
- every changed or feedback-linked Storybook story ID in scope;
- every failing browser or Storybook check when the runner can capture a page;
- every feedback resolution that affects app or Storybook UI.

Screenshot artifact references include kind, path, mime, size, sha256,
viewport, target URL or story ID, and capture timestamp.

### Console

Capture browser console output for every app URL or story inspected.

Console artifacts should include:

- `entries[]` with level, message, source, timestamp, URL, and location when
  available;
- `errorCount`, `warningCount`, and `allowlistedCount`;
- page errors and unhandled promise rejections;
- failed network requests when the executor exposes them.

`status: "passed"` is invalid when unallowlisted console errors exist.
Warnings may pass unless project policy promotes them.

### Accessibility

Accessibility checks are required when the project config or manifest exposes
an a11y runner. They are recorded as `skipped` only when no runner is installed
or the route/story cannot support automated a11y inspection and the skip reason
is explicit.

Accessibility artifacts should include:

- runner name and version;
- target URL or story ID;
- viewport;
- violation counts by severity;
- rule ids and short summaries;
- full report path.

Handoff mode requires `accessibility` status for browser-visible changes. The
status may be `skipped` only with a reason.

### DOM, Traces, And Videos

DOM snapshots, traces, videos, HAR files, and network logs are optional unless
needed to diagnose a failure or required by project config. They should be
captured on failure by default and referenced from `artifacts`.

## Run Directory Layout

Each verify run writes to:

```txt
.iak/runs/<run-id>/
  run.json
  verify.json
  handoff.json                    # optional, produced by handoff mode or handoff command
  input/
    manifest.json                 # compact copy or reference metadata
    plan.json                     # when provided
    changed-files.json            # normalized changed files
    scope.json                    # routes, urls, stories, feedback ids
  commands/
    commands.jsonl                # subprocess command, exit, duration, artifact refs
  audit/
    audit.json
  tests/
    typecheck.json
    lint.json
    unit.json
    pest-browser.json
    playwright.json
  app/
    routes.json
    screenshots/
      vehicles-index.desktop.png
      vehicles-index.mobile.png
    console/
      vehicles-index.console.json
    dom/
      vehicles-index.html
    a11y/
      vehicles-index.a11y.json
    traces/
    videos/
  storybook/
    stories.json
    screenshots/
      features-vehicles-vehicletable--default.desktop.png
    console/
      features-vehicles-vehicletable--default.console.json
    dom/
      features-vehicles-vehicletable--default.html
    a11y/
      features-vehicles-vehicletable--default.a11y.json
    traces/
  feedback/
    related.json
    unresolved.json
    resolutions.json
  brand/
    sync.json
    audit.json
  logs/
    stdout.log
    stderr.log
```

Rules:

- `run.json` records invocation metadata, started/finished timestamps,
  adapter, package version, project root hash if configured, and lock status.
- `verify.json` is the canonical `iak.verify.v1` artifact.
- `commands/commands.jsonl` records every subprocess in append-only order.
- Artifact paths referenced by JSON must stay inside `.iak/runs/<run-id>/` or
  `.iak/feedback/*`, unless they are project files listed in `changedFiles`.
- The run directory is append-only while the verifier is active. A rerun should
  create a new run id unless `--run-id` is explicitly passed and the old run is
  not locked.
- Large logs are capped in JSON summaries and stored as files.

## `iak.verify.v1`

`iak verify --json` emits one object:

```json
{
  "schema": "iak.verify.v1",
  "runId": "run_01j",
  "status": "passed",
  "summary": "Audit, tests, Storybook, browser, console, accessibility, feedback, and brand checks passed.",
  "mode": "focused",
  "scope": {
    "changedFiles": [
      {
        "path": "resources/js/features/vehicles/vehicle-table.tsx",
        "role": "feature",
        "action": "modify"
      }
    ],
    "routes": ["vehicles.index"],
    "urls": ["http://localhost:8000/vehicles"],
    "stories": ["features-vehicles-vehicletable--default"],
    "feedback": []
  },
  "checks": [
    {
      "id": "audit",
      "status": "passed",
      "command": "iak audit --json --changed-files .iak/runs/run_01j/input/changed-files.json",
      "durationMs": 842,
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/audit/audit.json",
        "mime": "application/json"
      }
    },
    {
      "id": "typecheck",
      "status": "passed",
      "command": "npm run typecheck -- --pretty false",
      "durationMs": 2110,
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/tests/typecheck.json"
      }
    },
    {
      "id": "storybook",
      "status": "passed",
      "stories": [
        {
          "storyId": "features-vehicles-vehicletable--default",
          "status": "passed",
          "viewports": ["desktop"],
          "artifacts": {
            "screenshot": {
              "kind": "screenshot",
              "path": ".iak/runs/run_01j/storybook/screenshots/features-vehicles-vehicletable--default.desktop.png",
              "mime": "image/png"
            },
            "console": {
              "kind": "json",
              "path": ".iak/runs/run_01j/storybook/console/features-vehicles-vehicletable--default.console.json"
            },
            "accessibility": {
              "kind": "json",
              "path": ".iak/runs/run_01j/storybook/a11y/features-vehicles-vehicletable--default.a11y.json"
            }
          }
        }
      ]
    },
    {
      "id": "browser",
      "status": "passed",
      "executor": "pest-browser",
      "targets": [
        {
          "route": "vehicles.index",
          "url": "http://localhost:8000/vehicles",
          "viewport": {
            "name": "desktop",
            "width": 1440,
            "height": 900
          },
          "status": "passed",
          "artifacts": {
            "screenshot": {
              "kind": "screenshot",
              "path": ".iak/runs/run_01j/app/screenshots/vehicles-index.desktop.png",
              "mime": "image/png"
            },
            "console": {
              "kind": "json",
              "path": ".iak/runs/run_01j/app/console/vehicles-index.console.json"
            },
            "accessibility": {
              "kind": "json",
              "path": ".iak/runs/run_01j/app/a11y/vehicles-index.a11y.json"
            }
          }
        }
      ]
    },
    {
      "id": "feedback",
      "status": "passed",
      "unresolved": 0,
      "related": 0,
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/feedback/unresolved.json"
      }
    },
    {
      "id": "brand",
      "status": "passed",
      "brand": "Acme Coffee",
      "syncStatus": "current",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/brand/sync.json"
      }
    }
  ],
  "evidence": {
    "audit": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/audit/audit.json"
      }
    },
    "tests": {
      "status": "passed",
      "artifacts": {
        "typecheck": {
          "kind": "json",
          "path": ".iak/runs/run_01j/tests/typecheck.json"
        },
        "pestBrowser": {
          "kind": "json",
          "path": ".iak/runs/run_01j/tests/pest-browser.json"
        }
      }
    },
    "storybook": {
      "status": "passed",
      "stories": [
        {
          "storyId": "features-vehicles-vehicletable--default",
          "status": "passed",
          "screenshot": {
            "kind": "screenshot",
            "path": ".iak/runs/run_01j/storybook/screenshots/features-vehicles-vehicletable--default.desktop.png"
          },
          "consoleErrors": 0,
          "accessibility": "passed"
        }
      ]
    },
    "browser": {
      "status": "passed",
      "targets": [
        {
          "route": "vehicles.index",
          "url": "http://localhost:8000/vehicles",
          "screenshot": {
            "kind": "screenshot",
            "path": ".iak/runs/run_01j/app/screenshots/vehicles-index.desktop.png"
          },
          "consoleErrors": 0,
          "accessibility": "passed"
        }
      ]
    },
    "feedback": {
      "unresolved": 0,
      "related": 0,
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/feedback/unresolved.json"
      }
    },
    "brand": {
      "status": "current",
      "brand": "Acme Coffee",
      "lock": ".iak/brand.lock.json",
      "audit": {
        "status": "passed",
        "artifact": {
          "kind": "json",
          "path": ".iak/runs/run_01j/brand/audit.json"
        }
      },
      "feedback": {
        "unresolvedBrandItems": 0
      }
    }
  },
  "artifacts": {
    "run": {
      "kind": "json",
      "path": ".iak/runs/run_01j/run.json"
    },
    "verify": {
      "kind": "json",
      "path": ".iak/runs/run_01j/verify.json"
    },
    "commands": {
      "kind": "log",
      "path": ".iak/runs/run_01j/commands/commands.jsonl"
    }
  },
  "nextActions": [],
  "errors": [],
  "meta": {
    "createdAt": "2026-05-22T15:00:00Z",
    "finishedAt": "2026-05-22T15:01:12Z",
    "iakVersion": "0.1.0",
    "adapter": "laravel-inertia-react"
  }
}
```

### Required Fields

- `schema`: always `iak.verify.v1`.
- `runId`: stable run id, usually `run_<ulid>`.
- `status`: `passed`, `failed`, or `blocked`.
- `summary`: short result summary capped by the JSON handoff contract.
- `mode`: command mode used for the run.
- `scope`: normalized changed files, routes, URLs, stories, and feedback ids.
- `checks[]`: normalized check summaries with artifact references.
- `evidence`: handoff-ready evidence grouped by audit, tests, Storybook,
  browser, feedback, and brand where applicable.
- `artifacts`: top-level run artifact references.
- `nextActions[]`: repair actions when status is not `passed`.
- `errors[]`: structured errors when a check cannot run or evidence is invalid.
- `meta`: created timestamp, IAK version, and adapter.

### Status Semantics

`passed` means all required checks for the selected mode and scope completed
successfully, required artifacts exist, and no related unresolved feedback
blocks handoff.

`failed` means verification ran and found actionable failures, such as audit
errors, test failures, console errors, a11y violations, stale brand state,
missing required stories, or unresolved related feedback.

`blocked` means the verifier could not produce reliable evidence because the
environment was unavailable, config or manifest was invalid, a required server
could not start, routes or stories could not be resolved, or artifacts could
not be written.

`skipped` is allowed only inside `checks[]`, never as the top-level verify
status. A skipped check requires `reason` and may still cause top-level
`failed` in handoff or CI mode when the check is mandatory.

## Failure Semantics

Exit codes follow the JSON handoff contract:

| Exit | Meaning |
| ---: | --- |
| `0` | Verify completed and top-level status is `passed`. |
| `1` | Verify completed and top-level status is `failed`. |
| `2` | Usage, config, manifest, schema, or validation error. |
| `3` | Environment error, missing dependency, missing server, unresolved route/story, or unavailable browser executor. |
| `4` | Unexpected internal error. |

The JSON body is still required for non-zero exits whenever possible.

Failures should create `nextActions[]` entries. Examples:

- audit failure: include audit rule id, file, line, and audit artifact path;
- test failure: include failing command, test file/name, and report artifact;
- missing screenshot: suggest rerunning browser verification with the target
  route/story;
- console error: include console artifact path and error count;
- a11y failure: include report path and top rule ids;
- unresolved feedback: include feedback ids and command to inspect them;
- stale brand: include `iak brand sync --check --json` artifact and suggested
  `iak brand sync --json`;
- blocked server: include command or manifest capability needed to start it.

`status: "passed"` is invalid when:

- `evidence.audit.status` is missing or not `passed`;
- configured typecheck/lint/test evidence is missing;
- browser-visible changes lack app URL or Storybook story ID evidence;
- required screenshots are missing;
- console results are missing for inspected browser surfaces;
- accessibility status is missing for browser-visible changes when a runner is
  configured;
- related unresolved feedback count is absent or greater than zero in handoff
  mode;
- brand support is enabled and brand status is missing, stale, failed, or
  unaudited;
- referenced artifacts are missing or outside allowed artifact roots.

## Handoff Evidence Requirements

`iak.handoff.v1` may summarize or reference `iak.verify.v1`, but it must still
contain the handoff fields required by the JSON handoff contract.

For completed UI work, handoff validation requires:

- changed files grouped by role;
- audit status and audit artifact;
- test status and test artifact;
- Storybook story ID and status for reusable UI changes;
- app URL or route evidence for browser-visible route changes;
- screenshot artifact for every inspected app URL or story;
- console error count and console artifact;
- accessibility result when available, or explicit skip reason;
- unresolved feedback count, even when zero;
- Brand OS status and brand audit/sync artifact when brand support is enabled;
- `verify` artifact path pointing to `.iak/runs/<run-id>/verify.json`.

Minimum handoff evidence slice:

```json
{
  "evidence": {
    "audit": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/audit/audit.json"
      }
    },
    "tests": {
      "status": "passed",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/tests/pest-browser.json"
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
        "path": ".iak/runs/run_01j/app/screenshots/vehicles-index.desktop.png"
      },
      "consoleErrors": 0,
      "accessibility": "passed"
    },
    "feedback": {
      "unresolved": 0
    },
    "brand": {
      "status": "current",
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/brand/sync.json"
      }
    }
  },
  "artifacts": {
    "verify": {
      "kind": "json",
      "path": ".iak/runs/run_01j/verify.json"
    }
  }
}
```

`iak handoff validate .iak/runs/<run-id>/handoff.json --json` should read the
verify artifact and fail when the handoff claims evidence that verify did not
produce or reference.

## Handoff And Feedback Resolution

Feedback resolution can link either to `verify.json` or `handoff.json`, but
handoff is preferred because it includes changed files grouped by role.

Valid feedback resolution evidence must include:

- changed files;
- commands run;
- audit result;
- test or browser verification result;
- post-fix screenshot for app or Storybook feedback;
- Storybook story result when resolving Storybook feedback;
- app URL result when the fix affects routed behavior;
- linked verify or handoff artifact;
- resolver identity.

`iak verify --feedback <id>` validates whether the current run has enough
evidence to resolve that record. It reports readiness; it does not call
`iak feedback resolve` automatically.

## Implementation Order

1. **Schema and run writer.** Publish `iak.verify.v1` JSON Schema, implement
   run id generation, run directory locking, `run.json`, `commands.jsonl`, and
   stable artifact reference helpers.
2. **Command shell.** Add `iak verify --json`, `--pretty`, `IAK_AGENT=1`,
   command modes, scope flags, exit codes, and structured `iak.error.v1`
   fallback behavior.
3. **Manifest and scope resolver.** Read `iak.manifest.v1`, `iak.config.json`,
   optional plan, changed files, routes, URLs, stories, feedback ids, and brand
   state. Classify changes by role, resource, and browser visibility.
4. **Audit integration.** Run `iak audit --json`, store the artifact, map
   audit status to verify checks, and preserve audit next actions.
5. **Static test adapters.** Add configurable typecheck, lint, and unit test
   command runners with JSON/log artifact capture.
6. **Storybook verification.** Integrate Storybook build/smoke checks, test
   runner execution, story ID selection, screenshots, console capture, a11y
   capture, and story manifest freshness checks.
7. **Pest Browser adapter.** Add Laravel route resolution through Boost or
   configured commands, Pest Browser execution, artifact collection, console
   normalization, and route-target evidence.
8. **Playwright adapter.** Add fallback app-page execution and Storybook
   screenshot/interaction capture using the same normalized artifact shapes.
9. **Feedback gate.** Read `.iak/feedback/*`, validate related unresolved
   records, validate resolution evidence for resolved records, and write
   feedback artifacts under the run.
10. **Brand gate.** Run `iak brand sync --check --json` and brand audit when
    enabled, write brand artifacts, and enforce stale/failure policy in CI and
    handoff modes.
11. **Handoff composition.** Let `iak handoff create` or `iak verify --mode
    handoff` compose `iak.handoff.v1` from verify evidence and validate it.
12. **Failure fixtures.** Add fixtures for audit failure, missing Storybook
    story, Storybook console error, Pest Browser failure, Playwright route
    failure, missing screenshot, a11y violation, unresolved feedback, stale
    brand lock, blocked server, and invalid artifact path.
13. **CI hardening.** Add deterministic output ordering, artifact hashing,
    strict schema validation, config-driven warning promotion, and tests that
    assert both exit code and JSON shape.

## Open Questions

- Whether `iak verify --mode auto` should start dev servers itself or only use
  configured already-running services in v1.
- Whether Pest Browser should be required for Laravel apps by default or only
  preferred when installed.
- Which accessibility runner should be the default recommendation for app
  pages and for Storybook stories.
- Whether visual diff baselines belong in `iak verify` v1 or should remain a
  later optional extension.
- How much Boost tool output should be persisted as artifacts versus referenced
  by tool result id when Boost exposes stable artifact handles.
