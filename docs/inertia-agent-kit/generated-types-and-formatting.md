# Generated Types And Backend-Owned Formatting

Status: Phase 1 convention spec.

Use this as the source of truth for Inertia Agent Kit's generated type
strategy and backend-owned display text, formatting, and translation rules.

## Core Rule

IAK does not trust agents to invent backend-derived TypeScript types.

Laravel is the source of truth for page props, DTOs, enums, validation shapes,
route/action contracts, translated copy, and locale-sensitive formatting. The
frontend imports generated contracts, composes UI, handles interaction, and
renders display-ready values.

Default v1 stack:

- **Spatie Laravel Data** for DTOs, page props, form data, filters, shared
  display values, and copy payloads.
- **spatie/laravel-typescript-transformer** for generated TypeScript from PHP
  Data classes, enums, and value objects.
- **Wayfinder** for typed Laravel routes and controller actions.
- **IAK audit** for drift detection when frontend files duplicate backend
  contracts or formatting decisions.

## PHP Data Layout

IAK expects app-specific Data classes to live under `app/Data` and mirror the
resource language used by Laravel routes, controllers, policies, requests, and
Inertia pages.

```txt
app/Data/
  Shared/
    ActionData.php
    DateTimeDisplayData.php
    EnumOptionData.php
    MoneyDisplayData.php
    PageMetaData.php
    SelectOptionData.php
  Vehicles/
    VehicleData.php
    VehicleListItemData.php
    VehicleIndexPageData.php
    VehicleShowPageData.php
    VehicleCreatePageData.php
    VehicleEditPageData.php
    VehicleFormData.php
    VehicleFiltersData.php
    VehicleIndexCopyData.php
    VehicleShowCopyData.php
    VehicleCapabilitiesData.php
```

Layout rules:

- Page props use one top-level `*PageData` class per Inertia page.
- Resource display rows, cards, summaries, and details use named Data classes,
  not anonymous arrays.
- Forms use `*FormData` for initial values and generated frontend form shape.
- Filters use `*FiltersData` for parsed filter state and allowed values.
- Page copy uses page-scoped `*CopyData` classes instead of frontend
  dictionaries.
- Capability and permission state uses `*CapabilitiesData` or a clearly named
  `can` object of booleans.
- Shared display primitives live in `app/Data/Shared` only when they are
  resource-neutral.

Example page Data:

```php
<?php

namespace App\Data\Vehicles;

use App\Data\Shared\PageMetaData;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class VehicleIndexPageData extends Data
{
    /**
     * @param DataCollection<int, VehicleListItemData> $vehicles
     */
    public function __construct(
        public DataCollection $vehicles,
        public VehicleFiltersData $filters,
        public VehicleIndexCopyData $copy,
        public VehicleCapabilitiesData $can,
        public PageMetaData $meta,
    ) {
    }
}
```

Example display DTO:

```php
<?php

namespace App\Data\Vehicles;

use App\Data\Shared\DateTimeDisplayData;
use App\Data\Shared\MoneyDisplayData;
use App\Data\Shared\EnumOptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class VehicleListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public EnumOptionData $status,
        public MoneyDisplayData $price,
        public DateTimeDisplayData $createdAt,
    ) {
    }
}
```

Controllers should pass Data objects to Inertia instead of handwritten prop
arrays:

```php
return Inertia::render('Vehicles/Index', VehicleIndexPageData::from([
    'vehicles' => VehicleListItemData::collect($vehicles),
    'filters' => VehicleFiltersData::fromRequest($request),
    'copy' => VehicleIndexCopyData::fromLocale(),
    'can' => VehicleCapabilitiesData::forUser($request->user()),
    'meta' => PageMetaData::forPaginator($vehicles),
]));
```

## Generated TypeScript Output

Generated files are owned by generators. Agents and application code must not
edit them manually.

IAK v1 reserves these output locations:

```txt
resources/js/types/generated/
  data.d.ts
  enums.d.ts
  page-props.d.ts
  index.d.ts

resources/js/actions/generated/
  **/*.ts

resources/js/routes/generated/
  **/*.ts
```

Output ownership:

- `resources/js/types/generated/data.d.ts` contains transformed Spatie Data
  classes and shared value objects.
- `resources/js/types/generated/enums.d.ts` contains transformed PHP enum value
  unions and enum-backed option types when configured separately.
- `resources/js/types/generated/page-props.d.ts` may contain IAK aliases for
  page prop contracts when the project wants stable import names.
- `resources/js/types/generated/index.d.ts` is a generated barrel only.
- `resources/js/actions/generated/**` is Wayfinder-owned controller/action
  output.
- `resources/js/routes/generated/**` is Wayfinder-owned route output.

IAK may adapt exact Wayfinder file names to the installed Wayfinder version,
but the generated route/action output must remain under generated locations or
be re-exported from generated barrels. User-owned wrappers can import
Wayfinder helpers, but they must not recreate route strings or controller
method signatures by hand.

Every generated file should include a header similar to:

```txt
// Generated by Inertia Agent Kit. Do not edit manually.
```

The audit may also store source hashes or generator metadata so CI can detect
stale output.

## Frontend Type Ownership

Frontend code has three type ownership zones.

### Generated Backend Types

Generated backend types are imported from `@/types/generated` and represent
Laravel-owned truth:

```ts
import type { App } from '@/types/generated'

export type VehicleIndexPageProps =
  App.Data.Vehicles.VehicleIndexPageData
```

These include:

- Inertia page props.
- Resource DTOs.
- Form payloads and initial form values.
- Filter state and allowed filter options.
- PHP enum values and enum option data.
- Validation-related field names when generated.
- Permission and capability payloads.
- Display/value DTOs for dates, money, status labels, copy, and metadata.

### Feature-Owned Frontend Types

Feature-only types live near the feature:

```txt
resources/js/features/vehicles/vehicles.types.ts
```

Allowed feature types:

- Type aliases that compose generated types.
- UI state that exists only in the browser.
- Component prop types that reference generated DTOs.
- View model types that add frontend-only state around generated DTOs.

Example:

```ts
import type { App } from '@/types/generated'

export type VehicleListItem = App.Data.Vehicles.VehicleListItemData

export type VehicleSelectionState = {
  selectedIds: number[]
  lastSelectedId: number | null
}
```

Disallowed feature types:

- Handwritten copies of Data classes.
- `type VehicleStatus = 'active' | 'inactive'` when the status comes from PHP.
- Local enum label maps.
- Local form value types that duplicate `*FormData`.
- Local page prop interfaces with backend fields.

### Shared Frontend Types

Shared frontend-only types live in:

```txt
resources/js/types/shared/
```

Shared types must stay generic. They may describe UI concepts such as
`SortDirection`, `Density`, or `TableColumnState`, but not resource-specific
backend domains such as `VehicleStatus`, `InvoiceState`, or
`CustomerFormValues`.

Pages should not define domain types inline. A page may import a generated
`*PageData` type directly or a feature alias named `*PageProps`, but it should
not declare the backend prop shape itself.

## DTO Display And Value Patterns

Backend DTOs should include both machine values and display values when the UI
needs both.

Use raw values for machine behavior:

- IDs, keys, and route parameters.
- Comparisons and branching.
- Form submission payloads.
- Sorting and filtering payloads.
- HTML input values.

Use display values for human-facing UI:

- Labels.
- Formatted numbers and money.
- Formatted dates and times.
- Relative dates when provided.
- Empty-state copy.
- Validation messages.
- Button and action text.
- Enum option labels.

Recommended DTO shapes:

```json
{
  "id": 123,
  "name": "Ford Transit",
  "status": {
    "value": "active",
    "label": "Active"
  },
  "price": {
    "amount": 129900,
    "currency": "EUR",
    "formatted": "EUR 1,299.00"
  },
  "createdAt": {
    "iso": "2026-05-22T12:00:00Z",
    "formatted": "22 May 2026",
    "relative": "2 hours ago"
  }
}
```

Enum options:

```json
{
  "statusOptions": [
    {
      "value": "active",
      "label": "Active"
    },
    {
      "value": "maintenance",
      "label": "In maintenance",
      "disabled": false
    }
  ]
}
```

Page copy:

```json
{
  "copy": {
    "title": "Vehicles",
    "createButton": "Add vehicle",
    "emptyTitle": "No vehicles yet",
    "emptyDescription": "Create the first vehicle to start tracking your fleet."
  }
}
```

Capabilities:

```json
{
  "can": {
    "createVehicle": true,
    "exportVehicles": false
  }
}
```

Validation:

```json
{
  "fields": {
    "registrationNumber": {
      "label": "Registration number",
      "placeholder": "AA-00-AA",
      "help": "Use the registration number on the vehicle document."
    }
  }
}
```

The frontend may use the field name as a form key, but labels, help text,
placeholders, and validation messages come from Laravel.

## Backend-Owned Formatting And Translation

The backend owns user-facing data formatting and translation.

Laravel sources of truth:

- Lang files for page copy, labels, validation messages, and empty states.
- PHP enums or value objects for values, labels, options, and disabled states.
- Spatie Laravel Data for serialized display/value DTOs.
- Form Requests and validators for validation rules and messages.
- Policies and gates for capability booleans.
- Wayfinder for typed routes and controller actions.

Disallowed in production page, feature, and app component render paths:

```txt
new Intl.NumberFormat(...)
new Intl.DateTimeFormat(...)
date-fns/dayjs/luxon formatting for user-facing text
hard-coded enum label maps
hard-coded validation messages
frontend translation key construction
frontend i18n dictionaries as the default app architecture
route string construction for Laravel routes
controller action signatures recreated in TypeScript
```

Allowed frontend formatting:

- Rendering backend-provided display strings.
- Passing raw values through form controls where the browser requires a machine
  format, such as `yyyy-mm-dd` for `input[type="date"]`.
- Comparing raw values for UI state, conditional rendering, sorting requests,
  filtering requests, and route parameters.
- Visual-only CSS transformation such as truncation, wrapping, or responsive
  hiding when it does not change the source text.
- Generic accessibility fallback text inside primitives when no domain copy is
  available.
- Story and test fixture copy that is clearly not production page copy.
- Developer-only debug output.

Any exception that formats user-facing production text in the frontend must be
explicitly allowlisted with an owner, reason, and expiry. The default answer is
to add the formatted value or translated copy to the backend Data class.

## Route And Action Ownership

Wayfinder is the default source for Laravel route and controller action typing.

Frontend code should import generated Wayfinder helpers for Inertia links,
router visits, forms, and actions. It should not handwrite backend route names,
URL templates, controller method strings, or HTTP method contracts unless an
IAK exception says the route cannot be represented by Wayfinder.

Allowed:

```ts
import { store } from '@/actions/generated/App/Http/Controllers/VehicleController'
import { index } from '@/routes/generated/vehicles'
```

Disallowed:

```ts
router.post('/vehicles')
route('vehicles.store')
const vehicleShowUrl = `/vehicles/${vehicle.id}`
```

If a project keeps a compatibility layer such as Ziggy, IAK should still prefer
Wayfinder for new v1 code and audit handwritten route strings in agent-owned
changes.

## Audit Rules

`iak audit --json` should report type and formatting drift with stable rule IDs
that agents can self-heal from.

Type drift failures:

- `iak/types/no-inline-page-props`: page files declare backend page prop
  interfaces inline instead of importing generated `*PageData` types.
- `iak/types/no-handwritten-data-copy`: frontend types duplicate generated Data
  classes, form payloads, filters, models, resources, or enum unions.
- `iak/types/no-local-domain-enum`: resource/domain enum unions or label maps
  are declared in frontend-owned files.
- `iak/types/no-any-page-props`: Inertia page props use `any`, `unknown`, or
  broad records instead of generated types.
- `iak/types/no-generated-edits`: generated files are manually modified.
- `iak/types/no-stale-generated-output`: PHP Data, enum, or route source has
  changed without regenerated TypeScript and Wayfinder output.
- `iak/types/no-feature-to-page-type-import`: feature code imports types from
  page files instead of generated or feature-owned type files.
- `iak/types/no-resource-type-in-shared`: `types/shared` contains
  resource-specific backend domain names.

Formatting and translation drift failures:

- `iak/format/no-intl-render-formatting`: `Intl.NumberFormat` or
  `Intl.DateTimeFormat` appears in page, feature, or app component render
  paths without an allowlist.
- `iak/format/no-date-library-render-formatting`: date formatting libraries are
  used for production user-facing text in frontend render paths.
- `iak/format/no-local-label-map`: frontend files define enum label maps or
  derive labels from raw enum values.
- `iak/format/no-hardcoded-validation-message`: frontend files define
  validation messages that belong to Laravel.
- `iak/format/no-frontend-translation-dictionary`: frontend i18n dictionaries
  are used when backend-owned translation mode is enabled.
- `iak/format/no-route-string-construction`: Laravel route URLs or controller
  actions are recreated by hand instead of using Wayfinder output.
- `iak/format/no-copy-key-construction`: frontend code constructs translation
  keys or copy keys from backend values.

Audit output should include:

- File path and line number when available.
- The generated type, Data class, enum, route, or DTO that should be used.
- A short remediation hint.
- Whether the failure can be auto-fixed.
- The allowlist entry that suppressed a finding, when applicable.

## Required Checks

Before handoff, an agent touching backend-derived types or display DTOs should
run the configured project commands for:

- Spatie TypeScript transformation.
- Wayfinder generation.
- Type checking.
- `iak audit --json`.

For this repository phase, this file defines the contract only. Implementation
commands can be wired once the Laravel package and installer exist.
