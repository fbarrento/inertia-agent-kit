# Brand OS Integration Contract

Status: draft spec  
Date: 2026-05-22  
Owned surface: IAK's Brand OS artifact intake, lock file, token adaptation,
manifest slice, audit rules, verification evidence, and agent command contract.

## Purpose

Brand OS stays upstream of Inertia Agent Kit. The products must not merge.

```txt
Brand OS = creates the brand contract
IAK = consumes, applies, audits, and verifies that contract in a Laravel/Inertia app
```

Brand OS defines the brand. IAK makes that brand enforceable inside an
application without asking agents to read the full Brand OS project or copy
brand rationale into frontend code.

## Product Boundary

Brand OS owns:

- strategy, positioning, audience, and offering rules;
- name, verbal identity, voice, tone, and usage guidance;
- logo, marks, brand imagery, and approved asset files;
- color, type, spacing, radius, motion, and visual rationale;
- neutral brand tokens and theme definitions;
- source markdown guidelines that explain the brand contract for humans.

IAK owns:

- Laravel/Inertia app conventions and scaffolding;
- frontend role graph, page/resource layout, component/story placement;
- design-system token enforcement inside the app;
- adaptation from Brand OS tokens to IAK `ds-` utilities;
- generated manifest slices for agents;
- Pest, Playwright, Storybook, audit, verify, and HITL feedback evidence;
- JSON-first handoffs and command output for agent workflows.

IAK must not edit the upstream Brand OS project. It may copy, link, cache, and
adapt consumable artifacts into the Laravel/Inertia app.

## Expected Brand OS Artifacts

The required integration artifact is a machine-readable manifest:

```txt
brand.json
```

IAK consumes structured artifacts by default. Markdown files are optional human
references and must not be parsed as the primary integration API.

Minimum v1 Brand OS export:

```txt
brand.json
brand/design-system/tokens.json
brand/design-system/theme.css
brand/assets/*.svg
brand/00-brief.md
brand/01-strategy.md
brand/02-verbal-identity.md
brand/03-visual-identity.md
brand/04-guidelines.md
```

`brand.json` should include stable ids, artifact paths, versions, and concise
agent-safe summaries:

```json
{
  "schema": "brand-os.manifest.v1",
  "id": "acme-coffee",
  "name": "Acme Coffee",
  "version": "2026-05-22",
  "summary": "Quiet confidence for independent cafes.",
  "voice": ["warm", "precise", "plainspoken"],
  "avoid": ["generic startup language", "luxury cliches"],
  "artifacts": {
    "tokens": "brand/design-system/tokens.json",
    "theme": "brand/design-system/theme.css",
    "assets": "brand/assets",
    "verbalIdentity": "brand/02-verbal-identity.md",
    "visualIdentity": "brand/03-visual-identity.md"
  }
}
```

Brand OS may include richer fields, but IAK only depends on the stable manifest
shape, artifact paths, and token/theme/assets required by this contract.

## IAK Commands

Brand commands must support `--json` and obey the JSON handoff contract.
`IAK_AGENT=1` makes JSON output the default and disables interactive prompts.

### `iak brand connect`

```bash
iak brand connect ../acme-brand --json
iak brand connect ../acme-brand/brand.json --mode copy --json
```

Responsibilities:

- locate and validate `brand.json`;
- validate required artifacts and schema versions;
- copy or link consumable artifacts into the app according to config;
- adapt Brand OS tokens into the configured IAK design-system token files;
- copy approved assets into the configured brand asset path;
- write `.iak/brand.lock.json`;
- refresh `iak.manifest.v1` or report that refresh is required.

`connect` mutates the consumer Laravel/Inertia app. It must not mutate the
Brand OS source project.

### `iak brand sync`

```bash
iak brand sync --json
iak brand sync --check --json
```

Responsibilities:

- read `.iak/brand.lock.json`;
- compare recorded source version, commit, artifact hashes, and generated
  outputs with the current Brand OS source;
- refresh copied or linked artifacts when not running `--check`;
- regenerate adapted token CSS and manifest brand slice;
- report whether the app is `current`, `stale`, `changed`, or `failed`.

`--check` is CI-safe and must not write files. It exits non-zero when the app
is out of sync.

### `iak brand audit`

```bash
iak brand audit --json
```

Responsibilities:

- validate `.iak/brand.lock.json` against `iak.brand-lock.v1`;
- validate copied or linked artifact paths and hashes;
- validate adapted token files and the Tailwind bridge;
- validate that app code uses `ds-` semantic utilities instead of raw brand
  values;
- validate that Storybook and feedback records can reference the active brand;
- report actionable violations with stable rule ids.

`iak audit --json` includes these brand checks. `iak brand audit --json` is the
focused command for brand-only diagnostics.

## `brand.lock` Shape

IAK writes the lock file at:

```txt
.iak/brand.lock.json
```

The lock file is source-controlled when the app wants deterministic brand state
in CI. It records the upstream source, copied or linked artifacts, generated
outputs, hashes, and sync status.

```json
{
  "schema": "iak.brand-lock.v1",
  "status": "current",
  "connectedAt": "2026-05-22T15:00:00Z",
  "syncedAt": "2026-05-22T15:00:00Z",
  "source": {
    "type": "local-path",
    "manifest": "../acme-brand/brand.json",
    "root": "../acme-brand",
    "version": "2026-05-22",
    "commit": "abc123"
  },
  "brand": {
    "id": "acme-coffee",
    "name": "Acme Coffee",
    "version": "2026-05-22"
  },
  "mode": "copy",
  "artifacts": {
    "cached": {
      "manifest": ".iak/brand/brand.json",
      "tokens": ".iak/brand/tokens.json",
      "theme": ".iak/brand/theme.css"
    },
    "generated": {
      "tokens": "resources/css/ds/tokens.css",
      "themes": "resources/css/ds/themes.css",
      "bridge": "resources/css/ds/bridge.css"
    },
    "assets": {
      "logo": "resources/assets/brand/logo.svg"
    }
  },
  "hashes": {
    "sourceManifest": "sha256:...",
    "sourceTokens": "sha256:...",
    "sourceTheme": "sha256:...",
    "generatedTokens": "sha256:...",
    "generatedThemes": "sha256:...",
    "generatedBridge": "sha256:..."
  },
  "adapter": {
    "id": "brand-os-to-iak-ds",
    "version": "0.1.0",
    "tokenFormat": "dtcg"
  }
}
```

Rules:

- all persisted paths are project-relative except `source.root` and
  `source.manifest`, which may be relative to the app root;
- `mode` is `copy` or `link`;
- generated files are owned by IAK after adaptation;
- source hashes prove whether Brand OS changed;
- generated hashes prove whether the app token layer is stale;
- unknown lock schemas are audit failures.

## Token Adaptation Into `ds` Utilities

Brand OS should export neutral tokens, preferably DTCG-style JSON. IAK adapts
those tokens into the existing three-tier IAK design-system model.

Input:

```txt
brand/design-system/tokens.json
brand/design-system/theme.css
```

Generated or updated app files:

```txt
resources/css/ds/tokens.css
resources/css/ds/themes.css
resources/css/ds/bridge.css
```

Adaptation rules:

- Brand OS raw values become IAK primitive tokens in `tokens.css`.
- Brand OS theme intent becomes semantic `--ds-*` tokens in `themes.css`.
- IAK generates Tailwind v4 `@theme inline` bridge variables with category
  prefixes such as `--color-ds-*`, `--spacing-ds-*`, and `--radius-ds-*`.
- App code consumes utilities such as `bg-ds-surface`, `text-ds-muted`,
  `p-ds-inset-md`, and `rounded-ds-control`.
- App code never imports Brand OS token JSON directly.
- App code never hard-codes brand colors, font stacks, radii, shadows, or raw
  DTCG token paths.
- If Brand OS adds a new token that has no semantic IAK use, IAK may keep it in
  cached artifacts without exposing it through `ds-` utilities.

The brand changes values behind stable `ds-` utilities. Components and pages do
not change class names when the active brand changes.

## Config And Manifest Contract

The consumer app config should declare the brand integration surface without
inlining the full Brand OS manifest.

Example `iak.config.json` slice:

```json
{
  "brand": {
    "enabled": true,
    "lockFile": ".iak/brand.lock.json",
    "artifactRoot": ".iak/brand",
    "mode": "copy",
    "tokenOutputs": {
      "primitives": "resources/css/ds/tokens.css",
      "semantics": "resources/css/ds/themes.css",
      "bridge": "resources/css/ds/bridge.css"
    },
    "assetOutput": "resources/assets/brand",
    "translationMode": "backend-owned"
  }
}
```

The generated `iak.manifest.v1` must expose a compact brand slice for agents:

```json
{
  "brand": {
    "status": "current",
    "lockFile": ".iak/brand.lock.json",
    "name": "Acme Coffee",
    "essence": "Quiet confidence for independent cafes.",
    "voice": ["warm", "precise", "plainspoken"],
    "avoid": ["generic startup language", "luxury cliches"],
    "artifacts": {
      "manifest": ".iak/brand/brand.json",
      "tokens": ".iak/brand/tokens.json",
      "theme": ".iak/brand/theme.css"
    },
    "generated": {
      "tokens": "resources/css/ds/tokens.css",
      "themes": "resources/css/ds/themes.css",
      "bridge": "resources/css/ds/bridge.css"
    },
    "assets": {
      "logo": "resources/assets/brand/logo.svg"
    },
    "commands": {
      "sync": "iak brand sync --json",
      "audit": "iak brand audit --json"
    }
  }
}
```

Manifest rules:

- expose summaries, paths, ids, status, and command names;
- do not inline full Brand OS prose, token JSON, CSS, SVG contents, or
  generated files;
- reference large or human-facing artifacts by path;
- keep Brand OS markdown as optional references for humans and HITL feedback;
- make stale or missing brand state visible to agents before source edits.

## Translation Boundary

Brand OS informs voice and messaging. Laravel still owns production copy,
translation, and locale-sensitive formatting.

Flow:

```txt
Brand OS verbal rules
  -> Laravel lang files and backend Data copy DTOs
  -> Inertia page props
  -> frontend renders display-ready strings
```

Rules:

- frontend TSX must not import Brand OS copy directly;
- frontend TSX must not construct translation keys from Brand OS terms;
- production page copy belongs in Laravel lang files and backend Data objects;
- DTOs send display-ready strings for user-facing copy and formatted values;
- generated Spatie Data TypeScript types describe copy and display fields that
  agents should import instead of inventing local brand-copy shapes;
- stories may use fixture copy, but production components receive copy through
  props;
- Brand OS voice rules may appear in manifest summaries and feedback
  references, not as frontend runtime dictionaries.

This boundary preserves backend-owned formatting and translation while still
letting Brand OS guide tone.

## Storybook And Feedback References

Storybook must render with the active Brand OS adapted tokens and approved
assets. Brand state is part of visual verification.

Storybook requirements:

- load the same generated `resources/css/ds/*` token layer as the app;
- expose active brand name and status through the IAK manifest or Storybook
  metadata;
- render stories with brand assets by importing app asset paths, not upstream
  Brand OS paths;
- fail or warn when Brand OS is stale according to `brand.lock` and project
  policy.

Feedback records may reference brand guidance by path and concise rule, without
embedding the full guideline document:

```json
{
  "surface": "storybook",
  "storyId": "components-app-empty-state--default",
  "message": "This feels too playful for the brand voice.",
  "brandReference": {
    "brand": "Acme Coffee",
    "source": "brand/02-verbal-identity.md",
    "rule": "plainspoken, not whimsical"
  }
}
```

Feedback rules:

- brand references are evidence links, not executable frontend copy;
- unresolved brand-related feedback blocks handoff like other HITL feedback
  when the project requires feedback resolution;
- feedback artifacts should include the manifest brand status and story or app
  URL that was inspected.

## Audit And Verify Implications

`iak audit --json` must include Brand OS integration checks when brand support
is enabled.

Required brand audit failures:

- `iak/brand/no-lock`: brand is enabled but `.iak/brand.lock.json` is missing;
- `iak/brand/invalid-lock`: the lock file does not match
  `iak.brand-lock.v1`;
- `iak/brand/source-missing`: the locked Brand OS source manifest cannot be
  resolved;
- `iak/brand/source-stale`: source hashes or commit differ from the lock;
- `iak/brand/generated-stale`: generated token outputs differ from the lock;
- `iak/brand/missing-artifact`: required manifest, token, theme, or asset
  artifact is missing;
- `iak/brand/invalid-token-adapter`: Brand OS tokens cannot be adapted into
  the configured IAK token tiers;
- `iak/brand/no-direct-token-import`: app code imports Brand OS token JSON or
  source CSS directly;
- `iak/brand/no-raw-brand-value`: app code hard-codes brand values instead of
  using `ds-` utilities;
- `iak/brand/no-frontend-brand-copy`: production frontend code imports Brand
  OS markdown or owns Brand OS-derived copy directly.

`iak verify --json` must report brand evidence:

```json
{
  "brand": {
    "status": "current",
    "lock": ".iak/brand.lock.json",
    "audit": {
      "status": "passed",
      "artifact": ".iak/runs/run_01j/brand-audit.json"
    },
    "storybook": {
      "status": "passed",
      "activeBrand": "Acme Coffee"
    }
  }
}
```

Verification rules:

- brand status is required in `iak.verify.v1` when brand support is enabled;
- stale brand sync is a failure in CI by default;
- missing Storybook brand evidence is at least a warning for visual changes;
- final `iak.handoff.v1` should include brand status under evidence for
  browser-visible or brand-sensitive work.

## JSON Handoff Contract

Brand integration evidence belongs in JSON artifacts. Human final responses may
summarize the result, but the persisted `iak.handoff.v1` remains the source of
truth.

Example handoff evidence slice:

```json
{
  "evidence": {
    "brand": {
      "status": "current",
      "brand": "Acme Coffee",
      "lock": ".iak/brand.lock.json",
      "audit": {
        "status": "passed",
        "artifact": ".iak/runs/run_01j/brand-audit.json"
      },
      "generated": {
        "tokens": "resources/css/ds/tokens.css",
        "themes": "resources/css/ds/themes.css",
        "bridge": "resources/css/ds/bridge.css"
      },
      "feedback": {
        "unresolvedBrandItems": 0
      }
    }
  }
}
```

Rules:

- include brand evidence when brand support is enabled and the task changes
  UI, copy, tokens, assets, Storybook, or verification;
- reference artifact paths instead of embedding Brand OS markdown, token JSON,
  CSS, SVG contents, screenshots, DOM dumps, or logs;
- use `status: "stale"` or `status: "failed"` when sync or audit did not pass;
- require unresolved brand feedback counts before handoff validation passes.

## First Integration Milestone

The first Brand OS + IAK milestone should prove the integration contract
without building every future brand-management feature.

Acceptance:

- Brand OS exports `brand.json`, `brand/design-system/tokens.json`,
  `brand/design-system/theme.css`, and at least one SVG asset.
- `iak brand connect ../brand-os-project --json` validates the Brand OS
  manifest and writes `.iak/brand.lock.json`.
- IAK copies or links brand artifacts into `.iak/brand/*` and
  `resources/assets/brand/*`.
- IAK adapts Brand OS tokens into `resources/css/ds/tokens.css`,
  `resources/css/ds/themes.css`, and `resources/css/ds/bridge.css`.
- `iak.manifest.v1` exposes the compact `brand` slice with status, summaries,
  artifact paths, generated token paths, and command references.
- A scaffolded primitive or app component story renders using the adapted
  brand tokens and at least one approved brand asset.
- `iak brand audit --json` catches a deliberate stale lock, missing artifact,
  raw brand value, and direct Brand OS token import.
- `iak audit --json` includes brand findings beside style, type, story, and
  formatting findings.
- `iak verify --json` includes brand sync status and brand audit evidence in
  the run output.
- `iak.handoff.v1` can reference brand evidence without embedding Brand OS
  markdown, token JSON, CSS, SVG contents, screenshots, or logs.

## Open Questions

- Whether Brand OS should publish JSON Schema for `brand-os.manifest.v1` in the
  same package as IAK schemas or in its own upstream package.
- Whether `link` mode is allowed in CI or only in local development.
- Exact token adapter behavior for multi-brand apps that need more than one
  active brand in the same Laravel/Inertia codebase.
- Whether brand-stale Storybook state is always a failure or configurable by
  project policy.
