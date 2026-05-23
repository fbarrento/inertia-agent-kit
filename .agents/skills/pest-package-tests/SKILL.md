---
name: pest-package-tests
description: Writes Pest tests for framework-agnostic PHP packages using Francisco Barrento's testing and naming conventions, with fbarrento/data-factory as the default for data-object test data. Use when writing, editing, or reviewing Pest tests inside a standalone Composer package (no Laravel app, no Testbench, no Eloquent), or when scaffolding a factory for a data object / DTO / value object in such a package.
license: MIT
metadata:
  author: Francisco Barrento
---

# Pest Tests for Framework-Agnostic Packages

Use this skill for standalone Composer packages that do not boot Laravel. There is no service container, no Laravel fakes, and no Eloquent. Resolve collaborators with `new`, build test data with `fbarrento/data-factory` for data objects, and roll in-memory doubles for service dependencies.

When testing inside a full Laravel application instead, prefer the `laravel-rules` skill.

## Quick Start

```php
use FBarrento\DataFactory\Factory;
use Vendor\Package\Actions\CreateWaitlistSignup;
use Vendor\Package\Data\CreateWaitlistSignupData;

beforeEach(function (): void {
    $this->createWaitlistSignup = new CreateWaitlistSignup();
});

test('creates a waitlist signup from data',
    /**
     * @throws Exception
     */
    function (): void {
        $data = CreateWaitlistSignupData::factory()->make();

        $waitlistSignup = $this->createWaitlistSignup->handle($data);

        expect($waitlistSignup->id)->toBeString()
            ->and($waitlistSignup->email)->toBe($data->email)
            ->and($waitlistSignup->createdAt)->not->toBeNull();
    });
```

## Rule Index

### 1. Testing -> `rules/testing.md`

- Use `test()` for Pest tests, never `it()`.
- Chain expectations with `->and()` instead of separate `expect()` blocks.
- Add an exception PHPDoc directly before any closure that may throw.
- Build setup objects with `new` in `beforeEach()` and assign them to `$this`.
- Mirror `src/` structure in `tests/Unit/`.
- Use `tests/Feature/` for cross-cutting or integration tests.
- Replace project service dependencies with in-memory doubles under `tests/Utils/`.
- Do not use Laravel fakes or `resolve()` — the package has no Laravel container.

### 2. Naming -> `rules/naming.md`

- Test files use the `Test` suffix and mirror the source class name.
- Actions are verb-first and do not use the `Action` suffix.
- Queries use the `Query` suffix; services use the `Service` suffix.
- Data objects use the `Data` suffix; value objects use a domain noun (no `VO`).
- Name variables and `$this` properties for the value they contain.

### 3. Architecture -> `rules/architecture.md`

- Keep `src/` flat: `Actions/`, `Queries/`, `Services/`, `Data/`, `ValueObjects/`, `Exceptions/`.
- Follow CQRS: actions mutate, queries read, services are ports to external systems.
- Use constructor injection only — no container, no singletons, no static factories.
- Data objects (`Data` suffix) carry payloads; value objects enforce domain invariants.
- Throw concrete, package-namespaced exceptions from `src/Exceptions/`.

### 4. Data Factories -> `rules/data-factory.md`

- Use `fbarrento/data-factory` as the default for data objects, DTOs, and value objects.
- Use Eloquent factories only when the package itself depends on Eloquent.
- Place factories under `tests/Factories/` and name them `<Class>Factory`.
- Add the `HasDataFactory` trait to the class so tests call `Class::factory()`.
- Expose readable states (`->succeeded()`, `->expired()`) instead of inline `state([...])` overrides in tests.
- Prefer `->make()` for in-memory tests; reserve `->create()` for cases that need persistence.

## Worked Example

See [EXAMPLES.md](EXAMPLES.md) for an end-to-end walkthrough: data object + factory + port + in-memory adapter + action + Pest test, all four rules applied together.

## How to Apply

1. Identify whether the target is a framework-agnostic package. If a Laravel application is present, switch to the `laravel-rules` skill instead.
2. Read the matching rule file before writing or editing tests.
3. For new data-object tests, scaffold the factory under `tests/Factories/` first, then write the Pest test on top of it.
4. Verify Pest and data-factory API syntax against the upstream docs when uncertain; this skill governs project shape, not framework APIs.
