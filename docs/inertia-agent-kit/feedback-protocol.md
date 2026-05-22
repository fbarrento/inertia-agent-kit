# IAK Feedback Protocol

Status: Draft (Phase 3, todo #11)
Schema family: `iak.feedback.v1`
Owner: Inertia Agent Kit
Source of truth: this document

## Goals

A product-neutral HITL feedback protocol owned by IAK. Anything that wants to leave feedback on a UI — humans annotating the running app, Storybook authors, automated test runners, AI agents flagging their own uncertainty — posts into one queue with one shape. Instruckt's browser-annotation flow is one valid producer of records, not the protocol itself.

The protocol must:

1. Define a single record shape (`iak.feedback.v1`) usable by app, Storybook, and tests.
2. Define a local-first storage layout under `.iak/feedback/` that survives across processes.
3. Expose HTTP, CLI, and MCP surfaces that all read/write the same store.
4. Enforce evidence-bearing resolution — agents cannot close feedback with prose alone.
5. Be compatible with Instruckt's annotation flow without depending on it.

## Non-goals

- Cloud/remote sync (out of scope for v1; storage is local).
- Auth / multi-tenancy (single developer machine).
- Rich-text or threaded discussion (one message + resolution; longer discussion belongs in commits/PRs).
- Replacing Boost's browser-log / app-info tools (they remain the source for execution context).

## Record schema — `iak.feedback.v1`

Canonical fields. Unknown fields are preserved but ignored by IAK. Optional fields are explicit.

```json
{
  "schema": "iak.feedback.v1",
  "id": "fbk_01h...",
  "status": "pending",
  "surface": "app",
  "source": "human",
  "producer": "iak.app-overlay",
  "target": {
    "url": "http://localhost:5173/invoices",
    "route": "invoices.index",
    "storyId": null,
    "selector": "[data-iak-part='filter-bar']",
    "coordinates": { "x": 412, "y": 188 }
  },
  "viewport": { "width": 1440, "height": 900, "name": "desktop" },
  "message": "This should reuse the standard filter bar pattern.",
  "tags": ["pattern", "filter-bar"],
  "attachments": {
    "screenshot": ".iak/feedback/fbk_01h/screenshot.png",
    "dom": ".iak/feedback/fbk_01h/dom.html",
    "console": ".iak/feedback/fbk_01h/console.json",
    "network": null,
    "trace": null
  },
  "context": {
    "gitSha": "abc123",
    "branch": "feat/invoices-index",
    "adapter": "laravel-inertia-react",
    "componentCandidates": ["FilterBar", "InvoiceFilters"],
    "storyArgs": null,
    "testRunId": null
  },
  "resolution": null,
  "createdAt": "2026-05-22T14:30:00Z",
  "updatedAt": "2026-05-22T14:30:00Z"
}
```

### Field semantics

| Field | Required | Notes |
|---|---|---|
| `schema` | yes | Always `iak.feedback.v1`. Bumped on breaking shape changes. |
| `id` | yes | ULID-shaped `fbk_<ulid>`. Globally unique on machine. |
| `status` | yes | `pending` \| `in_progress` \| `resolved` \| `wont_fix` \| `duplicate`. State machine in §Lifecycle. |
| `surface` | yes | `app` \| `storybook` \| `test`. Which producer surface created the record. |
| `source` | yes | `human` \| `agent` \| `test`. Who/what authored the message. |
| `producer` | yes | Producer identifier, e.g. `iak.app-overlay`, `iak.storybook-addon`, `instruckt`, `iak.test-runner`. Free-form but stable per producer. |
| `target.url` | yes when `surface=app` | Current page URL. |
| `target.route` | optional | Inertia route name when known (helps the resolver locate page/feature). |
| `target.storyId` | yes when `surface=storybook` | Storybook story ID (`features-invoices-invoicetable--default`). |
| `target.selector` | optional | Best-effort CSS selector. Prefer `[data-iak-part='...']` over generated classes. |
| `target.coordinates` | optional | Click coordinates if no usable selector. |
| `viewport` | optional | Viewport at capture time. `name` is one of `desktop`/`tablet`/`mobile`/custom. |
| `message` | yes | Free-form human/agent message. |
| `tags` | optional | Free-form labels. Keep short. |
| `attachments.*` | optional | All paths relative to project root and inside `.iak/feedback/<id>/`. `null` when not captured. |
| `context.gitSha` | yes | Captured at record time. |
| `context.componentCandidates` | optional | Producer's best guess at the responsible component(s). Resolver may update. |
| `resolution` | yes when `status` ∈ {`resolved`, `wont_fix`, `duplicate`} | See §Evidence-required resolution. |
| `createdAt` / `updatedAt` | yes | UTC ISO-8601. |

A JSON Schema mirror lives at `schemas/iak.feedback.v1.json` (to be published once the engine package ships).

## Local storage layout

All feedback is local-first under the consumer project's `.iak/` directory. The store is a content directory + a thin index, not a database.

```
.iak/
  config.json
  feedback/
    index.jsonl               # append-only index, one record per line
    by-status/
      pending.jsonl            # id-only index for fast list queries
      resolved.jsonl
      wont_fix.jsonl
      duplicate.jsonl
    fbk_01h.../
      record.json              # canonical record (see schema above)
      screenshot.png           # attachments referenced by paths in record.json
      dom.html
      console.json
      network.har              # optional
      resolution/
        evidence.json           # see §Evidence
        screenshot-after.png
        audit.json
        tests.json
```

Rules:

- `record.json` is the canonical, version-stable artifact. `index.jsonl` is a denormalized cache that can be rebuilt from `record.json` files via `iak feedback reindex`.
- Writes are atomic: write to `record.json.tmp`, fsync, rename.
- Producers MUST NOT mutate another producer's record fields except via the resolve endpoint.
- The `.iak/feedback/` directory MUST be `.gitignore`'d by default. IAK provides `iak feedback export <id> --to <path>` for selectively committing records to a repo when desired.

## HTTP surface

Routes live under the dev-only prefix `/__iak/feedback`, registered by the Laravel package's service provider when `app()->environment()` permits and `config('inertia-agent-kit.feedback.http')` is enabled.

| Method | Path | Purpose | Body / Response |
|---|---|---|---|
| `POST` | `/__iak/feedback` | Create a record. | Body: partial `iak.feedback.v1` (server fills `id`, `createdAt`, `context.gitSha`, validates). 201 with the canonical record. |
| `GET` | `/__iak/feedback` | List records. Query: `status`, `surface`, `source`, `producer`, `limit`, `cursor`. | 200 with `{ items: [...], nextCursor }`. |
| `GET` | `/__iak/feedback/{id}` | Fetch one record. | 200 with the record, including resolved-relative attachment paths. |
| `GET` | `/__iak/feedback/{id}/attachments/{name}` | Stream an attachment (`screenshot`, `dom`, `console`, `network`). | Binary or JSON payload. |
| `POST` | `/__iak/feedback/{id}/resolve` | Mark resolved. Body: `iak.feedback.resolution.v1` (see §Evidence). | 200 with updated record. 422 when evidence is missing. |
| `POST` | `/__iak/feedback/{id}/status` | Move to `in_progress` / `wont_fix` / `duplicate`. Body: `{ status, reason }`. `wont_fix` and `duplicate` still require an evidence stub (link to commit or duplicate id). |
| `POST` | `/__iak/feedback/{id}/comments` | Optional. Adds a single resolution-bound comment. Hard-capped to 1 KB. Stored under `record.json` as `notes[]`. |
| `POST` | `/__iak/feedback/import` | Bulk-create from a foreign producer (e.g. Instruckt). Body: `{ records: [...], producer }`. Each record is normalized through the adapter (see §Instruckt compatibility). |

Response shape conventions:

- `Content-Type: application/json`.
- Errors use `{ error: { code, message, details? } }`. Validation failures are HTTP 422; missing records 404; conflict (lock held) 409.
- Local-only authentication: requests must come from loopback. The Laravel middleware rejects non-loopback origins by default; configurable for trusted reverse proxies.

## CLI surface

Provided by the JS/Node `@inertia-agent-kit/cli` package. All commands accept `--json` and default to JSON when `IAK_AGENT=1` (see Token Budget contract).

```txt
iak feedback list [--status pending] [--surface app|storybook|test] [--limit N] [--json]
iak feedback show <id> [--json]
iak feedback attach <id> <kind> <path>           # late-bind an artifact (e.g. screenshot)
iak feedback open <id>                            # opens screenshot/dom/console in the default viewer
iak feedback resolve <id> --evidence .iak/runs/<run-id>.json [--summary "..."]
iak feedback status <id> --to wont_fix --reason "..." [--duplicate-of <id>]
iak feedback reindex                              # rebuild index.jsonl from record.json files
iak feedback export <id> --to docs/feedback/<id>.md   # for committing summaries
iak feedback import --from <path> --producer instruckt
```

Conventions:

- Exit code 0 on success, 1 on validation/state errors, 2 on missing record, 3 on lock contention.
- Human output is terse; agent output (`--json` or `IAK_AGENT=1`) follows the JSON shapes from §HTTP and the handoff contract.
- All write commands lock the record by `id` via an OS file lock on `record.json` to prevent concurrent resolvers.

## MCP surface

Exposed by the IAK MCP server (registered alongside Boost; see §Instruckt + Boost). Boost stays the substrate for generic Laravel awareness; IAK adds only feedback-specific tools.

```txt
iak_feedback_list_pending(limit?: number, surface?: "app" | "storybook" | "test") -> FeedbackSummary[]
iak_feedback_get(id: string) -> Feedback
iak_feedback_get_screenshot(id: string) -> { path: string, contentType: "image/png" }
iak_feedback_get_dom(id: string) -> { path: string }
iak_feedback_get_console(id: string) -> { path: string, entries: ConsoleEntry[] }
iak_feedback_resolve(id: string, evidence: Evidence, summary: string) -> Feedback
iak_feedback_mark(id: string, status: "in_progress" | "wont_fix" | "duplicate", reason: string, duplicateOf?: string) -> Feedback
```

Rules:

- Tools return paths plus minimal structured data, not embedded binaries. Agents fetch artifacts on demand to preserve token budget.
- `iak_feedback_resolve` mirrors the HTTP `POST /resolve` semantics and rejects evidence-less calls with the same 422 shape.
- Tools MUST NOT duplicate Boost-provided capabilities (browser logs, app info, DB schema). They consume those upstream when populating the record's `attachments` and `context`.

## Evidence-required resolution

Resolution payload shape — `iak.feedback.resolution.v1`:

```json
{
  "schema": "iak.feedback.resolution.v1",
  "summary": "Replaced ad-hoc filter with shared FilterBar primitive.",
  "changedFiles": [
    { "path": "resources/js/features/invoices/invoice-filters.tsx", "role": "feature", "action": "modify" }
  ],
  "commandsRun": [
    { "cmd": "iak audit --json", "exitCode": 0 },
    { "cmd": "iak verify --json", "exitCode": 0 },
    { "cmd": "pnpm test -- invoice-filters", "exitCode": 0 }
  ],
  "artifacts": {
    "screenshotAfter": ".iak/feedback/fbk_01h/resolution/screenshot-after.png",
    "audit": ".iak/feedback/fbk_01h/resolution/audit.json",
    "tests": ".iak/feedback/fbk_01h/resolution/tests.json",
    "storybook": { "storyId": "features-invoices-invoicefilters--default", "status": "passed" }
  },
  "linkedHandoff": ".iak/runs/run_01h/handoff.json",
  "resolvedAt": "2026-05-22T15:10:00Z",
  "resolver": { "kind": "agent", "id": "iak-worker:vehicles-index" }
}
```

A resolution is **invalid** (HTTP 422 / CLI exit 1 / MCP error) when any of the following hold:

1. `summary` is empty or `< 16` chars.
2. `changedFiles` is empty AND `status` is `resolved`. (`wont_fix` and `duplicate` may have empty `changedFiles` but require a non-empty `reason` and, for `duplicate`, a `duplicateOf` id.)
3. `commandsRun` does not include at least one passing audit invocation when the change touches `resources/js/**`.
4. `artifacts.screenshotAfter` is missing when the record's `surface` is `app` or `storybook` and the underlying UI is reachable.
5. `artifacts.audit` is missing or its referenced JSON does not validate against `iak.audit.v1`.
6. The `linkedHandoff` file does not exist or fails `iak handoff validate`.
7. The resolver field is missing (we record who closed the record).

`iak feedback resolve` and the HTTP / MCP equivalents all run the same validator, so the rule set is one implementation surfaced three ways.

## Instruckt compatibility

Instruckt (`joshcirre/instruckt-laravel`) provides browser annotations, screenshots, a local feedback store, API routes, and MCP tools (`get_all_pending`, `get_screenshot`, `resolve`). IAK is a superset and remains independent.

Two integration modes are supported:

**Mode A — Instruckt as a producer.** When Instruckt is installed in the same Laravel app, an IAK adapter (`InstrucktFeedbackProducer`) subscribes to Instruckt's create event (or polls its store on a short interval) and normalizes each Instruckt record into `iak.feedback.v1`:

| Instruckt field | IAK field | Notes |
|---|---|---|
| `id` | `context.upstreamId` | Preserved for round-trip; IAK mints its own `id`. |
| `note` / `message` | `message` | |
| `url` | `target.url` | |
| `selector` | `target.selector` | |
| `screenshot path` | `attachments.screenshot` | File is copied (not symlinked) into `.iak/feedback/<id>/`. |
| `viewport` | `viewport` | Defaulted to `desktop` when missing. |
| created timestamp | `createdAt` | |
| status | `status` | Mapped: Instruckt `open` → `pending`, `resolved` → `resolved` (resolution stub synthesized; see below). |

For records arriving without IAK-grade evidence, status starts as `pending`; once an IAK agent resolves them, the resolution flows back to Instruckt via the reverse adapter (`InstrucktFeedbackSink`) using Instruckt's `resolve` API — but only when IAK's evidence validator has already passed. This prevents IAK from "downgrading" the queue to Instruckt's looser resolution rules.

**Mode B — IAK only.** When Instruckt is absent, IAK's own app-overlay (`@inertia-agent-kit/app-overlay`) provides the in-browser annotation UX. Mode B is the default.

Field-level compatibility goal: an Instruckt record can round-trip through IAK without loss when the producer is set to `instruckt`. IAK-only fields (`producer`, `context.componentCandidates`, `resolution.commandsRun`, etc.) are additive.

## Unified queue — Storybook, app, test all share one store

The `surface` field is the multiplexer. There is exactly one `.iak/feedback/` directory and one canonical record format; the three producers differ only in what `target.*` they fill.

### `surface: "app"` — app overlay

Producer: `@inertia-agent-kit/app-overlay` (and/or `instruckt` via the adapter above). Captures:

- `target.url`, `target.route` (from Inertia page props)
- `target.selector` — auto-generated from `data-iak-part` attributes when available; falls back to a stable CSS path
- `target.coordinates` — click position
- `attachments.screenshot` — page screenshot via `html2canvas` or browser API
- `attachments.console` — last N console entries (default 50) via the Boost browser-logs bridge when present, otherwise a small ring buffer the overlay maintains
- `attachments.dom` — outerHTML of the closest annotated container

### `surface: "storybook"` — Storybook addon

Producer: `@inertia-agent-kit/storybook-feedback` addon. Captures everything `app` does, plus:

- `target.storyId` — the Storybook story ID
- `context.storyArgs` — current `args` object at capture time (JSON-stringified, capped at 8 KB)
- `viewport.name` — current Storybook viewport addon selection

The addon posts to the same `POST /__iak/feedback` endpoint when the Laravel dev server is reachable, OR writes directly to `.iak/feedback/` via a small Node helper when Storybook runs without Laravel up. Either path produces an identical record.

### `surface: "test"` — automated runners

Producer: `@inertia-agent-kit/test-runner` (Playwright/Vitest plugin) or any tool that POSTs to `/__iak/feedback/import` with `producer: "iak.test-runner"`. Captures:

- `target.url` and/or `target.storyId`
- `context.testRunId` — links back to `.iak/runs/<run-id>/`
- `source: "test"` — distinguishes generated diagnostics from human/agent feedback
- `attachments.screenshot` — failure screenshot
- `attachments.dom`, `attachments.console`, optional `attachments.trace`

Test-surface records typically arrive as `status: pending` AND `source: test`, which lets agents filter for them via `iak_feedback_list_pending({ source: "test" })`.

### Why one queue

- A single resolution surface: a human, an agent, or a test runner all close records with the same evidence rules.
- A single index lets the resolver triangulate — e.g. an `app` record and a `test` record about the same selector can be merged via `status=duplicate, duplicateOf=...`.
- Storybook stories become the natural fix-and-verify environment: app-surface feedback can be resolved by reproducing the bug in a story, fixing it there, and pointing `artifacts.storybook.storyId` at the now-passing story. Storybook is the design-system runtime per §Storybook-as-design-system-runtime.

## Lifecycle

State machine for `status`:

```
pending  ──claim──▶  in_progress  ──resolve──▶  resolved
   │                       │
   │                       ├──mark wont_fix──▶  wont_fix
   │                       └──mark duplicate─▶  duplicate
   │
   └──mark wont_fix / duplicate (skipping in_progress) is allowed for triage
```

- `claim` is implicit in `iak_feedback_resolve` / `POST /resolve` (the resolver writes who they are).
- Re-opening a resolved record is allowed via `POST /status` with `to=pending`; the old resolution is moved to `record.json#resolutions[]` (history kept for audit).

## Versioning

- Records carry `schema: iak.feedback.v1`. Additive fields don't bump the version; field renames, removals, or semantic changes do.
- Producers SHOULD set `schema` to the version they wrote. The store accepts the current version plus the immediately previous version with an upgrade pass at read time.
- `iak.feedback.resolution.v1` is versioned independently.

## Open questions

1. Should `attachments.console` cap by entry count, byte size, or both? Default proposed: 50 entries OR 64 KB, whichever comes first.
2. Should `producer` be a free-form string or an enum? Free-form for now; we'll lift well-known producers into an enum once we see real adopters.
3. Reverse-adapter (`InstrucktFeedbackSink`) — push-on-resolve vs. on-demand sync. Open until we know Instruckt's API rate limits.
4. Do we want a per-surface store under `.iak/feedback/by-surface/` mirroring `by-status/`? Likely yes once `iak feedback list --surface` becomes hot.
5. How does this interact with Boost's `browser_logs` MCP tool — do we ingest its logs into `attachments.console` at create time, or fetch on demand at resolve time? Both feasible; default to ingest-at-create so the record survives even after the dev server stops.
