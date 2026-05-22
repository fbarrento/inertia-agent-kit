# IAK MCP Tools And Resources

Status: draft spec
Date: 2026-05-22
Owned surface: Inertia Agent Kit-specific MCP resources and tools for
Laravel/Inertia apps.

## Purpose

Inertia Agent Kit (IAK) should expose a small MCP surface that gives agents the
structured frontend context they need without duplicating Laravel Boost. The
MCP surface exists for agent workflows that need to:

- read the current IAK manifest, conventions, schemas, tokens, components, and
  Brand OS status;
- inspect and resolve IAK feedback with evidence;
- run or read IAK audit and verify artifacts;
- validate style, token, component, handoff, and brand decisions against the
  app's configured contracts.

This document defines the IAK-specific resources and tools only. Generic
Laravel facts remain Boost-owned.

## Product Boundary With Boost

Boost is the Laravel agent substrate. IAK is the Inertia frontend discipline
layer.

Use Boost for:

- Laravel, package, and version-aware documentation;
- generic app info and environment/package inspection;
- absolute URL generation;
- browser logs when Boost already captures them;
- database connections, database schema, database queries, last error, and log
  entries;
- common MCP setup through `php artisan boost:mcp`.

Use IAK MCP for:

- IAK manifest, conventions, role graph, and resource/page contracts;
- generated type discipline and frontend role ownership;
- design-system token and component contracts;
- Storybook, app, test, and HITL feedback records;
- architecture/style/story/type/feedback audit output;
- verification runs and evidence artifacts;
- Brand OS lock, sync, audit, and manifest slices;
- JSON handoff validation where the handoff depends on IAK evidence.

IAK must not register generic tools such as `iak_app_info`,
`iak_route_list`, `iak_db_schema`, `iak_db_query`, `iak_browser_logs`,
`iak_log_entries`, `iak_docs_search`, or `iak_absolute_url`. IAK tools may
call Boost or reference Boost capability names internally, but returned IAK
JSON should store only concise summaries, capability names, tool-result ids, or
artifact references.

Implementation strategy:

1. Prefer package registration through Boost if the installed Boost version
   exposes a stable third-party MCP extension API.
2. Otherwise register a separate dev-only IAK MCP server through Laravel MCP
   and document that it should run alongside Boost.
3. Prefix every IAK tool with `iak_`.

## Resources Vs Tools

MCP resources are read-only snapshots. Reading a resource must not scan the
whole project, run tests, invoke generators, mutate `.iak`, resolve feedback,
or refresh stale artifacts. If a resource is stale, it returns a stale status
and a `nextActions[]` entry that points to the tool or CLI command that refreshes
it.

MCP tools perform work. Tools may run configured IAK commands, create
artifacts under `.iak/runs/*`, mutate `.iak/feedback/*`, refresh the manifest,
or update IAK-managed Brand OS copies. Tools must not run arbitrary shell
commands supplied by the client. They execute only configured IAK operations
with schema-validated inputs.

Default rule:

```txt
Read with resources first. Use tools only when the agent needs fresh evidence,
validation, or an intentional mutation.
```

## MCP Resources

Resource URIs are project-scoped by the local MCP server. Paths returned inside
resources remain project-relative POSIX paths.

| URI | Schema / shape | Purpose |
| --- | --- | --- |
| `iak://manifest` | `iak.manifest.v1` | Current generated manifest. First resource agents should read. |
| `iak://conventions` | Manifest convention slice | Page/resource roles, role graph, generated type policy, backend-owned formatting rules, and required agent loop. |
| `iak://schemas/{schema}` | JSON Schema | Published schema for `iak.manifest.v1`, `iak.audit.v1`, `iak.feedback.v1`, `iak.feedback.resolution.v1`, `iak.verify.v1`, `iak.handoff.v1`, `iak.brand-lock.v1`, and `iak.error.v1`. |
| `iak://tokens` | Token manifest slice | Token files, import order, semantic token names, generated `ds-` utilities, and token audit rules. |
| `iak://tokens/{category}` | Token category slice | Bounded token list for categories such as `color`, `space`, `radius`, `type`, `shadow`, or `motion`. |
| `iak://components` | Component manifest slice | Component summaries grouped by role, story, fixture, contract, variants, and required states. |
| `iak://components/{name}` | Component contract | One component contract by name or alias, including role, paths, public API summary, stories, fixtures, and variants. |
| `iak://resources/{resource}` | Resource contract | Inertia resource summary: page paths, feature paths, controller/action mapping, generated type imports, routes/stories known to IAK, and related feedback count. |
| `iak://feedback/{id}` | `iak.feedback.v1` | One canonical feedback record with artifact references. |
| `iak://feedback/{id}/artifact/{name}` | Artifact reference or bounded content | Attachment metadata for `screenshot`, `dom`, `console`, `network`, or `trace`. Binary data is not embedded unless the MCP client explicitly supports binary resources. |
| `iak://runs/{runId}/verify` | `iak.verify.v1` | Canonical verify artifact for a run. |
| `iak://runs/{runId}/handoff` | `iak.handoff.v1` | Handoff artifact when one exists for the run. |
| `iak://brand` | Manifest brand slice | Brand OS status, lock file, concise voice/avoid summaries, copied artifact paths, generated token outputs, and brand commands. |

Resource payload rules:

- Do not inline component source, generated types, CSS token files, logs, DOM
  dumps, screenshots, SVGs, or Brand OS markdown.
- Include stable ids, concise summaries, paths, hashes, artifact references,
  and command/tool hints.
- Keep large catalogs in referenced JSON artifacts such as
  `.iak/manifest/tokens.json` and `.iak/manifest/components.json`.
- Preserve deterministic ordering so unchanged projects produce unchanged
  resources.

## Tool Result Shape

When a tool naturally returns an existing IAK schema, return that object
directly. Examples: `iak_audit_run` returns `iak.audit.v1`; `iak_verify_run`
returns `iak.verify.v1`; `iak_feedback_get` returns `iak.feedback.v1`.

Tools that return a slice, list, or utility result use the common envelope:

```json
{
  "schema": "iak.mcp-result.v1",
  "status": "passed",
  "summary": "Returned 3 primitive components.",
  "data": {},
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

`data` must remain compact. If a result is truncated, include
`truncated`, `totalItems`, `returnedItems`, and an artifact reference to the
full JSON.

## Tool List

### Manifest And Conventions

| Tool | Inputs | Output | Mutates |
| --- | --- | --- | --- |
| `iak_manifest_read` | `sections?: string[]`, `maxItems?: number` | `iak.manifest.v1` or a manifest slice in `iak.mcp-result.v1` | No |
| `iak_manifest_refresh` | `reason?: string` | `iak.manifest.v1` plus freshness metadata | Yes, `.iak/manifest/*` |
| `iak_conventions_read` | `scope?: one of pages, features, components, types, formatting, agent-loop, all` | Convention slice with role rules, allowed paths, required checks, and source doc references | No |
| `iak_schema_get` | `schema: string` | JSON Schema resource or `iak.error.v1` | No |

Notes:

- `iak_manifest_read` should be equivalent to reading `iak://manifest`.
- `iak_manifest_refresh` may read config, catalog, Storybook metadata, Brand OS
  lock state, and generated schema locations. It must not run audit or verify.
- Manifest resources should state Boost capabilities by name instead of copying
  Boost outputs.

### Tokens And Style

| Tool | Inputs | Output | Mutates |
| --- | --- | --- | --- |
| `iak_tokens_list` | `category?: string`, `intent?: string`, `includeUtilities?: boolean`, `limit?: number` | Token summaries, utility names, file refs, and token policy | No |
| `iak_token_suggest` | `rawValue?: string`, `tailwindClass?: string`, `cssProperty?: string`, `context?: { file?: string, role?: string, intent?: string }` | Ranked semantic token or utility suggestions, with confidence and manual/safe applicability | No |
| `iak_style_diff_validate` | `diff?: string`, `files?: string[]`, `changedFilesArtifact?: string` | Style-focused audit slice with `iak/design-system/*` violations and suggestions | No |

Rules:

- Token tools never add tokens directly. Missing values produce a suggested
  token-contract change and point to the configured token files.
- `iak_style_diff_validate` is a focused validator, not a replacement for
  `iak_audit_run`.
- Suggestions must prefer `ds-` semantic utilities and existing components
  before recommending new tokens.

### Components

| Tool | Inputs | Output | Mutates |
| --- | --- | --- | --- |
| `iak_components_list` | `role?: one of primitive, app, feature`, `resource?: string`, `query?: string`, `limit?: number` | Component summaries with role, path, story, contract, variants, states, fixture path, and hash | No |
| `iak_component_get` | `name?: string`, `path?: string` | One component contract from manifest, Storybook metadata, or `.spec.json` | No |
| `iak_component_find_similar` | `description: string`, `role?: string`, `resource?: string`, `limit?: number` | Candidate components to reuse or promote, with reasons and paths | No |
| `iak_component_validate_contract` | `name?: string`, `path?: string`, `contractPath?: string` | Contract/story/fixture validation slice | No |

Rules:

- Component tools expose contracts, not source files.
- `components/ui/*` primitives and `components/app/*` components require
  colocated stories.
- Feature components are returned with resource ownership and generated type
  requirements.
- During migration, historical `shared/ui` registry entries may map to the
  IAK primitive role.

### Audit

| Tool | Inputs | Output | Mutates |
| --- | --- | --- | --- |
| `iak_audit_run` | `mode?: focused or full`, `changedFiles?: ChangedFile[]`, `changedFilesArtifact?: string`, `planArtifact?: string`, `rules?: string[]`, `includeWarnings?: boolean` | `iak.audit.v1` | Yes, `.iak/runs/<run-id>/audit/*` |
| `iak_audit_get` | `runId?: string`, `artifact?: string` | Existing `iak.audit.v1` artifact | No |

Rules:

- `iak_audit_run` maps to the same checker registry as `iak audit --json`.
- It must include stable rule ids such as `iak/page/no-inline-form`,
  `iak/types/no-handwritten-data-copy`,
  `iak/design-system/no-raw-hex`, `iak/stories/required-ui`, and
  `iak/feedback/no-unresolved-feedback`.
- Non-passing audit results are returned as valid tool output with
  `status: "failed"` or `status: "blocked"`, not as transport errors.

### Verify And Handoff

| Tool | Inputs | Output | Mutates |
| --- | --- | --- | --- |
| `iak_verify_run` | `mode?: one of auto, focused, full, ci, handoff, feedback`, `surface?: app, storybook, or all`, `browser?: auto, pest, or playwright`, `route?: string[]`, `url?: string[]`, `stories?: string[]`, `feedback?: string[]`, `changedFiles?: ChangedFile[]`, `changedFilesArtifact?: string`, `planArtifact?: string`, `runId?: string` | `iak.verify.v1` | Yes, `.iak/runs/<run-id>/*` |
| `iak_verify_get` | `runId: string` | Existing `iak.verify.v1` artifact | No |
| `iak_handoff_validate` | `path?: string`, `runId?: string` | `iak.handoff.v1` validation result or `iak.mcp-result.v1` with validation errors | No |

Rules:

- `iak_verify_run` executes only configured project commands from
  `iak.config.json` and manifest command entries.
- Browser-visible changes require screenshot, console, and accessibility
  evidence when available.
- Handoff mode fails when audit/test evidence, Storybook or app URL evidence,
  screenshot, console result, accessibility result, unresolved feedback count,
  changed-file grouping, or enabled brand evidence is missing.
- Verify may use Boost for absolute URL generation and browser logs, but the
  verify artifact stores IAK evidence references.

### Feedback

| Tool | Inputs | Output | Mutates |
| --- | --- | --- | --- |
| `iak_feedback_list_pending` | `limit?: number`, `surface?: app, storybook, or test`, `source?: human, agent, or test`, `route?: string`, `storyId?: string`, `resource?: string` | Compact feedback summaries with ids, target, message excerpt, artifacts, and related resources | No |
| `iak_feedback_get` | `id: string` | `iak.feedback.v1` | No |
| `iak_feedback_get_screenshot` | `id: string` | Artifact reference for `attachments.screenshot` plus optional resource URI | No |
| `iak_feedback_get_dom` | `id: string`, `maxBytes?: number` | DOM artifact reference and optional bounded excerpt | No |
| `iak_feedback_get_console` | `id: string`, `levels?: string[]`, `limit?: number` | Console artifact reference and bounded console summary | No |
| `iak_feedback_mark` | `id: string`, `status: in_progress, wont_fix, or duplicate`, `reason: string`, `duplicateOf?: string` | Updated `iak.feedback.v1` | Yes, `.iak/feedback/<id>/record.json` |
| `iak_feedback_resolve` | `id: string`, `summary: string`, `evidence: FeedbackResolutionEvidence` | Updated `iak.feedback.v1` | Yes, `.iak/feedback/<id>/record.json` and `resolution/*` |

Rules:

- Resolution uses the same validator as the HTTP and CLI surfaces.
- `iak_feedback_resolve` rejects evidence-less resolution. Required evidence
  includes changed files, commands run, audit result, test or browser result,
  post-fix screenshot for app or Storybook UI, linked verify or handoff
  artifact, and resolver identity.
- Artifact tools return paths and metadata first. They do not embed screenshots
  or large DOM/console payloads in normal tool output.
- IAK may ingest Boost browser logs into a feedback attachment, but IAK must
  not expose a generic browser-log reader.

### Brand

| Tool | Inputs | Output | Mutates |
| --- | --- | --- | --- |
| `iak_brand_status` | `includeArtifacts?: boolean` | Manifest brand slice and `.iak/brand.lock.json` status | No |
| `iak_brand_connect` | `source: string`, `mode?: copy or link`, `refreshManifest?: boolean` | `iak.brand-lock.v1` plus generated artifact refs | Yes, consumer app only |
| `iak_brand_sync` | `check?: boolean`, `refreshManifest?: boolean` | Sync status: `current`, `stale`, `changed`, or `failed` | Yes unless `check: true` |
| `iak_brand_audit` | `changedFiles?: ChangedFile[]`, `runId?: string` | Brand-focused audit slice or `iak.audit.v1` artifact | Yes, `.iak/runs/<run-id>/brand/*` |

Rules:

- IAK never mutates the upstream Brand OS project.
- `iak_brand_connect` and non-check `iak_brand_sync` may copy, link, cache, or
  adapt consumable brand artifacts inside the Laravel/Inertia app.
- Brand resources never inline Brand OS markdown, token JSON, CSS, SVGs, or
  generated files.
- When brand support is enabled, audit, verify, feedback, and handoff evidence
  must include brand status and unresolved brand feedback counts where relevant.

## JSON Schema Alignment

The MCP surface reuses the same JSON schemas as CLI, HTTP, and persisted
artifacts.

Required published schemas:

- `iak.manifest.v1`
- `iak.audit.v1`
- `iak.feedback.v1`
- `iak.feedback.resolution.v1`
- `iak.verify.v1`
- `iak.handoff.v1`
- `iak.brand-lock.v1`
- `iak.error.v1`
- `iak.mcp-result.v1`

Schema rules:

- Tools validate inputs before doing work.
- Tool outputs include `schema`, `status`, `summary`, `artifacts`,
  `nextActions`, `errors`, and `meta` when the target schema supports them.
- Existing schema status semantics are preserved. For example, audit and verify
  use `passed`, `failed`, or `blocked`; feedback uses `pending`,
  `in_progress`, `resolved`, `wont_fix`, or `duplicate`.
- Schema locations are exposed through `iak://schemas/{schema}` and the
  `schemas` slice of `iak.manifest.v1`.
- Additive fields may be ignored by older clients. Breaking changes require a
  new schema version.

## Artifact Reference Behavior

Artifacts hold verbose evidence. MCP responses should pass references, not
large content.

Canonical artifact reference:

```json
{
  "kind": "screenshot",
  "path": ".iak/runs/run_01j/app/screenshots/vehicles-index.desktop.png",
  "mime": "image/png",
  "sizeBytes": 184221,
  "sha256": "sha256:...",
  "summary": "Vehicle index desktop screenshot after audit fixes."
}
```

Rules:

- Artifact paths must be project-relative POSIX paths.
- Required evidence artifacts must stay under `.iak/runs/*` or
  `.iak/feedback/*`, unless the artifact is a project file listed in
  `changedFiles`.
- Brand consumable artifacts may also be referenced under `.iak/brand/*` or the
  configured app-owned brand output paths.
- Paths are normalized and rejected when they escape the project root or use
  symlink traversal to leave allowed roots.
- Base64 screenshots, full DOM dumps, full console logs, generated types,
  full Brand OS markdown, SVG contents, and large test output are prohibited in
  normal MCP tool results.
- Artifact fetch resources may return bounded text excerpts for DOM and
  console artifacts. Binary screenshot content is returned only when the MCP
  client explicitly supports binary resources; otherwise return path, mime,
  hash, and size.
- Missing artifacts referenced by required evidence are validation failures.

## Security And Dev-Only Assumptions

IAK MCP is local agent infrastructure, not a production feature surface.

Minimum boundaries:

- Disabled unless the Laravel app is in `local` or `testing`, or a project
  owner explicitly enables the server for a trusted environment.
- Bound to loopback by default.
- Protected by local signed middleware, auth, or a package-specific gate when
  exposed over HTTP or routed through a web server.
- Never exposes secrets, raw environment values, session payloads, cookies,
  API tokens, unrestricted database query execution, or arbitrary filesystem
  reads.
- Never accepts arbitrary shell commands from an MCP client.
- Mutating tools are limited to IAK-managed state and explicitly documented app
  outputs: `.iak/manifest/*`, `.iak/runs/*`, `.iak/feedback/*`,
  `.iak/brand/*`, `.iak/brand.lock.json`, and configured brand token/asset
  output paths.
- Feedback and run writes use locks to avoid concurrent resolver or verifier
  corruption.
- Destructive operations, such as deleting runs or feedback records, are not
  part of the v1 MCP surface.

Production apps should install IAK and Boost as dev dependencies. If IAK is
installed outside `require-dev`, runtime route and MCP registration must still
default to disabled in production.

## Error Model

Domain failures are data. Transport errors are exceptional.

Return a normal tool result when the operation ran and found a problem:

- audit violations: `iak.audit.v1` with `status: "failed"`;
- verify failure: `iak.verify.v1` with `status: "failed"`;
- missing server or unavailable route during verify:
  `iak.verify.v1` with `status: "blocked"`;
- unresolved feedback blocking handoff: `iak.verify.v1` or
  `iak.audit.v1` with feedback violations.

Use an MCP error only when the request itself cannot be accepted or represented
as the target schema:

- malformed input;
- missing required input;
- unauthorized environment;
- unknown tool/resource;
- schema lookup failure;
- internal exception before an IAK JSON object can be produced.

Structured error shape:

```json
{
  "schema": "iak.error.v1",
  "status": "blocked",
  "errors": [
    {
      "code": "schema.invalid_input",
      "message": "feedback id is required.",
      "file": null,
      "line": null,
      "details": {
        "tool": "iak_feedback_get"
      }
    }
  ],
  "nextActions": []
}
```

Common error codes:

| Code | Meaning |
| --- | --- |
| `mcp.unauthorized_environment` | MCP server is disabled by environment, config, gate, or host policy. |
| `schema.invalid_input` | Tool input failed JSON Schema validation. |
| `manifest.missing_config` | `.iak/config.json` or `iak.config.json` cannot be resolved. |
| `manifest.stale` | Manifest exists but inputs changed; resource returns stale status. |
| `artifact.invalid_path` | Artifact path is missing, outside allowed roots, or blocked by traversal checks. |
| `feedback.not_found` | Feedback id does not exist. |
| `feedback.evidence_invalid` | Resolution evidence failed validation. |
| `run.locked` | Run directory or feedback record is locked by another process. |
| `verify.environment_blocked` | Required server, route, Storybook instance, or browser executor is unavailable. |
| `brand.source_unavailable` | Brand OS source in `.iak/brand.lock.json` cannot be resolved. |

## Implementation Order

1. **Confirm MCP registration path.** Verify whether the supported Boost
   version can accept third-party MCP tools. If not, register an IAK dev-only
   MCP server through Laravel MCP and document the alongside-Boost setup.
2. **Publish schemas and common helpers.** Add JSON Schemas for
   `iak.mcp-result.v1` and `iak.error.v1`, reuse existing handoff-critical
   schemas, and implement artifact-reference/path validation helpers.
3. **Read-only resources first.** Serve `iak://manifest`,
   `iak://conventions`, `iak://schemas/*`, token/component manifest slices,
   resource contracts, and `iak://brand` without triggering scans or commands.
4. **Manifest tools.** Implement `iak_manifest_read`,
   `iak_manifest_refresh`, `iak_conventions_read`, and `iak_schema_get`.
5. **Feedback tools.** Implement list/get/artifact reads, record locking,
   status changes, and evidence-required resolution against the canonical
   `.iak/feedback/*` store.
6. **Audit tools.** Wire `iak_audit_run` and `iak_audit_get` to the structured
   audit runner and persist artifacts under `.iak/runs/<run-id>/audit/*`.
7. **Verify and handoff tools.** Wire `iak_verify_run`, `iak_verify_get`, and
   `iak_handoff_validate` to the run writer, configured command runner,
   Storybook/Pest/Playwright adapters, feedback gate, and artifact validator.
8. **Token and component query tools.** Add token listing/suggestion,
   style-diff validation, component listing, component contract reads, and
   similar-component search from generated manifest artifacts.
9. **Brand tools.** Add brand status, connect, sync, and audit operations with
   strict upstream-read-only behavior and brand evidence in audit/verify.
10. **Security hardening.** Add environment gates, loopback checks,
    package-specific authorization, concurrency locks, payload size caps,
    artifact root checks, and tests for rejected dangerous inputs.
11. **Compatibility tests.** Assert that IAK does not register Boost-duplicate
    tools, that every tool output validates against its schema, and that
    failed/blocked operations still return structured JSON whenever possible.
