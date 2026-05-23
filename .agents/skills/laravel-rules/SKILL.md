---
name: laravel-rules
description: Applies Francisco Barrento's Laravel, Pest, model, and naming conventions that override generic Laravel guidance. Use when writing, editing, reviewing, or refactoring Laravel PHP code in this project, especially tests, models, actions, services, and Pest files.
license: MIT
metadata:
  author: Francisco Barrento
---

# FBarrento Laravel Rules

Higher priority than `.agents/skills/laravel-best-practices` when both apply. Use Laravel and Pest documentation for API syntax; use these files for project code shape.

## Quick Reference

### 1. Naming -> `rules/naming.md`

- Actions are verb-first and do not use the `Action` suffix.
- Queries use the `Query` suffix and describe the read operation.
- Services use the `Service` suffix.
- Models and enums do not use `Model` / `Enum` suffixes.
- Tests use the `Test` suffix.

### 2. Testing -> `rules/testing.md`

- Use `test()` for Pest tests, never `it()`.
- Chain Pest expectations with `->and()` instead of creating separate expectation blocks.
- Add exception PHPDocs directly before Pest closures that may throw.
- Resolve setup objects in `beforeEach()` and assign them to `$this`.
- Mirror `app/` structure in `tests/Unit`, except Console and Http code belongs in feature tests.
- Prefer Laravel fakes over mocks; use in-memory test services under `tests/Utils/Services` for services.

### 3. Models -> `rules/models.md`

- Create models with `php artisan make:model ModelName -fms --no-interaction`.
- Cast every persisted attribute explicitly.
- Include a `to array` test for every model.
- Do not implement model scopes; put reusable read filters in query objects.

### 4. Migrations -> `rules/migrations.md`

- Use UUID primary keys and place timestamps immediately after ids.
- Use constrained `foreignIdFor()` for foreign keys.
- Never use cascade, defaults, database enums, or `down()` methods.

### 5. Architecture -> `rules/architecture.md`

- Keep Laravel folders flat and rely on naming conventions.
- Follow CQRS: actions mutate state, queries read state.
- Use services only for external systems.
- Prefer dependency injection over facades.
- Use Laravel contextual attributes for config/context dependencies.

### 6. Queries -> `rules/queries.md`

- Queries are fluent read-only query objects with `__invoke()` initialization.
- Queries expose chainable filters, terminal read methods, `builder()`, and optional result/data projection methods.
- Queries return Eloquent models/collections or explicit result/data projections, never mutate state.
- Queries replace model scopes so reads remain explicit and easy to trace.
- Query projections use explicit names like `toResult()` and `toResultCollection()`.

### 7. Actions -> `rules/actions.md`

- Actions own business logic and expose `handle()`.
- Pass data objects, not Eloquent models, as action inputs.
- Single-write actions do not need transactions.
- Orchestrator actions use transactions for multiple writes.
- Actions enforce invariants and throw specific business exceptions.

### 8. Jobs -> `rules/jobs.md`

- Jobs contain no business logic.
- Jobs inject actions when business operations are required.
- Jobs are always dispatched after commit.

## How to Apply

1. Identify the touched area and read the matching rule file before editing.
2. When rules conflict, this skill wins over `.agents/skills/laravel-best-practices`.
3. Check nearby files for current project patterns, but do not copy patterns that violate these rules.
4. Verify Laravel API syntax with `search-docs` before using framework-specific APIs or attributes.
