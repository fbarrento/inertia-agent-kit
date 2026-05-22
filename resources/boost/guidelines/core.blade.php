# Inertia Agent Kit

IAK is the Inertia/frontend convention, feedback, design-system, scaffolding,
audit, and verification layer for Laravel/Inertia apps. Boost remains the
Laravel substrate: use Boost for generic Laravel facts, tools, docs, routes,
logs, database/schema or safe read queries, browser logs, app info, URLs, last
errors, MCP setup, and resource discovery before guessing.

Apply IAK rules when work touches Inertia pages, resource workflows, frontend
components, generated backend-derived TypeScript, design-system styling,
Storybook/HITL evidence, `iak.feedback.v1` records, audit results, UI
verification, or handoff evidence.

Required loop:
1. Establish Laravel context with Boost.
2. Read the IAK manifest and local conventions.
3. Identify the affected route, page, feature, layout, or reusable component.
4. Make the smallest coherent Inertia/frontend change.
5. Run focused IAK checks and prefer `IAK_AGENT=1` plus `--json` output.
6. Report changed files, commands, artifact paths, blockers, and remaining risk.

IAK Artisan surface: `iak:init`, `iak:make-resource`, `iak:audit`,
`iak:feedback`, and `iak:verify`. Reference command JSON and artifact paths;
do not paste large schemas, screenshots, DOM dumps, generated types, or
framework documentation.

For any non-trivial Inertia UI change, feedback resolution, audit failure,
verification failure, design-system/story evidence change, or final handoff,
consult the `inertia-agent-kit` skill.

Do not manually edit Boost-generated files such as `CLAUDE.md`, `AGENTS.md`,
`.mcp.json`, `boost.json`, or generated guidelines/skills. Refresh those
through Boost.
