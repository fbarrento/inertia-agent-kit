# Inertia Agent Kit

Inertia Agent Kit is an AI-native convention, feedback, audit, and
verification kit for Laravel Inertia frontends.

It gives frontend agents Laravel-like constraints: pages mirror resource
controllers, features stay resource-local, backend-derived types come from
generated artifacts, formatting and translation stay on the backend, styling
uses semantic design-system tokens, and handoff requires JSON evidence.

## Commands

Install from the repository while the package is pre-release:

```bash
npm install -D git+ssh://git@github.com/fbarrento/inertia-agent-kit.git
```

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

## First Milestone

The current implementation proves the first fixture loop:

- `iak init` writes `iak.config.json`, `.iak/config.json`, rules, manifest
  directories, generated-type roots, token CSS, and basic UI/layout files.
- `iak new resource vehicles` creates thin page adapters, resource-local
  feature components, colocated stories, fixtures, and generated type imports.
- `iak audit --json` catches arbitrary Tailwind values, raw hex colors,
  primitive color utilities, forbidden global behavior buckets, missing stories,
  and feature types that do not import generated backend contracts.
- `iak feedback` creates, lists, shows, and resolves local
  `iak.feedback.v1` records under `.iak/feedback`.
- `iak verify --json` runs audit, blocks on unresolved feedback, writes
  `.iak/runs/<run-id>/verify.json`, and records screenshot metadata plus a local
  screenshot artifact.

Run the smoke test:

```bash
npm test
```

## Local Artifacts

IAK writes local agent/runtime state under `.iak/`. The repository ignores
feedback and verification run artifacts by default:

- `.iak/feedback/`
- `.iak/runs/`

Committed source should include configuration, docs, schemas, and package code,
not local browser screenshots, console logs, or feedback work queues.

## Specs

The orchestration and lane specs live in `docs/inertia-agent-kit/`. Start with:

- `docs/inertia-agent-kit/implementation-rfc.md`
- `docs/inertia-agent-kit/scaffolding-commands.md`
- `docs/inertia-agent-kit/audit-checks.md`
- `docs/inertia-agent-kit/feedback-protocol.md`
- `docs/inertia-agent-kit/verification-loop.md`
- `docs/inertia-agent-kit/mcp-tools-and-resources.md`
