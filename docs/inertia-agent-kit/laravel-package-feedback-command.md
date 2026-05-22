# Laravel Feedback Artisan Command

Status: planning spec for Phase 5, todo #23
Package target: `fbarrento/inertia-agent-kit`
Owned surface: `php artisan iak:feedback ... --json`

## Purpose

Port the feedback queue from the Node prototype into the Laravel package as a
local-first Artisan surface. The command family reads and writes canonical
`iak.feedback.v1` records under `.iak/feedback/` and gives agents a stable JSON
contract for listing, inspecting, and resolving HITL feedback.

This lane does not build the Storybook addon, browser overlay, MCP tools, or
HTTP feedback routes. It only defines the Laravel package command and storage
contract those producers will use later.

## Command Contract

The Laravel package exposes one Artisan command with subactions:

```txt
php artisan iak:feedback list [--status=pending|in_progress|resolved|wont_fix|duplicate|all] [--surface=app|storybook|test] [--source=human|agent|test] [--limit=50] [--json] [--pretty]
php artisan iak:feedback show {id} [--json] [--pretty]
php artisan iak:feedback resolve {id} --evidence=.iak/runs/<run-id>/verify.json [--summary="..."] [--json] [--pretty]
```

Rules:

- `--json` emits exactly one JSON object on stdout and no prose, tables,
  spinners, or ANSI control sequences.
- `IAK_AGENT=1` is equivalent to `--json`.
- `--pretty` is valid only with JSON output and controls formatting, not shape.
- `list` defaults to `--status=pending`; `--status=all` returns every status.
- `list` sorts by `createdAt` descending, then `id` descending for stable tests.
- Human output may be terse, but implementation tests should target JSON first.
- `list`, `show`, and `resolve` are the v1 command family. Record creation is
  owned by later HTTP, Storybook, browser overlay, and test-runner producers
  that use the same store service.

Exit codes:

| Exit | Meaning |
| ---: | --- |
| `0` | Command completed successfully. |
| `1` | Command ran but the requested state transition is invalid, such as evidence that does not prove resolution. |
| `2` | Usage, missing record, invalid schema, or invalid input path. |
| `3` | Environment, filesystem, permissions, or lock failure. |
| `4` | Unexpected internal error. |

Errors use the JSON handoff error shape:

```json
{
  "schema": "iak.error.v1",
  "status": "failed",
  "error": {
    "code": "feedback.not_found",
    "message": "Feedback record fbk_01j was not found.",
    "file": ".iak/feedback/fbk_01j/record.json",
    "line": null,
    "details": {}
  }
}
```

## JSON Output Shapes

`list --json` emits `iak.feedback.list.v1`:

```json
{
  "schema": "iak.feedback.list.v1",
  "status": "passed",
  "filters": {
    "status": "pending",
    "surface": null,
    "source": null,
    "limit": 50
  },
  "counts": {
    "total": 2,
    "returned": 2,
    "pending": 1,
    "inProgress": 0,
    "resolved": 1,
    "wontFix": 0,
    "duplicate": 0
  },
  "items": [
    {
      "id": "fbk_01j",
      "status": "pending",
      "surface": "app",
      "source": "human",
      "producer": "iak.app-overlay",
      "message": "This should reuse the standard filter bar pattern.",
      "target": {
        "url": "http://localhost:8000/vehicles",
        "route": "vehicles.index",
        "storyId": null,
        "selector": "[data-iak-part='filter-bar']"
      },
      "attachments": {
        "screenshot": ".iak/feedback/fbk_01j/screenshot.png",
        "dom": ".iak/feedback/fbk_01j/dom.html",
        "console": ".iak/feedback/fbk_01j/console.json"
      },
      "createdAt": "2026-05-22T15:00:00Z",
      "updatedAt": "2026-05-22T15:00:00Z"
    }
  ],
  "artifacts": {
    "store": {
      "kind": "json",
      "path": ".iak/feedback"
    }
  },
  "errors": []
}
```

`show --json` emits `iak.feedback.show.v1` and includes the full canonical
record:

```json
{
  "schema": "iak.feedback.show.v1",
  "status": "passed",
  "record": {
    "schema": "iak.feedback.v1",
    "id": "fbk_01j",
    "status": "pending"
  },
  "artifacts": {
    "record": {
      "kind": "json",
      "path": ".iak/feedback/fbk_01j/record.json"
    }
  },
  "errors": []
}
```

`resolve --json` emits `iak.feedback.resolve.v1` and returns the updated record:

```json
{
  "schema": "iak.feedback.resolve.v1",
  "status": "resolved",
  "record": {
    "schema": "iak.feedback.v1",
    "id": "fbk_01j",
    "status": "resolved"
  },
  "resolution": {
    "schema": "iak.feedback.resolution.v1",
    "summary": "Reused the shared FilterBar component.",
    "linkedEvidence": ".iak/runs/run_01j/verify.json",
    "evidenceCopiedTo": ".iak/feedback/fbk_01j/resolution/evidence.json"
  },
  "artifacts": {
    "record": {
      "kind": "json",
      "path": ".iak/feedback/fbk_01j/record.json"
    },
    "evidence": {
      "kind": "json",
      "path": ".iak/feedback/fbk_01j/resolution/evidence.json"
    }
  },
  "errors": []
}
```

Implementation detail: the examples above abbreviate nested records where the
shape is already defined below. Real command output must include complete
objects for `record` and `resolution`.

## Feedback Record Schema

`record.json` is the source of truth:

```json
{
  "schema": "iak.feedback.v1",
  "id": "fbk_01j",
  "status": "pending",
  "surface": "app",
  "source": "human",
  "producer": "iak.app-overlay",
  "target": {
    "url": "http://localhost:8000/vehicles",
    "route": "vehicles.index",
    "storyId": null,
    "selector": "[data-iak-part='filter-bar']",
    "coordinates": { "x": 412, "y": 188 }
  },
  "viewport": { "width": 1440, "height": 900, "name": "desktop" },
  "message": "This should reuse the standard filter bar pattern.",
  "tags": ["pattern", "filter-bar"],
  "attachments": {
    "screenshot": ".iak/feedback/fbk_01j/screenshot.png",
    "dom": ".iak/feedback/fbk_01j/dom.html",
    "console": ".iak/feedback/fbk_01j/console.json",
    "network": null,
    "trace": null
  },
  "context": {
    "gitSha": "abc123",
    "branch": "feat/vehicles-index",
    "adapter": "laravel-inertia-react",
    "componentCandidates": ["FilterBar", "VehicleFilters"],
    "storyArgs": null,
    "testRunId": null
  },
  "resolution": null,
  "createdAt": "2026-05-22T15:00:00Z",
  "updatedAt": "2026-05-22T15:00:00Z"
}
```

Required fields:

- `schema`, `id`, `status`, `surface`, `source`, `producer`, `target`,
  `message`, `attachments`, `context`, `createdAt`, and `updatedAt`.
- `target.url` is required for `surface: "app"`.
- `target.storyId` is required for `surface: "storybook"`.
- `context.gitSha` is required when Git metadata is available; otherwise use
  `null` and add a validation warning in command output.
- `resolution` is required and non-null when `status` is `resolved`,
  `wont_fix`, or `duplicate`.

Unknown fields are preserved on read-write cycles but ignored by validators.

### Resolution Object

Resolved records store `resolution` inline and copy detailed evidence to an
artifact:

```json
{
  "schema": "iak.feedback.resolution.v1",
  "status": "resolved",
  "summary": "Reused the shared FilterBar component.",
  "reason": null,
  "duplicateOf": null,
  "changedFiles": [
    {
      "path": "resources/js/features/vehicles/vehicle-filters.tsx",
      "role": "feature",
      "action": "modify"
    }
  ],
  "commandsRun": [
    { "cmd": "php artisan iak:verify --feedback=fbk_01j --json", "exitCode": 0 }
  ],
  "artifacts": {
    "screenshotAfter": ".iak/feedback/fbk_01j/resolution/screenshot-after.png",
    "audit": ".iak/feedback/fbk_01j/resolution/audit.json",
    "tests": ".iak/feedback/fbk_01j/resolution/tests.json"
  },
  "linkedEvidence": ".iak/runs/run_01j/verify.json",
  "evidenceCopiedTo": ".iak/feedback/fbk_01j/resolution/evidence.json",
  "evidenceSummary": "Verify passed for fbk_01j with no related unresolved feedback.",
  "resolver": { "kind": "agent", "id": "iak-worker" },
  "resolvedAt": "2026-05-22T15:10:00Z"
}
```

Rules:

- `resolution.status` must match the parent record status.
- `resolved` requires `summary`, `changedFiles`, `commandsRun`,
  `linkedEvidence`, `evidenceCopiedTo`, `resolver`, and `resolvedAt`.
- `wont_fix` requires a non-empty `reason` and evidence stub.
- `duplicate` requires `duplicateOf` pointing to an existing record and
  evidence stub.

## Status Semantics

Valid statuses:

| Status | Meaning | Blocks `iak:verify` |
| --- | --- | --- |
| `pending` | Open feedback that has not been claimed. | Yes, when related to the verify scope. |
| `in_progress` | A resolver has claimed the item but not closed it. | Yes, when related to the verify scope. |
| `resolved` | Closed with evidence that passed validation. | No. |
| `wont_fix` | Closed with a reason and evidence stub. | No, if resolution is valid. |
| `duplicate` | Closed in favor of another feedback record. | No, if `duplicateOf` points to an existing record. |

The Phase 5 Artisan command only creates `resolved` transitions. It must still
read and validate all statuses because later producers and status commands will
write them.

## Storage And Artifact Paths

All paths are project-relative POSIX paths from the Laravel app root.

```txt
.iak/
  feedback/
    index.jsonl
    by-status/
      pending.jsonl
      in_progress.jsonl
      resolved.jsonl
      wont_fix.jsonl
      duplicate.jsonl
    fbk_01j/
      record.json
      screenshot.png
      dom.html
      console.json
      network.har
      trace.zip
      resolution/
        evidence.json
        screenshot-after.png
        audit.json
        tests.json
```

Rules:

- `.iak/feedback/<id>/record.json` is canonical.
- `index.jsonl` and `by-status/*.jsonl` are derived caches. The command may
  rebuild them from `record.json` files when missing or stale.
- Attachments referenced by `record.attachments.*` must live under
  `.iak/feedback/<id>/`.
- Resolution artifacts must live under `.iak/feedback/<id>/resolution/` or be
  linked from `.iak/runs/<run-id>/`.
- `resolve --evidence` accepts a project-relative path to `iak.verify.v1`,
  `iak.handoff.v1`, or `iak.feedback.resolution.v1` JSON.
- The command copies the normalized resolution evidence to
  `.iak/feedback/<id>/resolution/evidence.json` and records the original path
  as `resolution.linkedEvidence`.
- Writes are atomic: write `record.json.tmp`, flush, then rename.
- Write commands must hold a per-record lock to avoid concurrent resolvers.

## Evidence Rules For Resolve

`php artisan iak:feedback resolve {id} --evidence=<path> --json` must fail
without mutating `record.json` unless all rules pass:

1. The record exists and has status `pending` or `in_progress`.
2. The evidence path exists, is JSON, and is inside `.iak/runs/` or
   `.iak/feedback/<id>/resolution/`.
3. The evidence schema is `iak.verify.v1`, `iak.handoff.v1`, or
   `iak.feedback.resolution.v1`.
4. The evidence names this feedback id in `scope.feedback`,
   `feedback.ids`, or `resolution.feedbackId`.
5. Audit evidence is present and passed.
6. Test or command evidence is present with at least one successful verification
   command.
7. `changedFiles` is non-empty for `status: "resolved"`.
8. App and Storybook records include post-fix screenshot evidence.
9. Storybook records include a passing story result for `target.storyId`.
10. Routed app records include browser evidence for `target.route` or
    `target.url` when reachable.
11. Related unresolved feedback count is zero, excluding the target feedback id
    when the evidence was produced by `iak:verify --feedback=<id>`.
12. The resolver identity and `resolvedAt` timestamp can be recorded.

`--summary` may override the evidence summary only when the provided summary is
non-empty and at least 16 characters. The original evidence summary remains in
`resolution.evidenceSummary`.

## Interaction With `iak:verify`

`iak:verify` reads feedback records through the same store service.

Required behavior for the verify implementation:

- `pending` and `in_progress` records related to changed routes, URLs,
  Storybook story IDs, selectors, component candidates, resources, or explicit
  feedback ids block handoff and CI verification.
- `resolved`, `wont_fix`, and `duplicate` records do not block only when their
  `resolution` object validates.
- Invalid resolved records are treated as unresolved and should produce a
  feedback validation error.
- `iak:verify --feedback=<id>` is the evidence-preparation path for resolving
  one item. It may exclude that target id from unresolved counts, but it must
  still fail on any other related unresolved feedback.
- `iak:verify` reports readiness; it never calls `iak:feedback resolve`
  automatically.
- A passing handoff must include `evidence.feedback.unresolved: 0` and an
  artifact reference such as `.iak/runs/<run-id>/feedback/unresolved.json`.

Recommended resolver workflow:

```txt
php artisan iak:feedback show fbk_01j --json
php artisan iak:verify --feedback=fbk_01j --json
php artisan iak:feedback resolve fbk_01j --evidence=.iak/runs/run_01j/verify.json --json
php artisan iak:verify --json
```

## Future Producer Mapping

Future producers write the same `iak.feedback.v1` record shape. This command
does not implement those integrations yet, but the store and validators must
accept their records.

| Producer input | Record mapping |
| --- | --- |
| App overlay HITL message | `surface: "app"`, `source: "human"` or `"agent"`, `producer: "iak.app-overlay"`, `target.url`, optional `target.route`, selector, coordinates, screenshot, DOM, console artifacts. |
| Storybook addon message | `surface: "storybook"`, `producer: "@inertia-agent-kit/storybook-feedback"`, required `target.storyId`, Storybook URL, args under `context.storyArgs`, component candidates, screenshot, DOM, console artifacts. |
| Browser log bridge | Stored as `attachments.console` with capped entries copied from Boost browser logs or the local overlay ring buffer. It does not become a separate feedback record unless paired with a message. |
| Automated test failure | `surface: "test"`, `source: "test"`, `producer: "iak.test-runner"`, `context.testRunId`, failing URL or story ID, screenshot, DOM, console, and trace artifacts. |
| Instruckt-compatible annotation | IAK mints its own `id`, preserves the upstream id in `context.upstreamId`, maps note/message to `message`, URL/selector/screenshot to target and attachments, and starts as `pending` unless IAK-grade resolution evidence exists. |

Unknown producer fields should be stored under `context.producerPayload` with
size caps. Large blobs must be artifacts, never embedded JSON.

## Pest And Testbench Acceptance Tests

Implementation agents should add focused package tests with Orchestra
Testbench and Pest:

- `iak:feedback list --json` returns an empty `iak.feedback.list.v1` object
  when `.iak/feedback/` does not exist.
- `list` returns deterministic summaries from multiple `record.json` files,
  filters by `--status`, `--surface`, and `--source`, and excludes large
  attachment contents.
- `show {id} --json` returns the complete canonical record and record artifact
  path.
- `show` for a missing id exits `2` with `iak.error.v1` and
  `feedback.not_found`.
- `resolve` without `--evidence`, with a missing evidence path, with invalid
  JSON, or with the wrong schema exits non-zero and leaves `record.json`
  unchanged.
- `resolve` rejects evidence that lacks the target feedback id, passing audit
  evidence, changed files, required screenshots, or zero related unresolved
  feedback.
- `resolve` with valid `iak.verify.v1` evidence updates status to `resolved`,
  writes `resolution`, copies normalized evidence to
  `.iak/feedback/<id>/resolution/evidence.json`, updates `updatedAt`, and emits
  `iak.feedback.resolve.v1`.
- `resolve` preserves unknown fields and existing attachments on the record.
- `IAK_AGENT=1` defaults list/show/resolve to JSON output without `--json`.
- Artifact path validation rejects absolute paths and paths escaping `.iak/`.
- A locked record causes `resolve` to exit `3` with a lock-specific error.
- Verify integration tests seed pending, in-progress, resolved-valid, and
  resolved-invalid records, then assert `iak:verify --json` blocks only on
  related unresolved or invalidly resolved records.

Keep tests filesystem-local: create temporary `.iak/feedback/` and
`.iak/runs/` trees under the Testbench app base path, then assert JSON stdout
and persisted files.

## Implementation Notes

Suggested internal services:

- `FeedbackStore`: read, list, atomic write, lock, and rebuild indexes.
- `FeedbackRecordValidator`: validate `iak.feedback.v1` and artifact paths.
- `FeedbackEvidenceValidator`: validate `verify`, `handoff`, or resolution
  evidence for a target record.
- `FeedbackJsonPresenter`: build compact list/show/resolve JSON shapes.
- `IakFeedbackCommand`: Artisan adapter only; no storage logic in the command
  body.

The implementation should be small enough to land before HTTP, Storybook, and
MCP producers. Those later surfaces should call the same store and validators
instead of reimplementing feedback rules.
