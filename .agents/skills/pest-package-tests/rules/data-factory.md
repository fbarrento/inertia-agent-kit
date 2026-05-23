# Data Factory Rules

Use [fbarrento/data-factory](https://github.com/fbarrento/data-factory) as the default for building test data for data objects, DTOs, and value objects in framework-agnostic packages. Consult the upstream README and `docs/` for full API details — this file only covers project shape.

## When to Use It

- **Always:** data objects (`Data` suffix), DTOs, request/response shapes, command/query payloads.
- **Encouraged:** value objects with non-trivial construction.
- **Skip:** Eloquent models — use Eloquent factories. The package should rarely depend on Eloquent at all.

## Layout

- Factories live under `tests/Factories/` (not in `src/`).
- One factory per class, with the `Factory` suffix: `CreateWaitlistSignupData` -> `tests/Factories/CreateWaitlistSignupDataFactory.php`.
- The target class uses the `HasDataFactory` trait so tests call `Class::factory()`.

## Rules

- Define a complete, valid default in `definition()`. Tests should be able to call `->make()` with zero arguments and get a usable instance.
- Encode every recurring variation as a named state method (`->succeeded()`, `->invited()`). Inline `->state([...])` calls are reserved for genuinely one-off cases.
- Default to `->make()`. Use `->create()` only when persistence is under test (rare in a framework-agnostic package).
- For collections, use `->count(n)->make()` instead of building arrays by hand.
- For object graphs, nest factories (see upstream `nested-factories` docs) — do not assemble children manually.

## Checklist

- Use `fbarrento/data-factory` for data objects and DTOs; Eloquent factories only when the package depends on Eloquent.
- Factories live in `tests/Factories/` with the `Factory` suffix.
- `HasDataFactory` trait on the target class.
- `definition()` returns a complete, valid default.
- Variations are named states, not inline `state([...])`.
- `->make()` by default; `->create()` only when persistence is under test.
