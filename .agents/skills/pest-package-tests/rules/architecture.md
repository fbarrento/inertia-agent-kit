# Architecture Rules

A framework-agnostic package keeps the same CQRS shape as the Laravel rules, minus the Laravel-only pieces (no container, no facades, no Eloquent).

## Layout

Keep `src/` flat and rely on naming conventions. Use single-word top-level folders.

```
src/
├── Actions/        # mutating operations (handle())
├── Queries/        # read-only operations (__invoke() or chainable)
├── Services/       # ports to external systems (interfaces + adapters)
├── Data/           # data objects / DTOs (typed payloads)
├── ValueObjects/   # domain values with invariants
├── Exceptions/     # package-specific exceptions
└── Support/        # internal helpers used by the package
```

Mirror this layout under `tests/Unit/`. See [testing.md](testing.md).

## CQRS

- **Actions mutate state.** One action, one operation. Expose `handle()`. Pass a `Data` object in, return a domain object (or `void` if no result is meaningful).
- **Queries read state.** No mutation. Expose `__invoke()` or chainable filters that terminate in a read method.
- **Services are ports to external systems.** Define them as interfaces in `src/Services/`. Production adapters live in the same namespace. In-memory adapters live under `tests/Utils/Services/`.

The same orchestrator/single-responsibility split applies as in `laravel-rules/actions.md`:

- Single-responsibility action: `CreateWaitlistSignup`.
- Orchestrator action: `RegisterOrganization` (coordinates several single-responsibility actions).

## Dependency Injection

Use constructor injection. There is no container, so:

- Actions, queries, and services declare their dependencies on the constructor.
- Tests build them with `new` in `beforeEach()`.
- The package's public facade (if any) wires production adapters.
- Do not pull collaborators from a global registry, singleton, or static factory.

```php
final class ListIndicatorsQuery
{
    public function __construct(
        private readonly IndicatorDataSource $indicatorDataSource,
    ) {}

    public function __invoke(): IndicatorCollection
    {
        return $this->indicatorDataSource->all();
    }
}
```

## Data Objects vs Value Objects

- **Data objects** (`Data` suffix) carry typed payloads across boundaries (input to an action, output of a query). They are mostly transport — minimal behavior.
- **Value objects** carry domain meaning and enforce invariants in the constructor (`EmailAddress::__construct` rejects malformed input).

Build both with `fbarrento/data-factory` for tests. See [data-factory.md](data-factory.md).

## Exceptions

- Throw concrete, package-namespaced exceptions (`InvalidWaitlistEmail extends InvalidArgumentException`).
- Each exception type lives in `src/Exceptions/`.
- Actions enforce invariants and throw business exceptions; queries return empty results rather than throw for "not found" cases unless the absence is exceptional.

## Checklist

- One responsibility per action; one read per query; one port per service.
- Constructor injection only. No container, no singletons, no static factories.
- Data objects for transport; value objects for domain meaning with invariants.
- Concrete, namespaced exceptions in `src/Exceptions/`.
- `src/` and `tests/Unit/` layouts mirror each other.
