# Inertia Agent Kit Positioning

Status: Phase 0 repositioning spec.

Use this as the source of truth when rewriting docs from `design-system-kit`
to Inertia Agent Kit.

## Product Statement

Inertia Agent Kit is an AI-native convention, feedback, design-system
contract, and verification layer for Inertia apps.

It gives AI agents Laravel-like frontend conventions: predictable page and
resource structure, reusable component roles, generated type discipline,
design-system rules, human feedback queues, browser verification, and
evidence-based handoffs.

The product boundary is **Inertia**. Laravel is the first backend integration.
React, Vue, and Svelte are renderer adapters.

## Target User

- Laravel and Inertia teams using AI agents to build or refactor product UI.
- Staff and platform engineers who need frontend work to follow repeatable
  conventions across resources, pages, components, and tests.
- AI coding agents that need compact, structured context instead of broad repo
  scans and prose-only instructions.

## Problem

AI-generated frontend work usually does not scale. Agents create huge page
files, duplicate components, invent styling tokens, mix page/resource/component
responsibilities, handwrite backend-derived types, skip stories or tests, and
handoff without inspecting the browser outcome.

Laravel works well for agents because it gives them strong roles and clear
surfaces: models, jobs, listeners, policies, resources, routes, tests, and
static analysis targets. Inertia Agent Kit brings that same convention and
feedback discipline to Inertia frontends.

## Product Model

Inertia Agent Kit provides:

- **Conventions**: frontend roles that mirror Laravel resource thinking.
- **Scaffolding**: commands that create the correct split before agents write
  implementation code.
- **Audit**: machine-readable rule checks that agents can self-heal from.
- **Feedback protocol**: one queue for app-page, Storybook, browser-test, and
  agent feedback.
- **Outcome verification**: required rendered-output checks before handoff,
  using tools such as Pest Browser, Playwright, Storybook tests, screenshots,
  console logs, and accessibility checks.
- **JSON handoffs**: stable schemas for plans, audits, feedback, verification,
  manifests, and final handoffs.

## Laravel And Boost Boundary

Laravel is the first-class package path because it supplies the backend source
of truth and a strong agent substrate.

Use Laravel Boost for generic Laravel awareness: package/version-aware docs,
app inspection, browser logs, database/schema context, logs, absolute URLs, and
baseline MCP setup.

Use Inertia Agent Kit for Inertia frontend discipline: page/resource
conventions, frontend role graphs, generated type discipline, design-system
tokens and primitives, Storybook/HITL feedback, architecture/style audits, and
verification artifacts.

Do not position IAK as a replacement for Boost. IAK should integrate with Boost
and add only the Inertia-specific layer.

## Existing Design-System Kit IP That Remains

Keep and adapt these parts of the old design-system-kit work:

- Registry and install mechanics for publishing conventions, stubs, and
  generated assets into existing apps.
- File ownership rules for generated, published, user-owned, and audited files.
- `ds-` token namespace discipline and semantic styling rules.
- Token contracts, primitive component contracts, app component contracts, and
  Storybook-backed examples where they support verification.
- Agent-readable rules and concise guidelines.
- Audit checks for raw styling, missing stories, oversized files, misplaced
  responsibilities, and contract violations.
- Structured JSON output for plans, audits, verification, feedback, manifests,
  and handoffs.

Design-system work remains part of the product, but it is now one contract
inside the larger agent workflow. It is not the whole product.

## Non-Goals

- Do not build a full component library.
- Do not rebuild Storybook.
- Do not force Feature-Sliced Design as the only architecture.
- Do not add global `queries/*`, `actions/*`, `forms/*`, `hooks/*`, or
  `composables/*` folders by default.
- Do not trust agents to handwrite backend-derived TypeScript types.
- Do not chase framework-agnostic abstractions before Laravel/Inertia works
  end-to-end.
- Do not make MCP a thin wrapper over existing CLI commands; MCP must expose
  structured context and feedback.
- Do not make Instruckt a required dependency; define a compatible feedback
  protocol that Instruckt can participate in.

## Framing To Retire

Stop using these as core framing:

- `design-system-kit` as the product name.
- "A design system installer" as the primary value proposition.
- React-only language.
- Component library, catalog, or registry-first positioning.
- Generic frontend architecture essay language.
- Feature-Sliced Design as the default or only architecture.
- Top-level behavior buckets such as `queries`, `actions`, `forms`, `hooks`, or
  `composables` as the default app shape.
- CLI/plugin mechanics as the headline rather than the agent workflow outcome.
- Freeform prose handoffs as the source of truth.

## Rewrite Rule

When updating docs, lead with Inertia Agent Kit as the convention, feedback,
design-system contract, and verification layer for AI-assisted Inertia work.
Mention the old design-system-kit only as historical source material or
retained implementation IP.
