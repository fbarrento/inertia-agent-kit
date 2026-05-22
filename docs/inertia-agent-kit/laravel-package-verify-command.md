# Laravel Package Verify Command

Status: planning spec for the Laravel package port
Owner: Inertia Agent Kit
Package target: `fbarrento/inertia-agent-kit`
Public command: `php artisan iak:verify --json`

## Purpose

`iak:verify` is the first Laravel package verification gate for IAK handoff and
feedback resolution. The first port does not run browser automation. It verifies
that static convention evidence is current, feedback is not blocking the chosen
scope, and the run has a durable `iak.verify.v1` artifact that later browser and
Storybook integrations can extend without changing the command contract.

The first port verifies only:

- `iak.audit.v1` evidence by running `iak:audit` or consuming a supplied audit
  artifact;
- `iak.feedback.v1` records under `.iak/feedback/`;
- artifact writing under `.iak/runs/<run-id>/`;
- screenshot, browser, and Storybook metadata placeholders.

## Command Contract

Signature:

```txt
php artisan iak:verify
  {--json : Emit one machine-readable JSON object}
  {--pretty : Pretty-print JSON when --json or IAK_AGENT=1 is active}
  {--run-id= : Optional verify run id for deterministic tests}
  {--config= : Optional config path, default config/inertia-agent-kit.php}
  {--audit= : Optional project-relative path to an existing iak.audit.v1 artifact}
  {--feedback=* : Feedback id in scope; repeatable or comma-separated}
  {--route=* : Route name in scope; repeatable or comma-separated}
  {--url=* : URL in scope; repeatable or comma-separated}
  {--story=* : Storybook story id in scope; repeatable or comma-separated}
  {--resource=* : Resource name in scope; repeatable or comma-separated}
  {--changed-files= : Optional JSON file with changed file entries}
```

`IAK_AGENT=1` is equivalent to `--json`. In JSON mode stdout contains only the
final JSON object. Concise human diagnostics may go to stderr.

Exit codes:

| Code | Meaning |
| --- | --- |
| `0` | Verify completed and `status` is `passed`. |
| `1` | Verify completed and `status` is `failed`. |
| `2` | Usage, config, manifest, schema, validation, or stale supplied artifact error. |
| `3` | Environment or filesystem error, including unreadable paths or unwritable artifacts. |
| `4` | Unexpected internal error. |

The JSON event is `iak.verify.completed`; the schema/version is
`iak.verify.v1` with `version: 1`. Non-zero exits should still write JSON and
the verify artifact whenever the command can build a reliable result.

## Audit Evidence

Verify obtains audit evidence in one of two ways.

When `--audit` is omitted, verify runs:

```txt
php artisan iak:audit --json --run-id=<verify-run-id> --config=<config>
```

The audit command writes `.iak/runs/<verify-run-id>/audit.json`; verify records
that path as both audit evidence and a command result. Audit exit `0` maps to a
passing audit check. Audit exit `1` maps to a failed verify result. Audit exits
`2`, `3`, or `4`, or audit JSON with `status: "blocked"`, map to blocked
verify.

When `--audit=.iak/runs/<audit-run-id>/audit.json` is provided, verify must not
rerun scanners. It validates the supplied JSON before using it:

- `schema` is `iak.audit.v1`;
- `event` is `iak.audit.completed`;
- `version` is `1`;
- `status`, `totals`, `checks[]`, `violations[]`, `artifacts.audit.path`, and
  `meta.configHash` are present;
- the artifact path is project-relative, inside `.iak/runs/`, and exists;
- `meta.configHash` equals the current resolved verify config hash.

A supplied audit artifact is stale when its `meta.configHash` differs from the
current config hash, the referenced artifact path is missing, or the artifact
lacks a hash and is older than the resolved config/runtime snapshot. Stale or
invalid supplied artifacts produce `status: "blocked"`, exit `2`, and an error
code such as `audit.stale_artifact` or `audit.schema_invalid`. To refresh audit
evidence, omit `--audit`.

Verify never rewrites audit rule ids, severities, locations, suggestions, or
fingerprints. It copies audit summaries into verify evidence and carries audit
`nextActions[]` forward.

## Feedback Evidence

Verify reads canonical feedback records from:

```txt
.iak/feedback/<id>/record.json
```

If `.iak/feedback/` is absent, feedback evidence is empty and passing. Derived
indexes may be used for speed, but `record.json` is authoritative.

Related records are selected by scope:

- explicit `--feedback` ids are always related;
- `target.route`, `target.url`, `target.storyId`, and `target.selector` match
  route, URL, story, and selector scope;
- `context.componentCandidates`, route/story naming, changed-file paths, and
  resource names may infer resource relation;
- when no scope flags or changed files are provided, the first port treats all
  records as related.

Blocking rules:

- `pending` and `in_progress` related records block verify.
- `resolved`, `wont_fix`, and `duplicate` records do not block only when their
  `resolution` object validates.
- Invalid resolved records are treated as unresolved and block verify.
- `duplicate` records must point at an existing record.
- `iak:verify --feedback=<id>` is the evidence-preparation path for resolving
  that item. The target id is included in `scope.feedback` and `feedback.target`,
  but it is excluded from unresolved counts. Other related unresolved records
  still block verify.

Verify writes feedback summaries under the run:

```txt
.iak/runs/<run-id>/feedback/related.json
.iak/runs/<run-id>/feedback/unresolved.json
```

The JSON output reports counts and ids, not attachment contents.

## Run Artifact

Each verify run writes:

```txt
.iak/runs/<run-id>/verify.json
```

`<run-id>` should be `run_<ulid>` in normal use. Tests may pass `--run-id`.
Persisted paths are project-relative POSIX paths. The artifact content must
match stdout in JSON mode, except stdout may be pretty-printed.

Minimal shape:

```json
{
  "schema": "iak.verify.v1",
  "event": "iak.verify.completed",
  "version": 1,
  "command": "iak:verify",
  "runId": "run_01j",
  "status": "passed",
  "summary": "Verify passed: audit passed and no related feedback is unresolved.",
  "mode": "first-port",
  "scope": {
    "changedFiles": [
      {
        "path": "resources/js/features/vehicles/vehicle-table.tsx",
        "role": "feature",
        "action": "modify"
      }
    ],
    "routes": ["vehicles.index"],
    "urls": [],
    "stories": ["features-vehicles-vehicletable--default"],
    "resources": ["vehicles"],
    "feedback": ["fbk_01j"]
  },
  "checks": [
    {
      "id": "audit",
      "status": "passed",
      "command": "php artisan iak:audit --json --run-id=run_01j",
      "durationMs": 842,
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/audit.json",
        "schema": "iak.audit.v1"
      },
      "totals": {
        "errors": 0,
        "warnings": 0,
        "violations": 0
      }
    },
    {
      "id": "feedback",
      "status": "passed",
      "related": 1,
      "unresolved": 0,
      "invalidResolved": 0,
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/feedback/unresolved.json"
      }
    },
    {
      "id": "browser",
      "status": "skipped",
      "reason": "first_port_no_browser_execution",
      "executor": null
    },
    {
      "id": "storybook",
      "status": "skipped",
      "reason": "first_port_no_storybook_execution"
    }
  ],
  "evidence": {
    "audit": {
      "status": "passed",
      "runId": "run_01j",
      "configHash": "sha256:...",
      "violations": 0,
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/audit.json",
        "schema": "iak.audit.v1"
      }
    },
    "feedback": {
      "related": 1,
      "unresolved": 0,
      "invalidResolved": 0,
      "target": "fbk_01j",
      "ids": [],
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/feedback/unresolved.json"
      }
    },
    "browser": {
      "status": "skipped",
      "executor": null,
      "targets": [
        {
          "route": "vehicles.index",
          "url": null,
          "status": "placeholder",
          "viewport": {
            "name": "desktop",
            "width": 1440,
            "height": 900
          },
          "screenshot": {
            "kind": "screenshot",
            "path": null,
            "status": "placeholder",
            "capture": "not_run",
            "required": false
          },
          "consoleErrors": null,
          "accessibility": "not_run"
        }
      ]
    },
    "storybook": {
      "status": "skipped",
      "stories": [
        {
          "storyId": "features-vehicles-vehicletable--default",
          "status": "placeholder",
          "screenshot": {
            "kind": "screenshot",
            "path": null,
            "status": "placeholder",
            "capture": "not_run",
            "required": false
          },
          "consoleErrors": null,
          "accessibility": "not_run"
        }
      ]
    },
    "screenshots": {
      "status": "placeholder",
      "items": [],
      "artifact": {
        "kind": "json",
        "path": ".iak/runs/run_01j/screenshots/metadata.json"
      }
    }
  },
  "changedFiles": [
    {
      "path": "resources/js/features/vehicles/vehicle-table.tsx",
      "role": "feature",
      "action": "modify"
    }
  ],
  "commandsRun": [
    {
      "cmd": "php artisan iak:verify --feedback=fbk_01j --json",
      "exitCode": 0
    }
  ],
  "artifacts": {
    "verify": {
      "kind": "json",
      "path": ".iak/runs/run_01j/verify.json",
      "schema": "iak.verify.v1"
    },
    "audit": {
      "kind": "json",
      "path": ".iak/runs/run_01j/audit.json",
      "schema": "iak.audit.v1"
    },
    "feedback": {
      "kind": "json",
      "path": ".iak/runs/run_01j/feedback/unresolved.json"
    },
    "screenshots": {
      "kind": "json",
      "path": ".iak/runs/run_01j/screenshots/metadata.json"
    }
  },
  "nextActions": [],
  "errors": [],
  "meta": {
    "createdAt": "2026-05-22T15:00:00Z",
    "finishedAt": "2026-05-22T15:00:03Z",
    "package": "fbarrento/inertia-agent-kit",
    "iakVersion": "0.1.0",
    "adapter": "laravel-inertia-react",
    "configHash": "sha256:...",
    "browserExecution": "not_implemented"
  }
}
```

`screenshots/metadata.json` should use the same placeholder entries as
`evidence.browser.targets[].screenshot` and `evidence.storybook.stories[]`.
The first port may write no image files. Placeholder screenshots prove only
that the evidence shape is wired; they are not visual proof.

## Status Semantics

Top-level `status` is one of `passed`, `failed`, or `blocked`.

`passed` means:

- audit evidence is valid, fresh, and `status: "passed"`;
- no related feedback remains unresolved, excluding the explicit
  `--feedback=<id>` target when preparing resolution evidence;
- all required verify artifacts were written;
- browser, Storybook, and screenshot metadata are present as first-port
  placeholders.

`failed` means verify ran and found actionable failures:

- audit evidence has `status: "failed"`;
- related feedback has unresolved `pending` or `in_progress` records;
- related closed feedback has invalid resolution evidence.

`blocked` means verify could not build reliable evidence:

- config, manifest, or schema validation failed;
- a supplied audit artifact is invalid or stale;
- `--feedback=<id>` references a missing record;
- `.iak/runs/<run-id>/verify.json` or related artifacts cannot be written;
- `iak:audit` could not run or returned blocked output.

`skipped` is allowed only inside `checks[]` and evidence subobjects. It is the
required first-port status for browser and Storybook execution.

## Feedback Resolution Evidence

`php artisan iak:feedback resolve {id} --evidence=.iak/runs/<run-id>/verify.json
--json` can consume `iak.verify.v1` when all of these are true:

- `schema` is `iak.verify.v1`, `event` is `iak.verify.completed`, and
  `status` is `passed`;
- `scope.feedback` includes the target id;
- `evidence.feedback.target` equals the target id and
  `evidence.feedback.unresolved` is `0`;
- `evidence.audit.status` is `passed` and points to a valid `iak.audit.v1`
  artifact;
- `changedFiles[]` is non-empty for `status: "resolved"`;
- `commandsRun[]` includes the successful verify command;
- app and Storybook screenshot entries exist when the feedback surface needs
  visual evidence.

For the first port, screenshot entries may be placeholders with
`capture: "not_run"` and `meta.browserExecution: "not_implemented"`. The
feedback resolver must preserve that fact in copied resolution evidence so
later browser-enabled ports can distinguish placeholder evidence from captured
visual proof.

Verify reports readiness only. It never mutates `.iak/feedback/*/record.json`
and never calls `iak:feedback resolve`.

## Pest/Testbench Acceptance Tests

Use Pest with Orchestra Testbench package tests. Tests should create a temporary
Laravel app filesystem, seed `.iak/runs` and `.iak/feedback` as needed, call the
Artisan command, and assert stdout JSON plus persisted artifacts.

Required tests:

1. Clean audit plus empty feedback passes, exits `0`, emits
   `schema: iak.verify.v1`, `event: iak.verify.completed`, writes
   `.iak/runs/<run-id>/verify.json`, and includes placeholder browser,
   Storybook, and screenshot metadata.
2. When `--audit` is omitted, verify invokes `iak:audit --json` with the verify
   run id and records `.iak/runs/<run-id>/audit.json`.
3. A fresh supplied `iak.audit.v1` artifact is consumed without rerunning audit.
4. A supplied audit artifact with mismatched `meta.configHash`, missing
   `artifacts.audit.path`, invalid schema, or missing file exits `2` with
   `status: blocked`.
5. Audit `status: failed` exits `1`, copies audit totals and next actions, and
   leaves audit violations referenced by artifact path.
6. Audit `status: blocked` or an audit environment failure exits non-zero with
   top-level `status: blocked`.
7. Related `pending` and `in_progress` feedback records fail verify and write
   `feedback/related.json` and `feedback/unresolved.json`.
8. `iak:verify --feedback=fbk_01j --json` excludes `fbk_01j` from unresolved
   counts but still fails on any other related unresolved record.
9. Valid `resolved`, `wont_fix`, and `duplicate` records do not block; invalid
   closed records are counted as `invalidResolved` and fail verify.
10. A missing explicit feedback id exits `2` with `status: blocked` and a
    stable `feedback.not_found` error.
11. `IAK_AGENT=1` defaults to JSON output, and `--pretty` changes formatting
    only.
12. JSON mode writes exactly one object to stdout with no prose, ANSI, tables,
    prompts, or progress output.

Do not add assertions that require real Pest Browser, Playwright, Storybook
preview/test-runner, browser console capture, accessibility runners, or MCP.

## Non-Goals

- No real Pest Browser execution in this first verify port.
- No Playwright execution or Playwright project discovery.
- No Storybook build, preview server, test-runner, addon, or story screenshot
  execution.
- No MCP tools, MCP resources, or Solo behavior.
- No Brand OS sync/audit gate.
- No handoff artifact composition beyond making `verify.json` consumable by
  later `iak.handoff.v1` work.
- No autofix or feedback mutation; `iak:feedback resolve` remains a separate
  explicit command.
