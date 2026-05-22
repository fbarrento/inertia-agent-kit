# Inertia Agent Kit Implementation RFC

Status: Lead orchestration RFC

Source context:

- Solo scratchpad: `solo://proj/7/scratchpad/inertia-agent-kit-or--36`
- Positioning: `docs/inertia-agent-kit/positioning.md`
- Worker lane specs in `docs/inertia-agent-kit/*.md`

## Product Boundary

Inertia Agent Kit is an AI-native convention, feedback, design-system contract,
and verification layer for Inertia apps.

It exists because AI-generated frontend code usually does not scale: agents
create large page files, duplicate components, invent styling, handwrite
backend-derived types, skip tests, ignore rendered outcomes, and hand off prose
instead of evidence.

IAK gives frontend agents Laravel-like constraints:

- resource-controller page mapping;
- thin Inertia page adapters;
- resource-local features;
- generated backend-derived TypeScript;
- backend-owned formatting and translation;
- token-bound design-system primitives;
- Storybook contracts for reusable UI;
- one HITL feedback queue;
- browser-visible verification;
- JSON-first plans, audits, runs, feedback, manifests, and handoffs.

IAK is not a replacement for Laravel Boost, Storybook, Pest Browser,
Playwright, Spatie Data, Wayfinder, Instruckt, or Brand OS. It composes them
into one enforceable agent workflow.

## Authoritative Specs

| Area | Source |
| --- | --- |
| Product positioning | `docs/inertia-agent-kit/positioning.md` |
| Laravel package and Boost integration | `docs/inertia-agent-kit/laravel-boost-integration.md` |
| Config, manifest, role graph, adapters | `docs/inertia-agent-kit/config-manifest-and-adapters.md` |
| Generated types and backend-owned formatting | `docs/inertia-agent-kit/generated-types-and-formatting.md` |
| Design-system tokens and components | `docs/inertia-agent-kit/design-system-contract.md` |
| Feedback protocol | `docs/inertia-agent-kit/feedback-protocol.md` |
| JSON handoff contract | `docs/inertia-agent-kit/json-handoff-contract.md` |
| Scaffolding commands | `docs/inertia-agent-kit/scaffolding-commands.md` |
| Structured audit checks | `docs/inertia-agent-kit/audit-checks.md` |
| Storybook HITL and stories | `docs/inertia-agent-kit/storybook-hitl-and-stories.md` |
| Brand OS integration | `docs/inertia-agent-kit/brand-os-integration.md` |
| Verification loop | `docs/inertia-agent-kit/verification-loop.md` |
| IAK MCP tools/resources | `docs/inertia-agent-kit/mcp-tools-and-resources.md` |

This RFC coordinates those specs. It should not duplicate every field from
them. If detail conflicts, update the lane spec first, then update this RFC.

## Naming And Packaging

Working product name:

```txt
Inertia Agent Kit
```

Suggested package shape:

```txt
inertia-agent-kit/laravel        # Laravel package, dev-only service provider
@inertia-agent-kit/cli           # JS/Node implementation for AST, manifests, Storybook
@inertia-agent-kit/storybook     # Storybook HITL addon
@inertia-agent-kit/adapter-react
@inertia-agent-kit/adapter-vue
@inertia-agent-kit/adapter-svelte
```

Suggested CLI:

```txt
iak
```

Laravel is the first package path. React is the first renderer adapter. Vue and
Svelte must remain adapter-shaped, but they should not block the first
Laravel/Inertia/React milestone.

## Non-Goals

- Do not build a full component library.
- Do not rebuild Storybook.
- Do not replace Laravel Boost.
- Do not force Feature-Sliced Design.
- Do not generate top-level `queries`, `actions`, `forms`, `hooks`, or
  `composables` folders by default.
- Do not trust agents to handwrite backend-derived types.
- Do not let production frontend code own locale-sensitive formatting,
  translation, enum labels, validation messages, money, dates, or pluralization.
- Do not make MCP a thin wrapper around CLI text output.
- Do not require Instruckt, but keep the feedback protocol compatible with it.
- Do not merge Brand OS into IAK. Brand OS remains upstream.

## Core Architecture

### Laravel Package

The Laravel package owns:

- service provider;
- dev-only route registration;
- install/doctor commands;
- config publishing;
- Boost guidelines and skills;
- feedback HTTP endpoints;
- package integration with Spatie Data, Wayfinder, Pest Browser, and Boost;
- coordination with the JS/CLI package where AST, manifests, Storybook, and
  token transformations are easier in Node.

Boost remains responsible for generic Laravel context: docs, app info, logs,
browser logs, database/schema tools, absolute URLs, and baseline MCP setup.
IAK adds only Inertia/frontend-specific discipline.

### Config And Manifest

`iak.config.json` is source-controlled project configuration.

`iak.manifest.v1` is generated, compact, deterministic, and agent-facing. It
must be the first source agents query before opening arbitrary source files.

The manifest should include slices for:

- project and adapter;
- role graph;
- resources and controller mapping;
- generated type locations;
- tokens, components, stories;
- feedback and verification commands;
- Brand OS connection when enabled;
- artifact references, not large inlined content.

### Frontend Roles

Default role model:

```txt
pages/<resource>/*        route adapters only
features/<resource>/*     resource workflow UI and resource-local behavior
components/ui/*           token-bound primitives
components/app/*          reusable app components
layouts/*                 app shells
lib/*                     pure framework-free helpers
types/generated/*         read-only backend-derived types
types/shared/*            small generic frontend-only types
```

Pages mirror Laravel resource controllers:

```txt
VehicleController@index  -> pages/vehicles/index
VehicleController@show   -> pages/vehicles/show
VehicleController@create -> pages/vehicles/create
VehicleController@edit   -> pages/vehicles/edit
```

Mutating actions use generated Wayfinder output. They do not become page files
and do not create global `actions/*`.

### Types, Formatting, And Translation

PHP is the source of truth for backend-derived types.

Default stack:

- Spatie Laravel Data for page DTOs, resource DTOs, form payloads, filters, and
  display/value shapes.
- `spatie/laravel-typescript-transformer` for TypeScript output.
- Wayfinder for route/controller actions.

Production frontend code renders display-ready strings from page props. Laravel
owns translated copy, validation messages, enum labels, formatted dates,
formatted money, pluralization, and domain-sensitive wording.

### Design-System Contract

IAK keeps the useful design-system IP from the current repo:

- primitives in `tokens.css`;
- semantic `--ds-*` tokens in `themes.css`;
- Tailwind v4 bridge utilities such as `bg-ds-surface`;
- `@theme` collision audit;
- raw hex, arbitrary value, primitive color, and primitive variable checks;
- colocated stories for primitives and reusable app components.

App code uses semantic utilities. Brand values change behind those utilities.

### Feedback

All HITL feedback goes into `iak.feedback.v1` records under `.iak/feedback/*`.

Producers may include:

- app overlay;
- Storybook addon;
- tests;
- agents;
- Instruckt-compatible adapters.

Agents cannot resolve feedback without evidence. Resolution must include a
summary, changed files, verification artifacts, and unresolved count.

### Verification

`iak verify` orchestrates evidence. It should not duplicate lower-level tools.

It coordinates:

- `iak audit --json`;
- typecheck/lint commands;
- Pest Browser for Laravel route-visible checks;
- Playwright where configured;
- Storybook tests/builds/screenshots for reusable UI;
- feedback unresolved checks;
- Brand OS sync/audit when enabled;
- screenshot, console, accessibility, DOM, trace, and artifact collection.

Output is `iak.verify.v1` under `.iak/runs/<run-id>/`.

### JSON Handoffs

Agent-facing mode is JSON-first.

```txt
IAK_AGENT=1
iak ... --json
```

Critical schemas:

- `iak.plan.v1`
- `iak.audit.v1`
- `iak.feedback.v1`
- `iak.verify.v1`
- `iak.handoff.v1`
- `iak.manifest.v1`

Large logs, DOM, screenshots, markdown, token JSON, SVGs, and CSS are artifact
paths, not chat payloads.

### MCP

IAK MCP must expose structured resources and IAK-specific tools only. It must
not duplicate Boost's generic Laravel tools.

Expected categories:

- manifest/conventions resources;
- token/component/story/brand resources;
- audit/verify tool calls;
- feedback list/get/screenshot/resolve tool calls;
- artifact path references;
- schema-aligned JSON results.

### Brand OS

Brand OS stays upstream.

```txt
Brand OS = creates the brand contract
IAK = consumes, applies, audits, and verifies that contract in an app
```

The integration point is `brand.json` plus token/assets artifacts. IAK should
support:

```txt
iak brand connect
iak brand sync
iak brand audit
```

and write `.iak/brand.lock.json`.

Laravel still owns production translations and page copy delivery.

## Build Order

### Phase 0: Repositioning

Deliverables:

- product positioning;
- package boundary;
- non-goals;
- retained design-system IP;
- Boost and Brand OS boundaries.

Status: specified.

### Phase 1: Contracts

Deliverables:

- `iak.config.json` schema draft;
- `iak.manifest.v1` schema draft;
- role graph;
- renderer adapter interface;
- generated types contract;
- design-system contract;
- JSON handoff contract.

Status: specified.

### Phase 2: Authoring And Auditing

Deliverables:

- scaffolding command contract;
- `iak plan validate`;
- `iak audit --json`;
- first style/type/page/story/feedback audit checks.

Status: specified.

### Phase 3: Feedback And MCP

Deliverables:

- `.iak/feedback` store;
- feedback HTTP/CLI/MCP surfaces;
- IAK-specific MCP resources/tools.

Status: specified.

### Phase 4: Verification

Deliverables:

- `.iak/runs/<run-id>` artifact layout;
- `iak.verify.v1`;
- `iak verify` orchestration;
- evidence-based handoff integration.

Status: specified.

### Phase 5: Storybook And Brand Integration

Deliverables:

- Storybook feedback addon contract;
- story manifest;
- Storybook verification hooks;
- Brand OS `brand.json` connection, sync, audit, and lock file.

Status: specified.

## First Implementation Milestone

The first implementation milestone should prove the loop, not every product
surface:

> In a Laravel + Inertia + React fixture, an agent can initialize IAK, scaffold
> a `vehicles` resource split, use generated backend-derived type imports, use
> token-bound UI, inspect feedback, run verification, and produce a JSON handoff
> with evidence.

Acceptance criteria:

- `iak init --json` writes config and agent rules for a Laravel/Inertia/React
  fixture.
- `iak manifest --json` or equivalent emits `iak.manifest.v1`.
- `iak new resource vehicles --dry-run --json` emits an `iak.plan.v1`.
- `iak new resource vehicles --apply --json` creates pages, features, typed
  fixtures, and required stories.
- Scaffolded code imports generated type paths; it does not handwrite backend
  DTO copies.
- Scaffolded code uses semantic `ds-` utilities.
- `iak audit --json` catches at least:
  - one page responsibility violation;
  - one generated-type drift violation;
  - one raw style/token violation;
  - one missing-story violation;
  - one unresolved feedback violation.
- `iak feedback list/show/resolve --json` works against local feedback records.
- `iak verify --json` writes `.iak/runs/<run-id>/verify.json` with audit, test,
  screenshot/console metadata, feedback status, and brand status when enabled.
- `iak handoff create --json` writes `iak.handoff.v1`.
- `iak handoff validate` fails if required evidence is missing.

Current baseline status, 2026-05-22:

- Implemented as a focused Node CLI proof in `src/iak.mjs` with `bin/iak.mjs`.
- Package metadata now points at
  `git@github.com:fbarrento/inertia-agent-kit.git`.
- `iak init --apply --json` writes config, rules, manifest paths, generated
  type roots, token CSS, a layout, and a UI primitive/story.
- `iak new resource vehicles --apply --json` creates thin page adapters,
  resource-local feature files, generated type imports, fixtures, and colocated
  stories.
- `iak audit --json` writes `iak.audit.v1` artifacts and catches deliberate
  style violations plus core convention drift.
- `iak feedback create/list/show/resolve --json` works against local
  `iak.feedback.v1` records and requires evidence for resolution.
- `iak verify --json` runs audit, blocks on unresolved feedback, then writes
  `iak.verify.v1` with screenshot metadata and a local screenshot artifact.
- `npm test` proves the Laravel + Inertia + React fixture loop with 32 smoke
  assertions.

Still pending after the first baseline:

- real Laravel package/service provider;
- real Spatie Data and Wayfinder integration;
- real Pest Browser or Playwright execution;
- Storybook addon implementation;
- MCP server implementation;
- `iak.handoff.v1` create/validate commands.

Out of scope for this milestone:

- full MCP server implementation unless needed for local tool proof;
- Vue/Svelte templates beyond schema-level adapter contracts;
- visual regression baselines;
- full Storybook addon UI;
- full Brand OS import implementation.

## Immediate Open Decisions

1. Whether `.spec.json` remains first-class, is generated from Storybook, or is
   replaced by manifest extraction.
2. Whether adapted Brand OS output defaults to `resources/css/ds/*` or
   `resources/css/brand/*`.
3. Whether `iak verify --mode auto` may start dev servers in v1.
4. Whether Pest Browser is required by default for Laravel apps or preferred
   when installed.
5. Exact Boost extension APIs for package-provided MCP tools, guidelines, and
   skills.
6. Exact Wayfinder generated filenames after the installer pins a Wayfinder
   version.
7. Canonical Storybook producer ID:
   `@inertia-agent-kit/storybook-feedback` vs `iak.storybook-addon`.

## Lead Responsibilities

The lead lane owns:

- resolving the open decisions;
- keeping this RFC in sync with lane specs;
- selecting the first implementation slice;
- preventing scope creep back into generic design-system or framework-agnostic
  tooling;
- deciding when a spec lane is complete enough to unblock implementation.
