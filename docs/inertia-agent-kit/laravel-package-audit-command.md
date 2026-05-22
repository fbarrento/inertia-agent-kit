# Laravel Package Audit Command

Status: planning spec for the Laravel package port
Owner: Inertia Agent Kit
Package target: `fbarrento/inertia-agent-kit`
Public command: `php artisan iak:audit --json`

## Purpose

`iak:audit` is the Laravel package implementation of IAK's frontend/Inertia
convention audit. It is not a general Laravel diagnostics command and must not
duplicate Laravel Boost's generic app, route, log, database, docs, or framework
health checks.

The first Laravel port audits only:

- arbitrary Tailwind values;
- raw hex colors outside token files;
- primitive Tailwind color utilities;
- forbidden global behavior folders;
- missing required story files;
- feature type files that do not import generated backend contracts.

## Command Contract

Signature:

```txt
php artisan iak:audit
  {--json : Emit one machine-readable JSON object}
  {--pretty : Pretty-print JSON when --json or IAK_AGENT=1 is active}
  {--run-id= : Optional run id for deterministic tests}
  {--config= : Optional config path, default config/inertia-agent-kit.php}
```

`IAK_AGENT=1` is equivalent to `--json`. In JSON mode stdout contains only the
final JSON object. Human diagnostics may go to stderr but must stay concise.

Exit codes:

| Code | Meaning |
| --- | --- |
| `0` | Audit ran and no unsuppressed `error` findings exist. |
| `1` | Audit ran and one or more unsuppressed `error` findings exist. |
| `2` | Usage, config, manifest, or schema error. |
| `3` | Environment error, for example unreadable project paths. |
| `4` | Unexpected internal error. |

The JSON event is `iak.audit.completed`; the schema/version is
`iak.audit.v1`. Non-zero exits should still write JSON and the run artifact
whenever the command can build a result.

## Run Artifacts

Each run writes:

```txt
.iak/runs/<run-id>/audit.json
```

`<run-id>` should be `run_<ulid>` in normal use. Tests may pass `--run-id`.
Persisted paths are project-relative POSIX paths. The artifact content must
match stdout in JSON mode, except stdout may be pretty-printed.

Minimal shape:

```json
{
  "schema": "iak.audit.v1",
  "event": "iak.audit.completed",
  "version": 1,
  "command": "iak:audit",
  "runId": "run_01j",
  "status": "failed",
  "summary": "Audit failed: 1 error.",
  "totals": {
    "checks": 8,
    "passed": 7,
    "failed": 1,
    "blocked": 0,
    "findings": 1,
    "errors": 1,
    "warnings": 0
  },
  "checks": [
    {
      "id": "iak/design-system/no-raw-hex",
      "category": "design-system",
      "status": "failed",
      "severity": "error",
      "summary": "Raw hex colors found outside token files.",
      "filesScanned": 18,
      "findings": 1,
      "durationMs": 8
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
      "line": 17,
      "column": 29,
      "endLine": 17,
      "endColumn": 36,
      "role": "feature",
      "resource": "vehicles",
      "hit": "#ffffff",
      "message": "Raw hex colors are allowed only in configured primitive token files.",
      "suggestion": {
        "kind": "replace",
        "summary": "Use a semantic design-system utility or add a token mapping.",
        "current": "#ffffff",
        "preferred": "bg-ds-surface",
        "applicability": "manual"
      },
      "docs": ["docs/inertia-agent-kit/design-system-contract.md"],
      "fingerprint": "sha256:rule-file-location-hit"
    }
  ],
  "artifacts": {
    "audit": {
      "kind": "json",
      "path": ".iak/runs/run_01j/audit.json",
      "schema": "iak.audit.v1"
    }
  },
  "nextActions": [
    {
      "type": "fix",
      "summary": "Replace the raw hex with a semantic token utility.",
      "rule": "iak/design-system/no-raw-hex",
      "file": "resources/js/features/vehicles/vehicle-table.tsx",
      "line": 17
    }
  ],
  "errors": [],
  "meta": {
    "createdAt": "2026-05-22T15:00:00Z",
    "package": "fbarrento/inertia-agent-kit",
    "iakVersion": "0.1.0",
    "adapter": "laravel-inertia-react",
    "configHash": "sha256:..."
  }
}
```

`status` is `passed`, `failed`, or `blocked`. Machine-readable findings live
in `violations[]`; implementation code may name the internal class `Finding`,
but the public JSON key stays aligned with `iak.audit.v1`.

## Initial Check Catalog

| Rule ID | Severity | Inputs scanned | Finding trigger | Suggested remediation |
| --- | --- | --- | --- | --- |
| `iak/design-system/no-arbitrary-value` | error | Source, component, page, feature, story, and CSS files under configured frontend roots. | Tailwind arbitrary utilities such as `p-[34px]`, `bg-[#fff]`, or `rounded-[11px]` outside allowed config/token files. | Use an existing semantic `ds-` utility or propose a token contract change. |
| `iak/design-system/no-raw-hex` | error | Same frontend roots plus CSS, excluding configured primitive token files. | Raw `#rgb`, `#rgba`, `#rrggbb`, or `#rrggbbaa` values outside primitive token files. | Replace with semantic utilities or add primitive plus semantic token mapping. |
| `iak/design-system/no-primitive-color` | error | Class-bearing source files and stories. | Primitive Tailwind color utilities such as `bg-blue-500`, `text-slate-700`, `border-neutral-200`, `from-zinc-900`, `white`, or `black`. | Use semantic color utilities such as `bg-ds-surface`, `text-ds-muted`, or `border-ds-border-default`. |
| `iak/role/no-top-level-behavior-folder` | error | Direct children of the configured JS root, default `resources/js`. | Non-generated top-level `queries`, `actions`, `forms`, `hooks`, or `composables` folders. | Move behavior into `resources/js/features/<resource>` or a configured generated route/action root. |
| `iak/stories/required-ui` | error | `resources/js/components/ui/*` component files. | Component lacks a colocated `*.stories.*` file. | Create the colocated story with default and relevant variant states. |
| `iak/stories/required-app` | error | `resources/js/components/app/*` component files. | Reusable app component lacks a colocated `*.stories.*` file. | Create the colocated story using typed fixtures where needed. |
| `iak/stories/required-feature` | error | Feature files declared story-required by scaffold metadata, or first-port conventions for `*-table.*` and `*-form.*`. | Required feature component lacks a colocated `*.stories.*` file. | Create the feature story and use feature fixtures. |
| `iak/types/generated-contract-import-required` | error | `resources/js/features/<resource>/*.types.ts`. | Feature type file does not import from the configured generated backend contract path or alias. | Import generated contracts, usually `import type { App } from '@/types/generated'`, and compose aliases instead of copying DTOs. |

The check count in JSON may be eight because story scopes are separate rules.
The implementation may group scanners internally, but public rule IDs must stay
stable.

## Scanner Boundaries

Inputs come from the package config or generated manifest when available, with
these defaults:

```txt
resources/js
resources/css
resources/views
resources/js/types/generated
resources/js/actions/generated
resources/js/routes/generated
```

Skip:

```txt
vendor
node_modules
public/build
storage
bootstrap/cache
.git
.iak/runs
.iak/feedback
```

Do not scan PHP for generic Laravel quality issues. The audit may read config,
manifest, scaffold metadata, and generated-contract locations only to evaluate
IAK frontend conventions.

Implementation boundaries:

- Normalize every path before matching and before JSON output.
- Use configured roots and aliases before falling back to defaults.
- Treat generated files as inputs for existence/import checks, not styling
  violations.
- Keep scanners deterministic; ordering is by normalized path, then line,
  column, rule.
- Each violation fingerprint hashes rule, normalized file, location, and hit.
- Do not attempt autofixes in the first port. Suggestions are manual.

## False-Positive Controls

- Strip comments before regex scans for style findings.
- Detect Tailwind utilities as class tokens, not arbitrary prose substrings.
- Use token boundaries so `text-slate-700ish` does not match
  `text-slate-700`.
- Allow raw values only in configured primitive token files, for example
  `resources/css/tokens.css`.
- Do not flag semantic theme files for `var(--ds-*)` references; the first port
  only checks raw hex and primitive Tailwind classes.
- Do not flag `resources/js/actions/generated/**` or configured Wayfinder
  output as a forbidden global behavior folder.
- Missing-story checks ignore barrels, fixtures, tests, generated files, and
  existing story files.
- Feature type import checks only inspect `*.types.ts` files under
  `features/<resource>` and accept configured generated aliases, not only the
  default `@/types/generated`.

## Pest/Testbench Acceptance Tests

Use Pest with Orchestra Testbench package tests. The tests should create a
temporary Laravel app filesystem, run the Artisan command, and assert both
stdout JSON and the `.iak/runs/<run-id>/audit.json` artifact.

Required tests:

1. Clean scaffold passes: a fixture matching `iak:make-resource vehicles`
   exits `0`, returns `schema: iak.audit.v1`, `event:
   iak.audit.completed`, `status: passed`, zero violations, and writes the
   artifact.
2. Design-system violations fail: a feature component containing `p-[34px]`,
   `#ffffff`, and `bg-blue-500` exits `1` and reports all three
   `iak/design-system/*` rule IDs with file, line, column, hit, and
   fingerprint.
3. Forbidden folders fail: `resources/js/hooks/use-vehicles.ts` and a
   non-generated `resources/js/actions/vehicles.ts` produce
   `iak/role/no-top-level-behavior-folder`; `resources/js/actions/generated/*`
   remains allowed.
4. Missing stories fail: removing required `components/ui/button.stories.tsx`,
   `components/app/filter-bar.stories.tsx`, or
   `features/vehicles/vehicle-table.stories.tsx` reports the matching
   `iak/stories/*` rule and expected story path.
5. Missing generated type import fails: `features/vehicles/vehicle.types.ts`
   without a generated-contract import reports
   `iak/types/generated-contract-import-required`; adding the import makes the
   check pass.
6. Blocked config is structured: invalid config exits `2`, returns
   `status: blocked`, writes JSON when possible, and includes an `errors[]`
   entry with a stable `code`.

Do not add browser, Storybook runtime, Laravel route, database, or Boost MCP
assertions to this command's first acceptance suite.

## `iak:verify` Consumption

`iak:verify` can consume an audit result by path or by running `iak:audit`
itself. It needs only:

- `schema`, `event`, `version`, and `runId` for validation and linking;
- `status` and exit-equivalent totals to decide pass, fail, or blocked;
- `artifacts.audit.path` for evidence;
- `violations[]` with `rule`, `severity`, `file`, `line`, `message`,
  `suggestion`, and `fingerprint`;
- `checks[]` for a compact evidence summary;
- `meta.configHash` to detect stale audit artifacts when config changes.

Verify should fail when audit status is `failed` or `blocked`, pass audit
evidence through into `.iak/runs/<verify-run-id>/verify.json`, and avoid
rerunning scanner logic when a fresh valid audit artifact is supplied.
