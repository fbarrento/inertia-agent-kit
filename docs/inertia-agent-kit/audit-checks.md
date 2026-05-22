# Structured Audit Checks

Status: draft spec
Schema family: `iak.audit.v1`
Owner: Inertia Agent Kit
Source of truth: this document

## Purpose

`iak audit --json` is the machine-readable enforcement layer for IAK
conventions. It must produce stable, compact JSON that another agent can use to
repair the codebase without reading prose docs or guessing intent.

The audit covers:

- Inertia page responsibility.
- Frontend role boundaries and import graph rules.
- Generated backend type ownership.
- Backend-owned formatting and translation.
- Design-system token and styling rules.
- Required Storybook contracts.
- Feedback and evidence gates before handoff.

The audit is not a replacement for TypeScript, ESLint, Pest, Playwright, or
Storybook tests. It reports convention drift and points agents at the next
repair action.

## Inputs

The audit runner reads these inputs in order:

1. `iak.manifest.v1`, when present.
2. `iak.config.json`, when the manifest is missing or stale.
3. `ds.config.json`, for migrated design-system-kit paths and token roots.
4. Project files under configured page, feature, component, type, route, CSS,
   Storybook, and feedback roots.
5. Optional changed-file input from `iak verify`, `iak plan validate`, or
   `iak handoff validate`.

All persisted paths in JSON output are project-relative POSIX paths. Absolute
paths are allowed only in local debug metadata and must not be required by
agents.

## Severity Model

Each finding has one of three severities:

| Severity | Exit behavior | Meaning |
| --- | --- | --- |
| `error` | `iak audit` exits `1` | The code violates an IAK contract and handoff is blocked. |
| `warning` | `iak audit` exits `0` by default | The code is likely drifting but may need project-specific judgment. |
| `info` | `iak audit` exits `0` | Context or a non-blocking improvement for agents. |

Rules declare a default severity. Config may lower `error` to `warning` only
through an explicit allowlist entry with owner, reason, and optional expiry.
Config may promote warnings to errors in strict mode.

Suppressed findings are not omitted. They are returned with:

```json
{
  "severity": "warning",
  "suppressedBy": {
    "id": "allow_01j",
    "owner": "frontend-platform",
    "reason": "Temporary Ziggy compatibility during Wayfinder migration.",
    "expiresAt": "2026-07-01"
  }
}
```

## Stable Rule IDs

Public rule IDs use this format:

```txt
iak/<category>/<rule-slug>
```

Rules are immutable once published. If behavior changes incompatibly, create a
new rule ID and keep the old ID as an alias until the next major schema family.
Human messages may change; `rule`, `category`, `severity`, and suggestion
shapes must remain compatible.

Categories:

- `page`
- `role`
- `types`
- `format`
- `design-system`
- `stories`
- `feedback`
- `manifest`

## JSON Output Schema

`iak audit --json` emits one object:

```json
{
  "schema": "iak.audit.v1",
  "runId": "run_01j",
  "status": "failed",
  "summary": "Audit failed: 2 design-system errors and 1 missing story.",
  "checks": [
    {
      "id": "iak/design-system/no-raw-hex",
      "category": "design-system",
      "status": "failed",
      "severity": "error",
      "summary": "Raw hex found outside token files.",
      "filesScanned": 48,
      "violations": 2,
      "durationMs": 12
    }
  ],
  "violations": [
    {
      "id": "vio_01j",
      "rule": "iak/design-system/no-raw-hex",
      "category": "design-system",
      "severity": "error",
      "confidence": "high",
      "file": "resources/js/features/vehicles/vehicle-table.tsx",
      "line": 42,
      "column": 18,
      "endLine": 42,
      "endColumn": 25,
      "role": "feature",
      "resource": "vehicles",
      "hit": "#ffffff",
      "message": "Raw hex colors are forbidden outside token files.",
      "suggestion": {
        "kind": "replace",
        "summary": "Use the semantic surface utility.",
        "current": "#ffffff",
        "preferred": "bg-ds-surface",
        "applicability": "manual"
      },
      "autofix": {
        "available": false,
        "safe": false,
        "reason": "The matching semantic token must be chosen from visual intent."
      },
      "docs": ["docs/inertia-agent-kit/design-system-contract.md"],
      "fingerprint": "sha256:..."
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
      "summary": "Replace the raw hex with a semantic design-system utility.",
      "rule": "iak/design-system/no-raw-hex",
      "file": "resources/js/features/vehicles/vehicle-table.tsx",
      "line": 42
    }
  ],
  "errors": [],
  "meta": {
    "adapter": "laravel-inertia-react",
    "manifest": "ds/iak.manifest.v1.json",
    "createdAt": "2026-05-22T15:00:00Z",
    "iakVersion": "0.1.0"
  }
}
```

`status` is:

- `passed` when no unsuppressed `error` findings exist.
- `failed` when one or more unsuppressed `error` findings exist.
- `blocked` when audit cannot run because config, manifest, or project files
  are unavailable.

Every non-zero exit should still write this JSON when possible.

### Violation Fields

Required violation fields:

- `id`: stable within one audit result.
- `rule`: stable rule ID.
- `category`: one of the known categories.
- `severity`: `error`, `warning`, or `info`.
- `message`: concise human-readable description.
- `fingerprint`: deterministic hash of rule, normalized file, location, and
  hit. Used to track repeated findings across runs.

Location fields:

- `file`, `line`, and `column` should be present for source findings.
- `endLine` and `endColumn` should be present when the detector knows the
  range.
- `related` lists secondary files, generated sources, matching components, or
  feedback records.

Context fields:

- `role`: `page`, `feature`, `primitive`, `app-component`, `story`, `type`,
  `route`, `lib`, `layout`, `config`, `feedback`, or `unknown`.
- `resource`: resource folder or Laravel resource name when known.
- `hit`: the offending token, import, symbol, or snippet. Keep this short.
- `confidence`: `high`, `medium`, or `low`.

## Suggestion And Autofix Shape

Findings must distinguish exact autofixes from broader suggestions.

`suggestion` tells an agent what to do:

```json
{
  "kind": "move_file",
  "summary": "Move the resource-specific reusable component into the feature.",
  "current": "resources/js/components/app/vehicle-status-pill.tsx",
  "preferred": "resources/js/features/vehicles/vehicle-status-pill.tsx",
  "applicability": "manual",
  "requires": ["update imports", "add colocated story"]
}
```

Allowed suggestion kinds:

- `replace`
- `add_import`
- `remove_import`
- `move_file`
- `create_file`
- `delete_file`
- `split_component`
- `promote_component`
- `generate_types`
- `run_command`
- `resolve_feedback`
- `manual`

`autofix` is present when IAK can produce an exact edit or safe command:

```json
{
  "available": true,
  "safe": true,
  "kind": "edit",
  "edits": [
    {
      "file": "resources/js/features/vehicles/vehicle.types.ts",
      "range": {
        "startLine": 1,
        "startColumn": 1,
        "endLine": 8,
        "endColumn": 1
      },
      "replacement": "import type { App } from '@/types/generated'\n"
    }
  ]
}
```

Autofix kinds:

- `edit`: exact text replacement.
- `create`: create a missing story, fixture, or config file from a template.
- `move`: move a file and update imports when the import graph is known.
- `command`: run a configured generator such as Wayfinder or Spatie TypeScript
  transformation.

`safe: true` means the edit should not change runtime behavior except to
restore the declared convention. Style token substitutions are usually
suggestions, not safe autofixes, unless the token mapping is explicit in the
manifest.

## Existing `src/audit.mjs` Reuse

The current `src/audit.mjs` should become the first IAK audit checker module
instead of being discarded.

Reusable pieces:

- `SCAN_EXT` for cross-renderer source extensions.
- `SKIP` directory exclusions.
- `stripComments()` to avoid obvious false positives in comments.
- Target root resolution from `readConfig()` and `layerDirs()`.
- Fallback roots `src`, `resources`, and `app`.
- Regex detectors for arbitrary Tailwind values, raw hex colors, and primitive
  Tailwind color utilities.
- `themeCollisions()` for duplicate `@theme` variable names across CSS files.

Required changes:

- Return structured check and violation objects instead of writing only to
  stderr.
- Normalize all paths to project-relative POSIX paths.
- Calculate `column`, `endColumn`, and short `hit` values from match indexes.
- Map legacy rule names to stable IAK IDs:

| Existing rule | Stable IAK rule |
| --- | --- |
| `arbitrary-value` | `iak/design-system/no-arbitrary-value` |
| `raw-hex-color` | `iak/design-system/no-raw-hex` |
| `primitive-color` | `iak/design-system/no-primitive-color` |
| `theme-collision` | `iak/design-system/no-theme-collision` |

- Add `--json` support while preserving terse human output for non-agent use.
- Support `IAK_AGENT=1` as equivalent to `--json`.
- Emit exit code `1` for actionable findings and `2` for config/schema errors,
  matching the JSON handoff contract.

The first implementation exposes these checks through `iak audit --json`.
Legacy `ds audit` behavior is historical source material, not the public IAK
command surface.

## Check Catalog

### Manifest Checks

| Rule | Default | Description |
| --- | --- | --- |
| `iak/manifest/schema-valid` | error | Manifest and config validate against the published schemas. |
| `iak/manifest/path-exists` | error | Required page, feature, component, generated type, token, and feedback roots exist or are intentionally disabled. |
| `iak/manifest/stale` | warning | Manifest inputs changed after the generated manifest artifact. |
| `iak/manifest/role-graph-valid` | error | Role graph has known roles, known resources, and no impossible import direction. |

### Page Checks

Pages are route adapters. They receive Inertia props, choose layout, and compose
feature/app components.

| Rule | Default | Description | Suggested repair |
| --- | --- | --- | --- |
| `iak/page/max-lines` | warning | Page exceeds configured line budget, default 200 lines. | Move workflow UI into `features/<resource>/*`. |
| `iak/page/no-inline-domain-types` | error | Page declares backend/domain types inline. | Import generated `*PageData` or feature alias. |
| `iak/page/no-any-props` | error | Page props use `any`, `unknown`, or broad records. | Use generated page prop type. |
| `iak/page/no-inline-table` | error | Page implements table/list/grid component directly. | Extract resource table to feature component. |
| `iak/page/no-inline-form` | error | Page implements resource form directly. | Extract form to feature component using generated form data. |
| `iak/page/no-local-hooks` | warning | Page defines hooks/composables beyond route glue. | Move orchestration to feature-local hook only when reused or complex. |
| `iak/page/no-inline-layout` | error | Page defines layout shell inline. | Use `layouts/*` and select layout from page. |
| `iak/page/no-reusable-component-definition` | error | Page exports or defines reusable components. | Move reusable UI to feature, app component, or primitive role. |

### Role And Import Checks

| Rule | Default | Description | Suggested repair |
| --- | --- | --- | --- |
| `iak/role/import-boundary` | error | Imports violate configured role graph. | Import downward or move the dependency to the correct role. |
| `iak/role/no-feature-to-page-import` | error | Feature imports a page module or page-owned type. | Import generated types or feature-owned types. |
| `iak/role/no-domain-in-primitive` | error | `components/ui/*` contains resource, route, backend, or domain concepts. | Move domain UI to feature or app component. |
| `iak/role/no-resource-in-app-component` | error | `components/app/*` has resource-specific naming or assumptions. | Move to `features/<resource>/*` or make the component generic. |
| `iak/role/no-side-effect-lib` | error | `lib/*` contains components, Inertia router calls, or side effects. | Move behavior to feature or app service. |
| `iak/role/no-resource-type-in-shared` | error | `types/shared/*` contains resource-specific names. | Move type to feature or generated source. |
| `iak/role/no-top-level-behavior-folder` | warning | New top-level `queries`, `actions`, `forms`, `hooks`, or `composables` appears without config. | Keep behavior local to features or generated route/action output. |
| `iak/role/no-duplicate-component-name` | warning | Same component name exists in multiple roles. | Reuse, promote, or rename intentionally. |
| `iak/role/no-near-duplicate-component` | warning | Component structure is highly similar to another component. | Reuse existing component or extract shared app component. |

Near-duplicate detection starts as a warning because AST similarity can be
noisy. A first implementation can hash normalized JSX/SFC trees, ignore text
nodes and import order, and report candidates above a configured threshold.

### Type Checks

These rules enforce the generated type strategy from Spatie Laravel Data,
Wayfinder, and generated route/action outputs.

| Rule | Default | Description |
| --- | --- | --- |
| `iak/types/no-inline-page-props` | error | Page files declare backend page prop interfaces inline. |
| `iak/types/no-handwritten-data-copy` | error | Frontend types duplicate generated Data classes, form payloads, filters, models, resources, or enum unions. |
| `iak/types/no-local-domain-enum` | error | Resource/domain enum unions or label maps are declared in frontend-owned files. |
| `iak/types/no-any-page-props` | error | Inertia page props use `any`, `unknown`, or broad records instead of generated types. |
| `iak/types/no-generated-edits` | error | Generated files were manually modified. |
| `iak/types/no-stale-generated-output` | error | PHP Data, enum, route, or controller source changed without regenerated TypeScript or Wayfinder output. |
| `iak/types/no-feature-to-page-type-import` | error | Feature code imports types from page files. |
| `iak/types/no-resource-type-in-shared` | error | Shared frontend-only types contain resource-specific backend names. |

Suggested output should name the generated type, Data class, enum, route, or
DTO that should be used when the detector knows it.

### Formatting And Translation Checks

Laravel owns user-facing data formatting and translation. The frontend renders
display-ready values and handles interaction.

| Rule | Default | Description |
| --- | --- | --- |
| `iak/format/no-intl-render-formatting` | error | `Intl.NumberFormat` or `Intl.DateTimeFormat` appears in page, feature, or app component render paths without an allowlist. |
| `iak/format/no-date-library-render-formatting` | error | `date-fns`, `dayjs`, `luxon`, or equivalent formats production user-facing text in render paths. |
| `iak/format/no-local-label-map` | error | Frontend defines enum label maps or derives labels from raw enum values. |
| `iak/format/no-hardcoded-validation-message` | error | Frontend defines validation messages that belong to Laravel. |
| `iak/format/no-frontend-translation-dictionary` | error | Frontend i18n dictionaries are used when backend-owned translation mode is enabled. |
| `iak/format/no-route-string-construction` | error | Laravel route URLs, route names, controller strings, or HTTP method contracts are recreated by hand instead of Wayfinder output. |
| `iak/format/no-copy-key-construction` | warning | Frontend constructs translation or copy keys from backend values. |

Allowed production exceptions require an allowlist entry. Stories and tests may
use fixture copy, but fixture files should still use typed data shapes.

### Design-System Checks

These keep and extend the current design-system-kit audit behavior.

| Rule | Default | Description | Existing reuse |
| --- | --- | --- | --- |
| `iak/design-system/no-arbitrary-value` | error | Tailwind arbitrary values such as `p-[34px]` appear outside allowed token or config files. | Current `ARBITRARY` regex. |
| `iak/design-system/no-raw-hex` | error | Raw hex colors appear outside primitive token files. | Current `RAW_HEX` regex. |
| `iak/design-system/no-primitive-color` | error | Primitive Tailwind color utilities such as `bg-blue-500`, `text-slate-700`, `white`, or `black` appear in app code. | Current `PRIMITIVE_COLOR` regex. |
| `iak/design-system/no-primitive-css-var` | error | Component/page/feature code references primitive CSS variables directly, such as `var(--neutral-500)`. | New regex/parser check. |
| `iak/design-system/no-theme-collision` | error | Same `@theme` variable name is declared in more than one CSS file. | Current `themeCollisions()`. |
| `iak/design-system/bridge-prefix-required` | error | Tailwind bridge variables do not use category-prefixed `ds` names such as `--color-ds-*`. | Extend CSS scan. |
| `iak/design-system/semantic-token-bridge-missing` | warning | Semantic token is not exposed through the Tailwind bridge when it should produce utilities. | New manifest/token cross-check. |
| `iak/design-system/import-order` | warning | CSS imports do not follow Tailwind, tokens, themes, bridge order. | New CSS entry check. |
| `iak/design-system/no-inline-style-value` | error | Inline style contains raw color, spacing, radius, or shadow values in production UI. | New AST/regex check. |

Token file allowances:

- Raw values are allowed in primitive token files.
- Semantic remaps are allowed in theme files.
- Bridge files may reference semantic tokens and declare Tailwind `@theme`
  variables.
- Component, page, feature, story, and layout files consume semantic utilities.

### Story Checks

Stories are executable contracts for reusable UI.

| Rule | Default | Description |
| --- | --- | --- |
| `iak/stories/required-ui` | error | Every `components/ui/*` primitive has a colocated story or configured equivalent contract. |
| `iak/stories/required-app` | error | Every `components/app/*` reusable component has a colocated story. |
| `iak/stories/required-feature` | warning | Feature component that is reusable, stateful, visually important, exported, or configured as agent-edited lacks a story. |
| `iak/stories/required-states` | warning | Required states such as `Default`, `Empty`, `Loading`, `Error`, `Disabled`, or `WithValidationErrors` are missing. |
| `iak/stories/variants-covered` | warning | Public variants or sizes are not represented by stories. |
| `iak/stories/fixtures-use-types` | error | Stories or fixtures invent backend-derived data shapes instead of importing generated, shared, or feature-owned types. |
| `iak/stories/story-id-valid` | error | Story ID referenced by manifest, feedback, verify, or handoff does not resolve. |

Projects may promote `iak/stories/required-feature` and
`iak/stories/required-states` to errors once the team has chosen strict story
coverage.

### Feedback Checks

Feedback records block handoff until resolved with evidence.

| Rule | Default | Description |
| --- | --- | --- |
| `iak/feedback/no-unresolved-feedback` | error | Pending or in-progress feedback exists for changed resources, routes, stories, or selectors. |
| `iak/feedback/schema-valid` | error | Feedback record does not validate against `iak.feedback.v1`. |
| `iak/feedback/resolution-evidence-required` | error | Resolved, duplicate, or wont-fix feedback lacks required evidence or reason. |
| `iak/feedback/artifact-reference-valid` | error | Feedback or resolution references missing artifacts or paths outside `.iak/feedback/*` or `.iak/runs/*`. |
| `iak/feedback/stale-context` | warning | Feedback was created against an older git SHA, route, or story and may need revalidation. |

The audit should support a focused mode:

```txt
iak audit --json --changed-files .iak/runs/run_01j/changed-files.json
```

In focused mode, feedback checks only block on records related to changed
routes, stories, resources, selectors, or component candidates. Full audit mode
may also report unrelated pending feedback as warnings.

## Check Status Model

Each `checks[]` item has:

```json
{
  "id": "iak/page/no-inline-form",
  "category": "page",
  "status": "passed",
  "severity": "error",
  "summary": "No page-owned resource forms found.",
  "filesScanned": 12,
  "violations": 0,
  "durationMs": 8
}
```

Allowed check statuses:

- `passed`
- `failed`
- `warning`
- `skipped`
- `blocked`

`skipped` requires `reason`, for example Storybook checks when Storybook is
not installed and no story requirement is configured. `blocked` is for missing
config, unreadable files, invalid JSON, or parser errors that prevent a rule
from producing reliable findings.

## Agent Recovery Rules

The audit should make common repairs obvious:

- Page responsibility findings should name the target role and likely target
  path.
- Import boundary findings should include the importing role, imported role,
  and allowed dependency direction.
- Type findings should point to generated import paths or generator commands.
- Formatting findings should name the backend-owned DTO/copy pattern when
  detectable.
- Design-system findings should suggest a semantic token utility or recommend
  adding a token contract change.
- Story findings should include the expected story path and required states.
- Feedback findings should include feedback IDs and evidence commands.

Every violation should create one `nextActions[]` entry unless another entry
already covers the same file and rule.

## Implementation Order

1. **Schema and runner shell.** Publish `iak.audit.v1` JSON Schema, add
   `--json`, `--pretty`, and `IAK_AGENT=1`, and return structured output with
   exit codes from the JSON handoff contract.
2. **Migrate existing style audit.** Refactor `src/audit.mjs` into a checker
   registry and map current rules to `iak/design-system/*` IDs.
3. **Path and role classifier.** Build role/resource classification from
   `iak.manifest.v1`, `iak.config.json`, and migrated `ds.config.json` paths.
4. **Design-system extensions.** Add primitive CSS variable, bridge prefix,
   semantic bridge coverage, and CSS import order checks.
5. **Story presence checks.** Detect required `components/ui/*`,
   `components/app/*`, and configured feature stories. Validate required states
   once Storybook metadata or CSF parsing is available.
6. **Page and role checks.** Add page line budget, inline table/form/component
   heuristics, local hook detection, and import-boundary validation.
7. **Type checks.** Detect inline page props, handwritten Data copies, local
   domain enums, generated edits, stale generated output, and shared type drift.
8. **Formatting checks.** Detect frontend formatting, label maps, validation
   messages, route string construction, and translation dictionary drift.
9. **Feedback checks.** Validate `.iak/feedback/*`, block unresolved related
   feedback, and validate resolution evidence.
10. **Duplicate component checks.** Add duplicate names first, then normalized
    JSX/SFC similarity as warnings.
11. **Autofix engine.** Start with safe file creation for missing stories and
    exact generated import replacements. Keep semantic styling repairs as
    suggestions until token intent is unambiguous.
12. **Verify integration.** Make `iak verify --json` consume audit artifacts,
    include audit status in `iak.verify.v1`, and require passing audit for
    completed handoffs.

## Test Fixtures

The implementation should include small fixtures for each category:

- Passing Laravel + Inertia + React resource with page, feature, generated
  type alias, story, and token usage.
- Page with inline table/form and inline prop types.
- Feature importing a page type.
- Shared type file containing resource-specific names.
- Generated file with manual edit marker or stale source hash.
- Component with raw hex, arbitrary value, primitive color, primitive CSS var,
  and inline style value.
- Duplicate `@theme` variable and unprefixed bridge variable.
- Missing primitive/app story and missing story state.
- Story fixture that handwrites backend shape.
- Pending feedback record and invalid resolution evidence.

Each fixture should assert both exit code and JSON shape, not only terminal
text.
