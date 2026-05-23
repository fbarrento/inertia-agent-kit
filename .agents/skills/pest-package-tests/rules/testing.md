# Testing Rules

Always write Pest tests with `test()`:

```php
test('discovers indicators from the configured source',
    /**
     * @throws Throwable
     */
    function (): void {
        // ...
    });
```

Always type-hint the exception in the PHPDoc. Use `@throws Throwable` as the default catch-all when several exception paths apply. Use the concrete class (`@throws InvalidArgumentException`, `@throws JsonException`) when exactly one exception type is in play. An untyped `@throws` is not acceptable.

Use the PHPDoc exception block when the closure may throw, including any path that calls a method declaring `@throws`, performs I/O, or unwraps a nullable. Keep the block immediately before the closure so it documents the executable test body.

Use `beforeEach()` for collaborators and reusable setup objects:

```php
beforeEach(function (): void {
    $this->createWaitlistSignup = new CreateWaitlistSignup();
});
```

Apply this even if the file has one test or the object is only used once. It keeps setup consistent and makes later tests cheaper to add. Resolve dependencies by `new` and constructor injection. There is no Laravel container in a framework-agnostic package, so do not call `resolve()`, `app()`, or `$this->app->instance()`.

## Expectations

Always chain Pest expectations with `->and()` when asserting multiple facts in the same test. Do not create separate `expect()` blocks unless they assert a separate behavior that should be its own test.

```php
expect($waitlistSignup->id)->toBeString()
    ->and($waitlistSignup->email)->toBe('ana@example.com')
    ->and($waitlistSignup->createdAt)->not->toBeNull();
```

Avoid this shape:

```php
expect($waitlistSignup->id)->toBeString();
expect($waitlistSignup->email)->toBe('ana@example.com');
expect($waitlistSignup->createdAt)->not->toBeNull();
```

## Test Structure

`tests/Unit/` mirrors the package's `src/` folder. Each source class has one matching test file with the `Test` suffix.

- `src/Actions/CreateWaitlistSignup.php` -> `tests/Unit/Actions/CreateWaitlistSignupTest.php`
- `src/Queries/ListIndicatorsQuery.php` -> `tests/Unit/Queries/ListIndicatorsQueryTest.php`
- `src/Data/CreateWaitlistSignupData.php` -> `tests/Unit/Data/CreateWaitlistSignupDataTest.php`
- `src/Services/IndicatorDataSource.php` -> `tests/Unit/Services/IndicatorDataSourceTest.php`
- `src/ValueObjects/EmailAddress.php` -> `tests/Unit/ValueObjects/EmailAddressTest.php`

Use `tests/Feature/` only for cross-cutting behavior that exercises several units together (for example, a public facade class that wires actions and queries). A package has no Console or Http folder, so do not invent `tests/Feature/Console` or `tests/Feature/Http`.

## Test Doubles

Do not use generic mocking libraries for project service dependencies. Implement in-memory doubles under `tests/Utils/Services/` (or `tests/Utils/<Category>/` for non-service collaborators) and inject them through the constructor under test.

```php
beforeEach(function (): void {
    $this->indicatorDataSource = new InMemoryIndicatorDataSource();
    $this->listIndicators = new ListIndicatorsQuery($this->indicatorDataSource);
});
```

The in-memory double implements the same interface as the production collaborator and exposes the minimum surface tests need to seed state and assert effects. Keep doubles dumb: store inputs, return canned outputs, raise typed exceptions on misuse.

Do not reach for Laravel fakes (`Bus::fake()`, `Http::fake()`, `Event::fake()`, etc.). They depend on a Laravel application and are unavailable in a framework-agnostic package. If the package wraps HTTP, time, or randomness, expose a port (interface) for it and provide an in-memory adapter under `tests/Utils/`.

## Checklist

- Use `test()`, never `it()`.
- Chain related expectations with `->and()`.
- Add a typed exception PHPDoc (`@throws Throwable` or a concrete class) directly before any Pest closure that may throw.
- Name every variable after the object or returned type it holds — see `naming.md`.
- Put collaborators and reusable setup objects in `beforeEach()` and assign them to `$this`.
- Build collaborators with `new`; do not call `resolve()`, `app()`, or any Laravel container API.
- Mirror `src/` structure under `tests/Unit/`.
- Reserve `tests/Feature/` for cross-cutting tests; do not create Console or Http subfolders.
- Replace project service dependencies with in-memory doubles in `tests/Utils/`.
- Do not use Laravel fakes; expose a port and write an in-memory adapter instead.
