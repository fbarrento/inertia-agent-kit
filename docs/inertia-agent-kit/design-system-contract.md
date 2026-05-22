# Design System Contract

Status: draft spec  
Date: 2026-05-22  
Owned surface: IAK's token, styling, component-story, manifest, and
design-system audit contract.

## Purpose

Inertia Agent Kit is not a component library. It still needs a design-system
contract because agents need executable rules for styling, theming, reusable
components, stories, and verification.

This spec preserves the design-system-kit IP that matters for IAK:

- `registry/items/core/tokens.css`: primitive raw values.
- `registry/items/core/themes.css`: semantic `--ds-*` variables.
- `registry/items/core/bridge.css`: Tailwind v4 `@theme inline` bridge.
- `registry/items/primitive-button/*`: canonical primitive component shape.
- `src/audit.mjs` and `src/catalog.mjs`: current audit and component-contract
  checks that should become structured IAK checks.

IAK should adapt those ideas to Laravel + Inertia apps, where Boost supplies
generic Laravel context and IAK supplies Inertia-specific frontend discipline.

## Token Model

IAK uses a three-tier token model.

### Tier 1: Primitives

File:

```txt
tokens.css
```

Primitives are raw values only: color ramps, spacing scale, radius scale,
typography scale, shadows, motion, and z-index. They have no semantic meaning.

Rules:

- Raw hex values and raw scale values are allowed only in primitive token
  files.
- Components, pages, feature code, and stories must never reference primitive
  variables directly.
- If a value is genuinely missing, add a primitive token and map it through the
  semantic tier. Do not inline the value at the call site.

### Tier 2: Semantic Tokens

File:

```txt
themes.css
```

Semantic tokens are named by intent and prefixed `--ds-*`. Examples from the
current contract include:

```txt
--ds-color-bg
--ds-color-surface
--ds-color-text-muted
--ds-color-action
--ds-space-inset-md
--ds-radius-control
```

Rules:

- Semantic tokens point to primitives.
- Themes remap semantic tokens to different primitives.
- Components stay unchanged across themes because they consume semantic
  utilities, not primitive values.
- The default theme is `:root`.
- Dark theme support must work with both `[data-theme="dark"]` and `.dark` for
  shadcn compatibility.
- Additional themes change only semantic mappings unless a deliberate token
  contract change is required.

### Tier 3: Tailwind Bridge

File:

```txt
bridge.css
```

The bridge exposes semantic tokens as Tailwind v4 utilities through
`@theme inline`.

Every bridge variable must be `ds`-prefixed inside its Tailwind category:

```css
@theme inline {
  --color-ds-surface: var(--ds-color-surface);
  --spacing-ds-inset-md: var(--ds-space-inset-md);
  --radius-ds-control: var(--ds-radius-control);
}
```

This generates utilities such as:

```txt
bg-ds-surface
text-ds-muted
p-ds-inset-md
gap-ds-stack-sm
rounded-ds-control
```

Do not rename bridge variables to `--ds-color-*`. Tailwind turns the segment
after the category into the utility name, so `--ds-color-muted` would not
generate `text-ds-muted`.

Recommended import order:

```css
@import "tailwindcss";
@import "./tokens.css";
@import "./themes.css";
@import "./bridge.css";
```

The prefix rule is structural, not stylistic. It prevents collisions with
shadcn or any other library's `@theme` block regardless of import order.

## Styling Rules For Agents

Agents must style through semantic utilities and reusable components.

Allowed:

```txt
bg-ds-surface
text-ds-default
text-ds-muted
border-ds-border-default
p-ds-inset-md
gap-ds-stack-sm
rounded-ds-control
```

Disallowed outside token/theme files:

```txt
bg-[#ffffff]
text-slate-700
bg-blue-500
p-[34px]
rounded-[11px]
style={{ color: "#fff" }}
var(--neutral-500)
```

Decision procedure:

1. Reuse an existing primitive or app component when it already matches the
   need.
2. Use `ds-` semantic utilities for color, component spacing, radius, focus,
   and intent-driven visual decisions.
3. Use normal Tailwind scale utilities only for layout sizing and positioning
   that are not design-system decisions.
4. When no semantic utility fits, propose a token contract change instead of
   inlining a one-off value.

## Component Roles

IAK should name component roles in the app's Inertia structure, while allowing
the existing design-system-kit `shared/ui` registry layer to map to the IAK
primitive role during migration.

### Primitive Components

Path role:

```txt
resources/js/components/ui/*
```

Historical registry equivalent:

```txt
shared/ui
```

Primitive components are reusable, low-level, domain-free, and token-bound.
The current `Button` registry item is the canonical reference: variants map to
semantic intents and class strings use utilities such as `bg-ds-action`,
`text-ds-on-action`, `p-ds-inset-*`, `gap-ds-stack-sm`, and
`rounded-ds-control`.

Requirements:

- Colocated story is required.
- Small typed public API is required.
- Variant and size sets must be finite and represented in the story states.
- Styling must use semantic `ds-` utilities or approved utility helpers.
- State should be expressible through props and useful `data-*` attributes.
- No backend, resource, or domain concepts.
- No raw Tailwind color scales, arbitrary values, inline hex, or direct
  primitive variable use.

Expected shape:

```txt
resources/js/components/ui/button.tsx
resources/js/components/ui/button.stories.tsx
resources/js/components/ui/button.spec.json
```

The `.spec.json` file is optional only if the Storybook metadata and generated
manifest can provide the same machine-readable contract. During migration,
preserve `.spec.json` support because the existing catalog builder already
proves this contract.

### App Components

Path role:

```txt
resources/js/components/app/*
```

App components are reusable product/application components that are not
low-level primitives.

Examples:

```txt
page-header.tsx
empty-state.tsx
data-table.tsx
filter-bar.tsx
```

Requirements:

- Colocated story is required.
- May compose primitives and generic feature data.
- Must remain generic enough to reuse across resources.
- Must not use resource-specific names or backend-specific assumptions.
- Generic generated/shared types are allowed; resource-specific types belong
  under `features/<resource>`.

### Feature Components

Path role:

```txt
resources/js/features/<resource>/*
```

Feature components own resource-specific workflow UI.

Examples:

```txt
resources/js/features/vehicles/vehicle-table.tsx
resources/js/features/vehicles/vehicle-form.tsx
resources/js/features/vehicles/vehicle-filters.tsx
resources/js/features/vehicles/vehicle.fixtures.ts
```

Requirements:

- Must use generated backend-derived types from the configured type strategy.
- Must use typed local fixtures for stories.
- Must get stories when reusable, stateful, visually important, exported
  outside the file, likely to be edited by agents, or required by config.
- If a feature component becomes generic, promote it to
  `components/app/*` and update imports.

## Storybook Runtime Contract

Storybook is the runtime contract for reusable UI in IAK. It is not a
marketing documentation site and it is not a replacement for app-page browser
tests.

Stories are required for:

- every `components/ui/*` primitive;
- every `components/app/*` reusable component;
- feature components that match the configured story criteria.

Pages usually do not get stories by default. Browser tests and IAK
verification cover pages. Add page stories only for important reviewable
screen states.

Canonical story states:

```txt
Default
Empty
Loading
Error
Disabled
WithValidationErrors
```

Rules:

- Stories must use typed fixtures, not arbitrary inline blobs.
- Story fixtures should import generated, shared, or feature-owned types
  instead of inventing backend-derived shapes.
- Stories are part of `iak verify`.
- Storybook test results and feedback must use the same IAK feedback queue as
  app-page feedback and browser-test feedback.
- Storybook feedback payloads should include story id, args, viewport,
  selector or coordinates, screenshot reference, console output, and user
  message.

The existing `.spec.json` catalog format remains useful as a compact
machine-readable component contract. IAK can keep it, generate it from stories,
or generate the manifest directly from Storybook CSF metadata. The runtime
contract is Storybook; the agent contract is structured JSON.

## Agent-Facing Manifest

IAK must expose the design-system surface as structured data so agents can
avoid broad scans and one-off UI invention.

The manifest should include:

- token files and import order;
- primitive token names grouped by category;
- semantic token names grouped by intent;
- Tailwind utility names generated by the bridge;
- component roles and paths;
- component public API summaries;
- variants, sizes, tones, states, and required story states;
- story ids and fixture paths;
- allowed style utility examples;
- audit rules that apply to each component role.

Sketch:

```json
{
  "schema": "iak.designSystemManifest.v1",
  "tokens": {
    "semantic": [
      "ds-color-bg",
      "ds-color-surface",
      "ds-color-action"
    ],
    "utilities": [
      "bg-ds-surface",
      "text-ds-muted",
      "p-ds-inset-md"
    ]
  },
  "components": {
    "Button": {
      "role": "primitive",
      "path": "resources/js/components/ui/button.tsx",
      "story": "resources/js/components/ui/button.stories.tsx",
      "contract": "resources/js/components/ui/button.spec.json",
      "variants": ["primary", "secondary", "danger"],
      "sizes": ["sm", "md", "lg"],
      "requiredStates": ["Default", "Disabled"]
    }
  }
}
```

IAK-specific MCP resources or commands should support these read-only queries:

```txt
list_tokens(category)
suggest_token(rawValue)
list_components(role)
get_component_contract(name)
find_similar_component(description)
validate_style_diff(diff)
```

These are IAK concerns because they expose Inertia frontend design-system
context. Generic Laravel facts remain Boost concerns.

## Audit Checks

`iak audit --json` must keep the existing design-system checks and extend them
for IAK's component/story model.

Required failures:

- arbitrary Tailwind values outside token files;
- raw hex colors outside token files;
- primitive Tailwind color utilities such as `bg-blue-500` in components,
  pages, features, and stories;
- direct primitive CSS variable usage such as `var(--neutral-500)` outside
  token/theme files;
- duplicate `@theme` variable names across CSS files;
- bridge variables that do not use the category-prefixed `ds` pattern such as
  `--color-ds-*`, `--spacing-ds-*`, and `--radius-ds-*`;
- missing required stories for `components/ui/*`;
- missing required stories for `components/app/*`;
- missing required story states for configured components once the project
  chooses to make those failures instead of warnings;
- stories or fixtures that invent backend-derived data shapes instead of using
  generated/shared/feature-owned types;
- component role violations, such as domain concepts in primitives or
  resource-specific app components.

Recommended warnings before strict enforcement:

- missing optional story states such as `Loading`, `Error`, or
  `WithValidationErrors`;
- components with variants not represented in Storybook;
- semantic token additions that are not exposed through the bridge;
- duplicate or near-duplicate component shapes that should be reused or
  promoted.

Audit output must be structured enough for agents to self-heal:

```json
{
  "rule": "design-system.raw-hex",
  "severity": "error",
  "file": "resources/js/components/ui/button.tsx",
  "line": 12,
  "message": "Raw hex colors are forbidden outside token files.",
  "suggestion": "Use a ds semantic utility or add a semantic token."
}
```

## Verification Contract

`iak verify` should include the design-system checks that can be run locally:

- style audit;
- token bridge audit;
- story presence audit;
- Storybook build or test run when configured;
- manifest generation or manifest freshness check.

The first milestone should prove:

- core token files can be installed or discovered;
- the canonical Button primitive remains valid;
- at least one primitive has a colocated story or machine-readable contract;
- deliberate raw hex, arbitrary value, primitive color, and `@theme` collision
  violations are caught;
- a small token/component manifest is generated or exposed for agent
  consumption.

## Open Questions

- Whether `.spec.json` remains a first-class source file, is generated from
  stories, or is replaced by manifest extraction from Storybook metadata.
- Exact `iak.config` knobs for required feature-component stories and required
  story states.
- Exact MCP shape for token suggestion and style-diff validation.
- How much of the current `ds` CLI registry moves into the Laravel package
  versus remains as historical source material.
