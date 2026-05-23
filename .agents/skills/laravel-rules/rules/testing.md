# Testing Rules

Always write Pest tests with `test()`:

```php
test('discovers INE indicators from the configured API',
    /**
     * @throws Exception
     */
    function (): void {
        // ...
    });
```

Use the PHPDoc exception block when the closure may throw, including framework, database, HTTP fake, or `firstOrFail()` paths. Keep the block immediately before the closure so it documents the executable test body.

Use `beforeEach()` for resolved actions, queries, services, or reusable setup objects:

```php
beforeEach(function (): void {
    $this->createWaitlistSignup = resolve(CreateWaitlistSignup::class);
});
```

Apply this even if the current file has one test or the object is only used once. This keeps setup consistent and makes later tests cheaper to add.

## Expectations

Always chain Pest expectations with `->and()` when asserting multiple facts in the same test. Do not create separate expectation blocks unless they assert a separate behavior that should be its own test.

```php
expect($waitlistSignup->id)->toBeString()
    ->and($waitlistSignup->email)->toBe('ana@example.com')
    ->and($waitlistSignup->created_at)->not->toBeNull();
```

Avoid this shape:

```php
expect($waitlistSignup->id)->toBeString();
expect($waitlistSignup->email)->toBe('ana@example.com');
expect($waitlistSignup->created_at)->not->toBeNull();
```

## Test Structure

`tests/Unit` mirrors the `app` folder structure, except `app/Console` and `app/Http` code belongs in feature tests.

- `app/Actions/CreateWaitlistSignup.php` -> `tests/Unit/Actions/CreateWaitlistSignupTest.php`
- `app/Queries/ListStatisticsObservationsQuery.php` -> `tests/Unit/Queries/ListStatisticsObservationsQueryTest.php`
- `app/Data/CreateWaitlistSignupData.php` -> `tests/Unit/Data/CreateWaitlistSignupDataTest.php`
- `app/Models/User.php` -> `tests/Unit/Models/UserTest.php`
- `app/Console/Commands/ImportSomething.php` -> `tests/Feature/ImportSomethingCommandTest.php`
- `app/Http/Controllers/WelcomeController.php` -> `tests/Feature/WelcomeControllerTest.php`

Do not create `tests/Unit/Console` or `tests/Unit/Http` for application Console or Http classes.

## Test Doubles

Do not use generic mocks for project services. Prefer Laravel fakes for framework concerns such as events, queues, mail, notifications, HTTP, storage, bus, and time.

For project service dependencies, implement in-memory services under `tests/Utils/Services` and bind them in the test setup.

```php
beforeEach(function (): void {
    $this->ineIndicatorDataSource = new InMemoryIneIndicatorDataSource();

    $this->app->instance(IneIndicatorDataSource::class, $this->ineIndicatorDataSource);
});
```

## Checklist

- Use `test()`, never `it()`.
- Chain related expectations with `->and()`.
- Add an exception PHPDoc directly before Pest closures that may throw.
- Put resolved actions, queries, services, and reusable setup objects in `beforeEach()`.
- Assign setup objects to `$this` even if only one test currently uses them.
- Mirror `app/` structure in `tests/Unit`, excluding Console and Http.
- Put Console and Http tests under `tests/Feature`.
- Prefer Laravel fakes over mocks.
- Use in-memory services in `tests/Utils/Services` for project service dependencies.
