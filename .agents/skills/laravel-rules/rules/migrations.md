# Migration Rules

## IDs And Timestamps

Always use UUID primary keys:

```php
$table->uuid('id')->primary();
```

Always place timestamps immediately after the primary key:

```php
$table->uuid('id')->primary();
$table->timestamps();
```

## Foreign Keys

Always use `foreignIdFor()` for foreign keys and always constrain them:

```php
$table->foreignIdFor(User::class)->constrained();
```

Never use cascading deletes or cascading updates in migrations. Domain behavior should be explicit in actions, not hidden in database cascade rules.

## Defaults And Enums

Never define database defaults in migrations. Set defaults explicitly in application code or data objects.

Never use database enum columns. Use strings or other portable column types and cast to PHP enums in Eloquent models.

## Down Methods

Never define a `down()` method. Migrations are forward-only in this project.

## Checklist

- UUID primary key via `$table->uuid('id')->primary()`.
- `$table->timestamps()` appears immediately after the primary key.
- Foreign keys use `foreignIdFor(...)->constrained()`.
- No `cascadeOnDelete()`, `cascadeOnUpdate()`, or equivalent cascade behavior.
- No column defaults.
- No database enum columns.
- No `down()` method.
