# Job Rules

## Purpose

Jobs orchestrate queued execution only. They must not contain business logic.

If a job needs to perform a business operation, inject the relevant action and call its `handle()` method from the job handler.

```php
final class ImportIndicatorObservationsJob implements ShouldQueue
{
    public function handle(ImportIndicatorObservations $importIndicatorObservations): void
    {
        $importIndicatorObservations->handle($this->indicatorObservationQueryData);
    }
}
```

## Dispatching

Jobs must always be dispatched after commit so queued work does not run before database state is durable.

Use Laravel's after-commit dispatching APIs or job configuration whenever dispatching from code that may run inside a transaction.

## Checklist

- Job contains no business logic.
- Job delegates business operations to actions.
- Job dependencies are injected through `handle()` or the constructor.
- Job dispatch is configured to run after commit.
