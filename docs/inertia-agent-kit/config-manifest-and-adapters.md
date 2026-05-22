# IAK Config, Manifest, and Adapters

Status: current baseline contract
Owner: Inertia Agent Kit

IAK needs one small, stable query surface for agents. Agents should read the
generated manifest first, then open source files only when the manifest points
to a specific file they need.

## Artifacts

| File | Owner | Purpose |
| --- | --- | --- |
| `iak.config.json` | Source-controlled app config | Declares app shape, role graph, paths, commands, and token budget. |
| `.iak/config.json` | IAK runtime copy | Local package/runtime config derived from `iak.config.json`. |
| `.iak/manifest/iak.manifest.v1.json` | Generated, read-only | Compact agent-facing index built from config, app paths, resources, stories, tokens, feedback, and run state. |
| `ds.config.json` | Optional legacy input | Migration-only input from the earlier design-system-kit prototype. New projects should not require it. |

The config is the durable contract. The manifest is a deterministic cache that
agents query instead of reading the whole repo.

## Current Baseline Config

The first implementation writes this shape:

```json
{
  "schema": "iak.config.v1",
  "project": {
    "framework": "laravel",
    "inertia": true,
    "adapter": "laravel-inertia-react"
  },
  "paths": {
    "root": "resources/js",
    "pages": "resources/js/pages",
    "features": "resources/js/features",
    "componentsUi": "resources/js/components/ui",
    "componentsApp": "resources/js/components/app",
    "layouts": "resources/js/layouts",
    "typesGenerated": "resources/js/types/generated",
    "css": "resources/css/iak",
    "manifest": ".iak/manifest/iak.manifest.v1.json",
    "feedback": ".iak/feedback",
    "runs": ".iak/runs"
  },
  "generated": {
    "types": "resources/js/types/generated/index.d.ts",
    "routes": "resources/js/routes/generated",
    "actions": "resources/js/actions/generated"
  },
  "conventions": {
    "pages": "route-adapters-only",
    "features": "resource-local-ui",
    "formatting": "backend-owned",
    "translations": "backend-owned",
    "styling": "semantic-design-system-tokens-only"
  },
  "commands": {
    "audit": "iak audit --json",
    "verify": "iak verify --json"
  }
}
```

## Manifest Shape

`iak.manifest.v1` is generated under `.iak/manifest/`.

Required slices:

- `project`: framework, Inertia status, adapter.
- `paths`: source roots and generated artifact roots.
- `resources`: known resource folders and generated page/feature paths.
- `conventions`: compact rules agents must obey.
- `commands`: JSON-first command surface.
- `tokens`: token/theme/bridge CSS paths and hashes when implemented.
- `components`: reusable UI and app component entries when implemented.
- `stories`: colocated story entries when implemented.
- `feedback`: pending and in-progress feedback counts.
- `runs`: recent verify/audit artifact references.
- `brand`: Brand OS connection status when enabled.

Rules:

- Paths are project-relative POSIX paths.
- Manifest entries are summaries, not source dumps.
- Large content is always an artifact path.
- Regenerate the manifest after scaffold, audit, verify, feedback, and brand
  sync operations that change agent-visible state.

## Command Surface

Current implemented commands:

```bash
iak init --apply --json
iak new resource vehicles --apply --json
iak audit --json
iak feedback create --message "..." --route vehicles.index --json
iak feedback list --json
iak feedback show fbk_... --json
iak feedback resolve fbk_... --summary "..." --evidence .iak/runs/run_.../audit.json --json
iak verify --json
```

Planned manifest commands:

```bash
iak manifest refresh --json
iak manifest read --json
iak manifest query --select resources --json
```

Agent mode:

- `IAK_AGENT=1` defaults output to JSON.
- `--json` always emits one JSON object.
- JSON results reference artifacts by path instead of embedding logs,
  screenshots, DOM, CSS, SVG, generated types, or token files.

## Adapter Contract

React is the first adapter. Vue and Svelte must stay adapter-shaped, but they
do not block the first Laravel/Inertia/React milestone.

Adapter responsibilities:

- page file extension and template style;
- feature component extension and template style;
- story extension and metadata style;
- layout import shape;
- generated type import convention;
- Storybook decorator/runtime integration when enabled.

The current baseline implements React templates directly in `src/iak.mjs`.
Future work should extract adapter templates behind an interface similar to:

```ts
type RendererAdapter = {
  id: 'react' | 'vue' | 'svelte'
  pageExtension: string
  componentExtension: string
  storyExtension: string
  renderPage(args: PageTemplateArgs): string
  renderFeature(args: FeatureTemplateArgs): string
  renderStory(args: StoryTemplateArgs): string
}
```

## Role Graph

Default role model:

```txt
pages/<resource>/*        route adapters only
features/<resource>/*     resource workflow UI and resource-local behavior
components/ui/*           token-bound primitives
components/app/*          reusable app components
layouts/*                 app shells
lib/*                     pure framework-free helpers
types/generated/*         read-only backend-derived types
```

Rules:

- Do not generate top-level `queries`, `actions`, `forms`, `hooks`, or
  `composables` folders.
- `resources/js/actions/generated` is reserved for generated Wayfinder output.
- `resources/js/routes/generated` is reserved for generated route output.
- Resource pages mirror Laravel resource controller actions.
- Mutating controller actions do not become pages.

## Resource Controller Mapping

| Laravel action | Frontend page | Notes |
| --- | --- | --- |
| `VehicleController@index` | `pages/vehicles/index.tsx` | Thin adapter. |
| `VehicleController@show` | `pages/vehicles/show.tsx` | Thin adapter. |
| `VehicleController@create` | `pages/vehicles/create.tsx` | Thin adapter. |
| `VehicleController@edit` | `pages/vehicles/edit.tsx` | Thin adapter. |
| `VehicleController@store` | none | Use generated Wayfinder action. |
| `VehicleController@update` | none | Use generated Wayfinder action. |
| `VehicleController@destroy` | none | Use generated Wayfinder action. |

## Verification Contract

Required checks:

- `iak audit --json` for convention and design-system violations.
- feedback unresolved count before handoff.
- verify artifact under `.iak/runs/<run-id>/verify.json`.
- browser-visible evidence metadata when UI is route-visible.

The first baseline writes a local placeholder screenshot artifact so the JSON
shape and evidence flow are testable before real Pest Browser or Playwright
integration lands.
