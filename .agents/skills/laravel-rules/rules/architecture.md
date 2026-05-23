# Architecture Rules

## Folders

Use Laravel's conventional top-level folders with a flat structure. Do not create nested domain folders unless explicitly approved.

- Good: `app/Actions/CreateUser.php`
- Avoid: `app/Actions/User/CreateUser.php`
- Good: `app/Queries/ListUsersQuery.php`
- Avoid: `app/Queries/User/ListUsers.php`

Rely on clear class names for discoverability instead of folder nesting.

## CQRS

Follow CQRS boundaries:

- Actions contain business logic and state mutations. Actions do not use the `Action` suffix.
- Queries contain read operations and read-specific query composition. Queries use operation-specific names with the `Query` suffix, such as `ListUsersQuery`.
- Services talk to external systems such as third-party APIs, SDKs, webhooks, or remote data sources.

Do not put state mutations in queries. Do not put internal business workflows in services when an action is the correct home.

## Query Objects

Queries are fluent query objects, not one-off `handle()` methods. They initialize their Eloquent builder in `__invoke()`, expose chainable filter methods, and expose terminal read methods.

Use this query object shape:

- `__invoke()` initializes the Eloquent builder and returns a cloned query object.
- Filter methods mutate the internal builder and return `$this` for chaining.
- Terminal methods perform reads, such as `builder()`, `get()`, `first()`, `firstOrFail()`, `count()`, and `exists()`.
- Optional projection methods may convert Eloquent models into result/data objects, such as `toResult()` and `toResultCollection()`.
- Reusable read filters live in query objects, never in model scopes.

Do not add `handle()` methods to queries. `handle()` belongs to actions.

## Dependency Injection

Prefer constructor and method dependency injection over facades. Use facades only when a Laravel testing fake or framework convention makes that the clearest option.

Use Laravel contextual attributes for scalar configuration, named drivers, and contextual dependencies when applicable, for example:

```php
use Illuminate\Container\Attributes\Config;

final class ImportIneIndicatorObservations
{
    public function __construct(
        #[Config('services.ine.base_url')]
        private readonly string $baseUrl,
    ) {}
}
```

Prefer built-in attributes such as `Config`, `Storage`, `Cache`, `DB`, `Log`, `Give`, `Tag`, `Context`, `RouteParameter`, and `CurrentUser` before writing manual contextual bindings.

## Checklist

- Keep `app/Actions`, `app/Queries`, and `app/Services` flat.
- Put state-changing workflows in actions.
- Put read-only operations in queries.
- Implement queries as fluent read-only query objects.
- Use services only for external integrations.
- Inject dependencies instead of reaching for facades.
- Use Laravel contextual attributes for config and contextual dependencies when they fit.
