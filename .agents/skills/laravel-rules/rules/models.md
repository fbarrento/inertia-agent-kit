# Model Rules

## Creation

Always create new models with Artisan and include the related factory, migration, and seeder:

```bash
php artisan make:model ModelName -fms --no-interaction
```

Use the concrete model name, for example:

```bash
php artisan make:model WaitlistSignup -fms --no-interaction
```

Do not manually create model, migration, factory, or seeder files when Artisan can generate the correct structure.

## Casts

Every Eloquent model must cast every persisted attribute in `casts(): array`, including:

- Primary keys and foreign keys.
- Strings, decimals, booleans, integers, enums, and nullable attributes.
- `created_at`, `updated_at`, and other date/time columns.

Do not rely on Laravel's implicit timestamp casts when editing or creating a model. Keep casts explicit and complete.

## Scopes

Never implement Eloquent scopes on models. Model scopes hide query behavior behind Laravel magic and make it harder to understand what is being called and when.

Put reusable read filters and query composition in query objects under `app/Queries` instead.

## Tests

Every model must have a unit test that verifies its array shape:

```php
test('to array', function (): void {
    $model = SomeModel::factory()->create()->refresh();

    expect(array_keys($model->toArray()))
        ->toBe([
            'id',
            'name',
            'created_at',
            'updated_at',
        ]);
});
```

The expected array keys should match the public serialized shape, including hidden attributes and appended values when applicable.

## Checklist

- Create new models with `php artisan make:model ModelName -fms --no-interaction`.
- Cast all persisted columns explicitly.
- Include enum casts for enum-backed columns.
- Include decimal precision in decimal casts.
- Include timestamp casts rather than relying on Laravel defaults.
- Do not add local or global model scopes.
- Add or update a `to array` test for every model touched.
