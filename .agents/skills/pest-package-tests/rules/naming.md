# Naming Rules

## Class Names

- Test files use the `Test` suffix and mirror the source class name (`CreateWaitlistSignup` -> `CreateWaitlistSignupTest`).
- Actions do not use the `Action` suffix. Action names are verb-first and put the subject after the verb.
- Queries use the `Query` suffix and describe the read operation, not just the aggregate.
- Services use the `Service` suffix.
- Data objects use the `Data` suffix (`CreateWaitlistSignupData`).
- Value objects use a domain noun (`EmailAddress`, `Money`) and do not use `VO` or `ValueObject` suffixes.
- Factories use the `Factory` suffix and live under `tests/Factories/` (`CreateWaitlistSignupDataFactory`).

Query names should describe the read operation, not just the aggregate. Prefer `ListIndicatorsQuery`, `FindUserByEmailQuery` over broad names like `UserQuery`.

Action names must describe what the action does using a verb-first name. Put the subject or subjects after the verb.

- Orchestrator example: `RegisterOrganization`.
- Single-responsibility example: `CreateOrganization`.
- Single-responsibility example: `AttachUserToOrganization`.
- Avoid noun-only names like `OrganizationRegistration`.
- Avoid vague verbs like `HandleOrganization` or `ProcessUser`.

## Variables And Properties

Variable and property names must match the object or returned type they hold. This is not a preference — it is the rule.

- A variable holding a `WaitlistSignup` is `$waitlistSignup`.
- A variable holding a `CreateWaitlistSignupData` is `$createWaitlistSignupData`.
- A property holding a `CreateWaitlistSignup` action is `$this->createWaitlistSignup`.
- A variable holding a `IndicatorDiscoveryResult` is `$indicatorDiscoveryResult`.

The lowerCamelCase of the class is the default. Drop a leading article only when the type name is awkward to read (a `URL` is `$url`, a `HTTPClient` is `$httpClient`).

For setup, the property mirrors the collaborator class. For results, the variable mirrors the returned object — never the call site. `$this->createWaitlistSignup->handle($data)` returns a `WaitlistSignup`, so the variable is `$waitlistSignup`, not `$result` or `$signup` or `$response`.

- Good: `$this->createWaitlistSignup = new CreateWaitlistSignup();`
- Good: `$waitlistSignup = $this->createWaitlistSignup->handle($createWaitlistSignupData);`
- Avoid: `$service`, `$result`, `$resolved`, `$action`, `$model`, `$response`, `$data` when the concrete type is known.

## Examples

```php
beforeEach(function (): void {
    $this->createWaitlistSignup = new CreateWaitlistSignup();
});

$createWaitlistSignupData = CreateWaitlistSignupData::factory()->make();
$waitlistSignup = $this->createWaitlistSignup->handle($createWaitlistSignupData);
```

Prefer domain-specific names over generic names:

- Use `$waitlistSignup`, not `$model`.
- Use `$indicatorDiscoveryResult`, not `$result`.
- Use `$indicatorDataSource`, not `$service`.
- Use `$createWaitlistSignupData`, not `$data` or `$payload`.
