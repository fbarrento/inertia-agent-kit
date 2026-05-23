# Query Rules

## Shape

Queries are fluent read-only query objects. They live in flat `app/Queries` and use the `Query` suffix.

Every query starts with `__invoke()` to initialize the builder and return a clone:

```php
/** @var Builder<Article> */
private Builder $builder;

public function __invoke(): self
{
    $this->builder = Article::query();

    return clone $this;
}
```

Do not implement read queries as a single `handle()` method. `handle()` belongs to actions.

## Filters

Filter methods mutate the internal builder and return `$this`:

```php
public function forSource(Source $source): self
{
    $this->builder->where('source_id', $source->id);

    return $this;
}
```

Use model arguments when the caller already has the model. Use scalar arguments only for external identifiers or value filters.

Reusable read filters belong here, not in model scopes. Do not add local or global Eloquent scopes to models.

## Terminal Methods

Queries expose terminal read methods that delegate to the builder:

```php
/** @return Builder<Article> */
public function builder(): Builder
{
    return $this->builder;
}

public function count(): int
{
    return $this->builder->count();
}

public function exists(): bool
{
    return $this->builder->exists();
}

public function first(): ?Article
{
    return $this->builder->first();
}

public function firstOrFail(): Article
{
    /** @var Article */
    return $this->builder->firstOrFail();
}

/** @return Collection<int, Article> */
public function get(): Collection
{
    return $this->builder->get();
}
```

## Projection Conversion

When consumers should not receive Eloquent models, add explicit projection methods using project terminology.

```php
public function toResult(Article $article): ArticleResult
{
    return new ArticleResult(...);
}

/** @return SupportCollection<int, ArticleResult> */
public function toResultCollection(): SupportCollection
{
    return $this->get()->map(fn (Article $article): ArticleResult => $this->toResult($article));
}
```

Use consistent project terminology:

- Use `toResult()` / `toResultCollection()` for result objects.
- Use `toData()` / `toDataCollection()` for data objects.
- Use `toDto()` / `toDtoCollection()` only if DTO is the project's established term.

Avoid vague names like `toCollection()` for projected objects. Reserve `toCollection()` for raw collection conversion only when it does not imply projection.

## Boundaries

- Queries never mutate state.
- Queries do not call external services.
- Queries may compose Eloquent filters, relationships, eager loading, ordering, counting, and explicit result/data projection.
- Actions, controllers, jobs, and other application code may inject queries for reads.

## Tests

Query tests live in `tests/Unit/Queries` and resolve the query in `beforeEach()`.

Cover every filter, terminal method, ordering rule, empty state, and projection conversion method.
