# Storybook HITL And Stories Contract

Status: draft spec  
Date: 2026-05-22  
Owned surface: IAK's reusable UI story contract, Storybook HITL addon,
storybook feedback payloads, and story verification hooks.

## Purpose

Storybook is the reusable UI runtime contract for Inertia Agent Kit. It gives
agents and humans a focused surface for component state review, design-system
verification, typed fixtures, and human-in-the-loop feedback.

Storybook is not a replacement for app-page browser tests. Browser tests still
own routed Inertia behavior, Laravel integration, authorization, validation,
redirects, real navigation, server-provided copy, and end-to-end user flows.

This spec extends these contracts without redefining them:

- `docs/inertia-agent-kit/design-system-contract.md`
- `docs/inertia-agent-kit/feedback-protocol.md`
- `docs/inertia-agent-kit/json-handoff-contract.md`
- `docs/inertia-agent-kit/generated-types-and-formatting.md`

## Goals

- Make stories executable component contracts, not only documentation.
- Require colocated stories for primitives, app components, and eligible
  feature components.
- Require typed fixtures that compose generated Laravel-owned types instead of
  handwritten backend data shapes.
- Normalize Storybook feedback into `iak.feedback.v1`.
- Make Storybook verification part of `iak verify`.
- Give agents a repeatable workflow for creating, checking, and resolving
  Storybook feedback with evidence.

## Non-Goals

- Rebuilding Storybook.
- Making Storybook the default verification surface for full Inertia pages.
- Creating a second feedback schema or feedback store.
- Making a component catalog the product boundary.
- Encouraging agents to invent backend-derived data, translations, formatting,
  or route/action contracts in stories.

## Story Placement Rules

Stories are colocated with the component they contract.

Required:

```txt
resources/js/components/ui/button.tsx
resources/js/components/ui/button.stories.tsx

resources/js/components/app/filter-bar.tsx
resources/js/components/app/filter-bar.stories.tsx

resources/js/features/vehicles/vehicle-table.tsx
resources/js/features/vehicles/vehicle-table.stories.tsx
resources/js/features/vehicles/vehicle.fixtures.ts
```

Rules:

- Every `components/ui/*` primitive requires a colocated story.
- Every `components/app/*` reusable application component requires a colocated
  story.
- A `features/<resource>/*` component requires a colocated story when it is
  reusable, stateful, visually important, exported outside its file, likely to
  be edited by agents, or matched by project config.
- A story file uses the same basename as the component file.
- A story file imports the component through the same public path application
  code would use, unless the adapter requires a relative colocated import.
- Stories must not define new reusable components inline. If a repeated helper
  becomes meaningful, move it to the owning role and give it its own story when
  required.
- Stories must load the same token/theme CSS contract used by the app preview.
  Story-only raw colors, arbitrary spacing, or primitive token references are
  audit failures.

Pages usually do not get stories. Add a page story only when a screen state is
important to review in isolation, such as a complex empty state, a responsive
layout state, or a multi-step visual branch. A page story never satisfies the
browser-test requirement for the real route.

## Story File Contract

IAK adapters may emit React, Vue, or Svelte Storybook files, but every story
must expose the same machine-readable concepts:

- component role: `primitive`, `app-component`, `feature`, or `page`;
- owning path and component path;
- stable Storybook story ID;
- required state exports;
- fixture source path when fixtures are used;
- variant, size, tone, density, permission, and validation states when the
  component supports them;
- Storybook args that are JSON-serializable or explicitly summarized.

React CSF example:

```tsx
import type { Meta, StoryObj } from '@storybook/react'
import { VehicleTable } from './vehicle-table'
import {
  emptyVehicleIndex,
  vehicleIndexWithRows,
} from './vehicle.fixtures'

const meta = {
  title: 'features/vehicles/vehicle-table',
  component: VehicleTable,
  parameters: {
    iak: {
      role: 'feature',
      componentPath: 'resources/js/features/vehicles/vehicle-table.tsx',
      fixturePath: 'resources/js/features/vehicles/vehicle.fixtures.ts',
      requiredStates: ['Default', 'Empty', 'Loading', 'Error'],
    },
  },
} satisfies Meta<typeof VehicleTable>

export default meta

type Story = StoryObj<typeof meta>

export const Default: Story = {
  args: {
    vehicles: vehicleIndexWithRows.vehicles,
    can: vehicleIndexWithRows.can,
    copy: vehicleIndexWithRows.copy,
  },
}

export const Empty: Story = {
  args: {
    vehicles: emptyVehicleIndex.vehicles,
    can: emptyVehicleIndex.can,
    copy: emptyVehicleIndex.copy,
  },
}
```

The `parameters.iak` shape is adapter-owned, but its extracted manifest output
must be stable JSON. If a project keeps `.spec.json` component contracts, IAK
may merge Storybook metadata and `.spec.json`; it must not make agents inspect
both manually.

## Required States

`Default` is required for every story file. Other canonical states are required
when the component can express that state.

| State | Required when |
| --- | --- |
| `Default` | Always. Represents the normal ready state. |
| `Empty` | The component renders a collection, search result, slot, or optional content that can be empty. |
| `Loading` | The component accepts loading, pending, skeleton, optimistic, or async state. |
| `Error` | The component renders an error, retry, alert, failed request, or unavailable state. |
| `Disabled` | The component exposes disabled, read-only, unavailable, or permission-blocked controls. |
| `WithValidationErrors` | The component renders a form, field group, or validation summary. |

Rules:

- State export names are PascalCase and stable.
- Required states must use fixtures or typed args that represent the state
  directly; they must not mutate `Default` at runtime.
- Feature and app form stories must include `WithValidationErrors` when the
  component can show Laravel validation errors.
- Primitive control stories must include `Disabled` when the public API
  supports it.
- Components with finite variants, sizes, tones, or densities must represent
  those options in a story, controls table, or machine-readable contract.
- Missing `Default` is always an audit error.
- Missing applicable optional states starts as an audit warning and can become
  an error through project config.

Project config should be able to tighten this per role:

```json
{
  "stories": {
    "requiredStates": {
      "primitive": ["Default", "Disabled"],
      "app-component": ["Default", "Empty", "Loading", "Error"],
      "feature": ["Default"]
    },
    "featureStoryCriteria": [
      "exported",
      "stateful",
      "visuallyImportant",
      "agentEditable"
    ]
  }
}
```

## Typed Fixtures

Stories use typed fixtures, not inline fake blobs.

Fixture rules:

- Feature fixtures live in `features/<resource>/<resource>.fixtures.ts`.
- Primitive fixtures must stay domain-free and usually belong inside the story
  file unless reused across primitive stories.
- App component fixtures must stay generic. If a fixture needs resource terms,
  the component probably belongs in `features/<resource>`.
- Fixtures import generated, shared, or feature-owned types.
- Fixtures may use story-only copy, but production components should receive
  display-ready copy and formatted values from Laravel page props.
- Fixtures must not call `Intl.NumberFormat`, `Intl.DateTimeFormat`, date
  formatting libraries, route string builders, or handwritten enum label maps
  to create user-facing production-like output.
- Fixtures must not use `any`, broad records, or handwritten copies of Spatie
  Data, Wayfinder, enum, validation, form, or page-prop contracts.

Feature fixture example:

```ts
import type { App } from '@/types/generated'

export type VehicleIndexPageProps =
  App.Data.Vehicles.VehicleIndexPageData

export const vehicleIndexWithRows = {
  vehicles: [
    {
      id: 123,
      name: 'Ford Transit',
      status: { value: 'active', label: 'Active' },
      price: { amount: 129900, currency: 'EUR', formatted: 'EUR 1,299.00' },
      createdAt: {
        iso: '2026-05-22T12:00:00Z',
        formatted: '22 May 2026',
        relative: '2 hours ago',
      },
    },
  ],
  can: {
    createVehicle: true,
    exportVehicles: false,
  },
  copy: {
    title: 'Vehicles',
    createButton: 'Add vehicle',
    emptyTitle: 'No vehicles yet',
    emptyDescription: 'Create the first vehicle to start tracking your fleet.',
  },
} satisfies Pick<VehicleIndexPageProps, 'vehicles' | 'can' | 'copy'>
```

Audit rules:

- `iak/stories/no-inline-backend-shape`: story args define backend-derived
  objects inline instead of importing typed fixtures.
- `iak/stories/fixture-uses-generated-type`: fixtures for backend-derived
  data must use generated or feature aliases.
- `iak/stories/no-any-fixture`: fixtures use `any`, `unknown`, or broad
  records where generated types exist.
- `iak/format/no-local-label-map`: fixtures or stories derive user-facing
  labels from raw backend values in production-like props.

## Storybook Feedback Addon

Package:

```txt
@inertia-agent-kit/storybook-feedback
```

The addon gives humans and agents a Storybook panel or toolbar action for
creating feedback from the current story. It must write into the same
`.iak/feedback/` store as app-page and test feedback.

Capture requirements:

- current Storybook story ID;
- Storybook title, export name, component name, and role when known;
- current args, capped and serialized safely;
- current globals, including theme and locale when relevant;
- current viewport name and pixel dimensions;
- selected element selector, preferring `[data-iak-part='...']`;
- fallback click coordinates when no stable selector exists;
- screenshot artifact;
- closest relevant DOM artifact;
- recent console artifact;
- human or agent message;
- git SHA and adapter context when available.

The addon writes by trying these transports in order:

1. `POST /__iak/feedback` when the Laravel dev server is reachable.
2. Local Node helper writing atomically to `.iak/feedback/` when Storybook is
   running without Laravel.

Both transports must produce the same canonical `iak.feedback.v1` record.

## Addon Payload

The addon may use an internal create payload before normalization:

```json
{
  "schema": "iak.storybook.feedback.create.v1",
  "surface": "storybook",
  "source": "human",
  "producer": "@inertia-agent-kit/storybook-feedback",
  "message": "The empty state action should use the standard primary button.",
  "target": {
    "url": "http://localhost:6006/?path=/story/features-vehicles-vehicletable--empty",
    "storyId": "features-vehicles-vehicletable--empty",
    "selector": "[data-iak-part='empty-state-action']",
    "coordinates": { "x": 842, "y": 512 }
  },
  "viewport": {
    "width": 1440,
    "height": 900,
    "name": "desktop"
  },
  "story": {
    "title": "features/vehicles/vehicle-table",
    "exportName": "Empty",
    "componentName": "VehicleTable",
    "componentPath": "resources/js/features/vehicles/vehicle-table.tsx",
    "fixturePath": "resources/js/features/vehicles/vehicle.fixtures.ts",
    "role": "feature"
  },
  "context": {
    "gitSha": "abc123",
    "adapter": "laravel-inertia-react",
    "storyArgs": {
      "vehicles": [],
      "can": { "createVehicle": true }
    },
    "storyArgsTruncated": false,
    "globals": {
      "theme": "light",
      "locale": "en"
    },
    "componentCandidates": ["VehicleTable", "EmptyState", "Button"]
  },
  "attachments": {
    "screenshot": ".iak/feedback/fbk_01j/screenshot.png",
    "dom": ".iak/feedback/fbk_01j/dom.html",
    "console": ".iak/feedback/fbk_01j/console.json"
  }
}
```

Normalization into `iak.feedback.v1`:

- `schema` becomes `iak.feedback.v1`.
- `surface` is always `storybook`.
- `producer` is `@inertia-agent-kit/storybook-feedback`.
- `target.storyId` is required.
- `target.url` is the Storybook iframe or manager URL when available.
- `target.selector` uses a stable selector when available.
- `context.storyArgs` is the captured args object, capped at 8 KB.
- `context.componentCandidates` starts with the story component and may include
  selected child components inferred from `data-iak-part`.
- `attachments.*` are artifact references under `.iak/feedback/<id>/`.
- Unknown addon fields are preserved only inside `context.addon`.

The canonical feedback record must validate against the same
`iak.feedback.v1` schema used by app and test producers.

## Integration With `iak.feedback.v1`

Storybook feedback is not special after creation. It is one queue item with
`surface: "storybook"`.

Resolver rules:

- Agents list it with `iak feedback list --surface storybook --json` or the
  equivalent MCP tool.
- Agents inspect screenshot, DOM, console, story args, and component candidates
  through feedback artifact references.
- Agents may resolve app-surface feedback by reproducing the issue in a story,
  fixing the reusable component, and attaching the passing story evidence.
- Agents may resolve storybook-surface feedback only with evidence that includes
  changed files, commands run, a post-fix screenshot, audit result, Storybook
  story result, and browser/page result when the fix affects routed behavior.
- The resolve path is still `iak feedback resolve <id> --evidence <path>
  --json`, `POST /__iak/feedback/{id}/resolve`, or
  `iak_feedback_resolve`.
- A prose-only resolution is invalid.

Resolution evidence example:

```json
{
  "schema": "iak.feedback.resolution.v1",
  "summary": "Reused the shared Button primary action in the empty state.",
  "changedFiles": [
    {
      "path": "resources/js/features/vehicles/vehicle-table.tsx",
      "role": "feature",
      "action": "modify"
    },
    {
      "path": "resources/js/features/vehicles/vehicle-table.stories.tsx",
      "role": "story",
      "action": "modify"
    }
  ],
  "commandsRun": [
    { "cmd": "iak audit --json", "exitCode": 0 },
    { "cmd": "iak verify --json", "exitCode": 0 }
  ],
  "artifacts": {
    "screenshotAfter": ".iak/feedback/fbk_01j/resolution/screenshot-after.png",
    "audit": ".iak/feedback/fbk_01j/resolution/audit.json",
    "tests": ".iak/feedback/fbk_01j/resolution/tests.json",
    "storybook": {
      "storyId": "features-vehicles-vehicletable--empty",
      "status": "passed"
    }
  },
  "linkedHandoff": ".iak/runs/run_01j/handoff.json"
}
```

## Storybook Verification Hooks

`iak verify` must include Storybook checks when any changed file affects a
required story, a fixture, a reusable component, or the design-system runtime.

Required local hooks:

- story presence audit for primitives, app components, and eligible feature
  components;
- required-state audit;
- fixture type audit;
- Storybook build or static preview smoke check when configured;
- Storybook test runner execution for changed stories and required stories;
- interaction tests from story `play` functions when present;
- screenshot capture for changed story states;
- console capture for changed story states;
- accessibility check when the project has a configured a11y runner;
- manifest freshness check for story IDs, component paths, fixture paths, and
  required states.

Recommended command shape:

```txt
iak verify --surface storybook --json
iak verify --stories features-vehicles-vehicletable--default --json
```

`iak.verify.v1` should record Storybook evidence by story ID:

```json
{
  "id": "storybook",
  "status": "passed",
  "stories": [
    {
      "storyId": "features-vehicles-vehicletable--default",
      "status": "passed",
      "artifacts": {
        "screenshot": {
          "kind": "screenshot",
          "path": ".iak/runs/run_01j/storybook/features-vehicles-vehicletable--default.png"
        },
        "console": {
          "kind": "json",
          "path": ".iak/runs/run_01j/storybook/features-vehicles-vehicletable--default.console.json"
        }
      }
    }
  ]
}
```

Rules:

- A passing `storybook` check alone is enough for component-contract evidence
  only. Browser-visible route changes still require app URL or page browser
  evidence.
- `status: "passed"` is invalid when required story states are missing,
  Storybook cannot render the changed story, or console errors are present
  without an explicit allowlist.
- Story screenshots are artifact references. They must not be embedded in JSON
  or pasted into agent chat.
- The Storybook preview must run with IAK's token/theme CSS and adapter
  decorators so screenshots reflect the app runtime.

## Story Manifest

IAK should expose stories through the manifest so agents do not scan the whole
repo.

Suggested artifact:

```txt
.iak/manifest/stories.json
```

Suggested shape:

```json
{
  "schema": "iak.storiesManifest.v1",
  "stories": [
    {
      "storyId": "features-vehicles-vehicletable--default",
      "title": "features/vehicles/vehicle-table",
      "exportName": "Default",
      "role": "feature",
      "component": "VehicleTable",
      "componentPath": "resources/js/features/vehicles/vehicle-table.tsx",
      "storyPath": "resources/js/features/vehicles/vehicle-table.stories.tsx",
      "fixturePath": "resources/js/features/vehicles/vehicle.fixtures.ts",
      "requiredStates": ["Default", "Empty", "Loading", "Error"],
      "statesPresent": ["Default", "Empty", "Loading", "Error"],
      "variants": [],
      "tags": ["iak", "feature"]
    }
  ]
}
```

Manifest rules:

- Story IDs must be stable across runs unless a file or title is intentionally
  renamed.
- `statesPresent` must be extracted from Storybook exports, not hand-maintained
  by agents.
- Component and fixture paths are relative to the Laravel project root.
- The manifest should link to component contracts where available instead of
  duplicating large type or arg schemas.

## Page Vs Component Boundaries

Use Storybook for component contracts:

- primitive API, variants, sizes, disabled state, focus/hover affordances;
- app component reuse, empty/loading/error states, generic composition;
- feature component state surfaces using generated typed fixtures;
- design-system token and theme rendering;
- visual HITL feedback on reusable UI;
- interaction tests that do not need a real Laravel route.

Use app-page browser tests for routed behavior:

- Inertia route renders and page prop delivery;
- Laravel authorization, policies, redirects, and middleware;
- server validation and validation-message display;
- form submission through Wayfinder or Inertia router;
- navigation between pages and layouts;
- loading/error behavior caused by real requests;
- backend-owned formatting, translation, and copy in real page props;
- full-page screenshots, console logs, and accessibility checks.

Boundary rules:

- A page story may review page composition, but it does not prove the Inertia
  route works.
- A component story may prove a reusable state contract, but it does not prove
  Laravel sends the correct props.
- Fixes to reusable UI should usually be verified first in Storybook and then,
  when route-visible, in the relevant app page.
- Storybook feedback may point to a component; app feedback may point to a
  route. The shared feedback queue lets the resolver connect both.

## Agent Workflow

An agent creating or changing reusable UI should follow this loop:

1. Read `iak.manifest.v1`, the story manifest, and relevant component
   contracts.
2. Produce or validate an `iak.plan.v1` file plan with component, fixture,
   story, test, and page files grouped by role.
3. Inspect existing primitives, app components, feature components, stories,
   and typed fixtures before creating new UI.
4. Scaffold with IAK commands when available.
5. Implement the component and colocated story within the approved write set.
6. Add or update typed fixtures using generated backend-owned types.
7. Run typecheck, lint, and `iak audit --json`.
8. Run Storybook verification for changed stories.
9. Run Pest Browser or Playwright when the change is route-visible.
10. Inspect screenshot and console artifacts.
11. List unresolved feedback and resolve only with evidence.
12. Create and validate `iak.handoff.v1`.

Minimum handoff evidence for Storybook-visible work:

- changed files grouped by role;
- audit result;
- type/test result;
- Storybook story ID and status;
- screenshot artifact for the changed story;
- console artifact or console error count;
- accessibility result when available;
- unresolved feedback count;
- app URL evidence when the change affects a routed page.

## Audit Rule IDs

Suggested Storybook-specific audit rules:

| Rule | Severity | Meaning |
| --- | --- | --- |
| `iak/stories/missing-required-story` | error | Required component role has no colocated story. |
| `iak/stories/missing-default-state` | error | Story file has no `Default` export. |
| `iak/stories/missing-required-state` | warning or error | Applicable canonical state is missing. |
| `iak/stories/no-inline-backend-shape` | error | Story args define backend-derived objects inline. |
| `iak/stories/fixture-uses-generated-type` | error | Backend-derived fixture does not use generated or feature-owned types. |
| `iak/stories/no-any-fixture` | error | Fixture or story uses `any`, `unknown`, or broad records where generated types exist. |
| `iak/stories/story-id-changed` | warning | Story ID changed without a rename marker or migration note. |
| `iak/stories/console-error` | error | Story render produced console errors without an allowlist. |
| `iak/stories/manifest-stale` | error | `.iak/manifest/stories.json` does not match current stories. |
| `iak/stories/page-story-without-browser-check` | warning | Page story exists but no matching browser verification is configured. |

## Open Questions

- Should `.spec.json` remain a first-class source file, be generated from CSF
  metadata, or be replaced by `.iak/manifest/stories.json` and component
  manifest extraction?
- Which missing story states should fail by default in v1 versus remain
  warnings until project config opts in?
- What is the exact cross-renderer shape of `parameters.iak` for Vue and
  Svelte adapters?
- Should Storybook screenshots be captured through the Storybook test runner,
  Playwright directly, or both depending on adapter capability?
- How should non-serializable args be summarized so feedback remains useful
  without violating the JSON handoff token budget?
- Should the addon include a selector picker, freehand coordinates, or both in
  v1?
- Should Storybook feedback use the package name
  `@inertia-agent-kit/storybook-feedback` or a shorter producer ID such as
  `iak.storybook-addon` in canonical records?
- How much page-story support should exist before the Laravel package has
  strong page-map and route verification commands?
