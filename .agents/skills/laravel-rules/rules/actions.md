# Action Rules

## Purpose

Actions encapsulate all business logic and state mutation workflows. Controllers, console commands, and jobs may depend on actions, but they must not implement business logic themselves.

Every action exposes a `handle()` method:

```php
final class CreateOrganization
{
    public function handle(CreateOrganizationData $createOrganizationData): Organization
    {
        // ...
    }
}
```

Name actions with a verb first, then the affected subject or subjects. Use names like `RegisterOrganization`, `CreateOrganization`, and `AttachUserToOrganization`.

## Inputs

Avoid passing Eloquent models directly into actions. Pass purpose-built data objects instead so the action receives an explicit command-shaped input.

- Good: `CreateWaitlistSignupData $createWaitlistSignupData`
- Avoid: `User $user` when a data object can describe the operation.

Use names that describe the input value, not generic names like `$data` when a precise data object name exists.

## Action Types

Use two action types:

- Single-responsibility actions perform one focused operation.
- Orchestrator actions coordinate a workflow and usually inject other actions.

If a single-responsibility action performs only one database write, it does not need a database transaction.

If an orchestrator action performs more than one database write, wrap the workflow in a database transaction.

## Dependencies

Actions may be dependencies of:

- Controllers.
- Console commands.
- Jobs.
- Other orchestrator actions.

Services must not contain internal business logic. Use services only for external systems; actions may depend on services when they need to call third-party APIs or remote systems.

Controllers, console commands, and jobs should validate or prepare input, call an action, then return/output/dispatch. They must not enforce domain workflows directly.

## Domain Invariants

Actions are responsible for enforcing domain invariants. When an invariant fails, throw a specific business exception instead of a generic exception.

- Good: `DuplicateWaitlistSignupException`
- Good: `IndicatorImportAlreadyRunningException`
- Avoid: `Exception`, `RuntimeException`, or returning `false` for domain failures.

## Checklist

- Action has a `handle()` method.
- Action input is a data object where operation input is needed.
- Single-write actions avoid unnecessary database transactions.
- Orchestrator actions use transactions when they perform multiple writes.
- Controllers, commands, and jobs delegate business logic to actions.
- Domain invariants are enforced inside actions.
- Domain failures throw specific business exceptions.
