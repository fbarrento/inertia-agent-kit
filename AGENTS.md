# AGENTS.md

Codex agents working in this repo must follow `CLAUDE.md` as the canonical
project instruction file. Do not treat `CLAUDE.md` as Claude-only guidance.

## Worker Runtime

When spawning Codex worker agents for this repo:

- Use `gpt-5.3-codex-spark`.
- Do not use extra-high reasoning by default.
- Respawn workers that start with the wrong model or reasoning effort.

Claude worker agents may use Sonnet or Opus 4.6, but prompts must be strict:
project folder names, mirrored tests, 100.0% coverage, no global Pest helper
functions, no private Action helpers, and JSON handoff rules are hard
constraints.

## Local Rules

- Production Actions live in `src/Actions`, not `src/Action`.
- Mirrored Action tests live in `tests/Unit/Actions`.
- Every production class needs a matching mirrored unit test.
- Do not define global helper functions in Pest test files.
- Actions must not hide helper logic in private methods or local closures.
- Command/artifact JSON payloads should be Data objects; add `Arrayable` when
  array interop is needed.
- Commit messages and PR titles must use Conventional Commits.
