# Laravel Package Make Resource Command

Status: Laravel package port planning spec

Use this as the implementation contract for
`php artisan iak:make-resource <resource> --json` in the
`fbarrento/inertia-agent-kit` Laravel package.

## Purpose

`iak:make-resource` ports the prototype resource scaffold into the Laravel
package surface. It creates thin Inertia page adapters plus a resource-local
feature folder. It must not create global behavior buckets or handwritten
backend DTO copies.

This command replaces the prototype `iak new resource` surface for the Laravel
package. The public command name is `iak:make-resource`, not
`iak:new-resource`.

## Command Contract

Canonical call:

```bash
php artisan iak:make-resource vehicles --json
```

Signature:

```txt
iak:make-resource
  {resource : Plural kebab-case resource route name, for example vehicles}
  {--adapter=react : Renderer adapter. First port supports react only}
  {--controller= : Fully qualified Laravel controller class}
  {--route-name= : Laravel resource route name}
  {--singular= : Singular kebab-case resource name when inference is ambiguous}
  {--only= : Comma-separated page actions: index,show,create,edit}
  {--except= : Comma-separated page actions to omit}
  {--dry-run : Return the file plan without writing}
  {--force : Overwrite only files known to be generated or scaffold-owned}
  {--allow-missing-generated-types : Write imports for expected backend contracts}
  {--json : Emit exactly one JSON object on stdout}
```

Behavior:

- The command writes files by default, matching Laravel `make:*` expectations.
- `--dry-run` validates and returns the same plan without writing files.
- `--json` and `IAK_AGENT=1` suppress prose, tables, prompts, spinners, and
  ANSI output on stdout.
- Missing required input in JSON mode returns structured JSON and exits `2`.
- Existing user-owned files are `conflict` unless `--force` is safe by scaffold
  metadata or generated-file markers.
- All paths in JSON output are project-relative POSIX paths.

## Naming Rules

Resource input is the plural route resource name.

| Input | Plural folder | Singular name | Studly singular | Default controller |
| --- | --- | --- | --- | --- |
| `vehicles` | `vehicles` | `vehicle` | `Vehicle` | `App\Http\Controllers\VehicleController` |
| `organization-settings` | `organization-settings` | `organization-setting` | `OrganizationSetting` | `App\Http\Controllers\OrganizationSettingController` |

Rules:

- Normalize each resource segment to kebab-case.
- Infer singular names with Laravel `Str::singular`; require `--singular` when
  inference is ambiguous, such as `statuses`.
- File and folder names are kebab-case.
- Component, type, and class-like symbols are PascalCase from the singular or
  plural resource name.
- `--route-name` defaults to the plural resource name.
- `--controller` defaults to `App\Http\Controllers\<StudlySingular>Controller`.
- Dot-nested resources use route dots and page path segments:
  `organizations.vehicles` maps to route names such as
  `organizations.vehicles.index` and pages under
  `resources/js/pages/organizations/vehicles`.

## Resource Controller Mapping

The command mirrors Laravel resource controller page actions.

| Laravel action | Route name | Inertia page | Generated |
| --- | --- | --- | --- |
| `VehicleController@index` | `vehicles.index` | `resources/js/pages/vehicles/index.tsx` | yes |
| `VehicleController@show` | `vehicles.show` | `resources/js/pages/vehicles/show.tsx` | yes |
| `VehicleController@create` | `vehicles.create` | `resources/js/pages/vehicles/create.tsx` | yes |
| `VehicleController@store` | `vehicles.store` | none | no |
| `VehicleController@edit` | `vehicles.edit` | `resources/js/pages/vehicles/edit.tsx` | yes |
| `VehicleController@update` | `vehicles.update` | none | no |
| `VehicleController@destroy` | `vehicles.destroy` | none | no |

`OrganizationSettingsController` with route name `organization-settings` maps
to:

```txt
resources/js/pages/organization-settings/index.tsx
resources/js/pages/organization-settings/show.tsx
resources/js/pages/organization-settings/create.tsx
resources/js/pages/organization-settings/edit.tsx
```

Rules:

- `--only` and `--except` apply only to `index`, `show`, `create`, and `edit`.
- Mutating actions use generated Wayfinder imports. They never become page
  files.
- Pages stay thin route adapters: receive typed Inertia props, select layout,
  compose feature components, and avoid domain behavior.

## Generated File Layout

Default React output for `php artisan iak:make-resource vehicles --json`:

```txt
resources/js/
  pages/
    vehicles/
      index.tsx
      show.tsx
      create.tsx
      edit.tsx
  features/
    vehicles/
      vehicle.types.ts
      vehicle.fixtures.ts
      vehicle-table.tsx
      vehicle-table.stories.tsx
      vehicle-filters.tsx
      vehicle-form.tsx
      vehicle-form.stories.tsx
      vehicle-empty-state.tsx
```

Generated responsibilities:

- `pages/<resource>/<action>.tsx`: route adapter for one Inertia page.
- `features/<resource>/<singular>.types.ts`: aliases that compose generated
  backend contracts.
- `features/<resource>/<singular>.fixtures.ts`: typed fixtures for stories and
  tests.
- `features/<resource>/<singular>-table.tsx`: list UI for index states.
- `features/<resource>/<singular>-filters.tsx`: resource-local filter UI.
- `features/<resource>/<singular>-form.tsx`: create/edit form UI using
  generated form and Wayfinder contracts.
- `features/<resource>/<singular>-empty-state.tsx`: resource-local empty
  state.
- `*.stories.tsx`: colocated Storybook contracts for table and form.

The command must not generate these folders:

```txt
resources/js/queries/
resources/js/actions/
resources/js/forms/
resources/js/hooks/
resources/js/composables/
```

`resources/js/actions/generated` and `resources/js/routes/generated` are
allowed only for generator-owned Wayfinder output.

## Type Imports

Feature types compose generated Laravel/PHP contracts. They do not copy DTO,
enum, form, filter, validation, route, or controller action shapes.

Default `vehicle.types.ts` imports:

```ts
import type { App } from '@/types/generated'

export type VehicleIndexPageProps =
  App.Data.Vehicles.VehicleIndexPageData
export type VehicleShowPageProps =
  App.Data.Vehicles.VehicleShowPageData
export type VehicleCreatePageProps =
  App.Data.Vehicles.VehicleCreatePageData
export type VehicleEditPageProps =
  App.Data.Vehicles.VehicleEditPageData

export type VehicleListItem =
  App.Data.Vehicles.VehicleListItemData
export type VehicleFormValues =
  App.Data.Vehicles.VehicleFormData
export type VehicleFilters =
  App.Data.Vehicles.VehicleFiltersData
```

Default Wayfinder imports for form-capable features:

```ts
import { store, update, destroy } from '@/actions/generated/App/Http/Controllers/VehicleController'
import { index, show, create, edit } from '@/routes/generated/vehicles'
```

Rules:

- Page files import page prop aliases from the feature type file.
- Feature components import aliases from their local `<singular>.types.ts`.
- Fixtures use `satisfies` with generated or feature-owned aliases.
- If generated symbols are missing, strict mode fails before writing. With
  `--allow-missing-generated-types`, the command may write expected imports and
  aliases, but never fallback frontend copies.
- Production templates render backend-provided display strings, copy,
  validation text, enum labels, dates, and money. They do not introduce local
  formatting or translation maps.

## Design-System Rules

Generated templates use semantic design-system utilities only for styling
decisions, such as:

```txt
bg-ds-surface
text-ds-default
text-ds-muted
border-ds-border-default
p-ds-inset-md
gap-ds-stack-sm
rounded-ds-control
```

Templates must not include raw hex values, primitive color utilities,
arbitrary spacing/radius values, inline color styles, or direct primitive CSS
variables.

## JSON Output

`--json` emits one `iak.scaffold-plan.v1` object for dry-run and write modes.

Example:

```json
{
  "schema": "iak.scaffold-plan.v1",
  "command": "php artisan iak:make-resource vehicles",
  "status": "completed",
  "mode": "write",
  "adapter": "react",
  "resource": {
    "name": "vehicles",
    "singular": "vehicle",
    "folder": "vehicles",
    "routeName": "vehicles",
    "controller": "App\\Http\\Controllers\\VehicleController"
  },
  "controllerMap": [
    {
      "controllerAction": "VehicleController@index",
      "route": "vehicles.index",
      "page": "resources/js/pages/vehicles/index.tsx"
    },
    {
      "controllerAction": "VehicleController@show",
      "route": "vehicles.show",
      "page": "resources/js/pages/vehicles/show.tsx"
    },
    {
      "controllerAction": "VehicleController@create",
      "route": "vehicles.create",
      "page": "resources/js/pages/vehicles/create.tsx"
    },
    {
      "controllerAction": "VehicleController@edit",
      "route": "vehicles.edit",
      "page": "resources/js/pages/vehicles/edit.tsx"
    }
  ],
  "files": [
    {
      "path": "resources/js/pages/vehicles/index.tsx",
      "role": "page",
      "action": "create"
    },
    {
      "path": "resources/js/features/vehicles/vehicle.types.ts",
      "role": "feature-types",
      "action": "create"
    }
  ],
  "generatedTypeImports": [
    {
      "from": "@/types/generated",
      "symbols": ["App"],
      "usedBy": "resources/js/features/vehicles/vehicle.types.ts"
    }
  ],
  "wayfinderImports": [
    {
      "from": "@/actions/generated/App/Http/Controllers/VehicleController",
      "symbols": ["store", "update", "destroy"]
    },
    {
      "from": "@/routes/generated/vehicles",
      "symbols": ["index", "show", "create", "edit"]
    }
  ],
  "stories": [
    {
      "component": "VehicleTable",
      "path": "resources/js/features/vehicles/vehicle-table.stories.tsx",
      "fixture": "resources/js/features/vehicles/vehicle.fixtures.ts",
      "states": ["Default", "Empty", "Loading", "Error"]
    },
    {
      "component": "VehicleForm",
      "path": "resources/js/features/vehicles/vehicle-form.stories.tsx",
      "fixture": "resources/js/features/vehicles/vehicle.fixtures.ts",
      "states": ["Default", "WithValidationErrors", "Disabled"]
    }
  ],
  "writtenFiles": [
    "resources/js/pages/vehicles/index.tsx"
  ],
  "skippedFiles": [],
  "conflicts": [],
  "warnings": [],
  "errors": []
}
```

Status values:

- `planned`: dry-run succeeded.
- `completed`: files were written or safely skipped.
- `failed`: validation, missing generated contracts, or file conflicts blocked
  the command.

## Package-Owned Stubs

The Laravel package should own PHP-side stub resources under
`resources/stubs/react/`:

```txt
resources/stubs/react/
  pages/
    index.tsx.stub
    show.tsx.stub
    create.tsx.stub
    edit.tsx.stub
  features/
    types.ts.stub
    fixtures.ts.stub
    table.tsx.stub
    table.stories.tsx.stub
    filters.tsx.stub
    form.tsx.stub
    form.stories.tsx.stub
    empty-state.tsx.stub
```

Stub rendering inputs should include resolved paths, route name, controller
class, controller basename, resource names, generated type symbols, generated
route/action import paths, selected page actions, and adapter metadata.

Stubs should stay small and renderer-specific. Shared planning, naming,
collision detection, JSON serialization, and file writing belong in PHP
services under `src/Scaffolding`, not inside stub templates.

## Acceptance Tests

Use Pest with Orchestra Testbench. Tests should run inside a fixture Laravel
app root and assert files plus JSON, not only console text.

Required cases:

- `it registers the iak:make-resource command`.
- `it scaffolds vehicles resource pages and feature files as react`.
- `it emits one valid json object with --json`.
- `it supports --dry-run without writing files`.
- `it maps OrganizationSettingsController to organization-settings pages`.
- `it respects --only and --except for page actions`.
- `it rejects mutating actions in --only`.
- `it requires --singular for ambiguous plural inference`.
- `it imports generated App types from the configured generated path`.
- `it includes Wayfinder route and action import metadata`.
- `it does not create top-level queries actions forms hooks or composables`.
- `it creates table and form stories with typed fixture references`.
- `it uses semantic ds utilities and no raw hex or primitive color utilities in
  stubs`.
- `it fails on existing user-owned conflicts without --force`.
- `it allows --allow-missing-generated-types without writing fallback DTOs`.

Fixture expectations:

- Fixture app contains `resources/js`, generated type directories, generated
  Wayfinder directories, and optional existing user-owned files for conflict
  tests.
- Generated files are compared against stable snapshots or normalized strings.
- JSON assertions validate `schema`, `status`, `resource`, `controllerMap`,
  `files`, `generatedTypeImports`, `wayfinderImports`, `stories`, `warnings`,
  `conflicts`, and `errors`.
- Tests must assert forbidden folders do not exist after a successful scaffold.

## Non-Goals

- Do not implement Vue or Svelte templates in the first Laravel package port.
- Do not remove adapter shape from the command; keep `--adapter=react` so Vue
  and Svelte can be added later without changing the public contract.
- Do not build Spatie Data or Wayfinder generators in this command.
- Do not create backend controller, model, request, policy, migration, route,
  or Data classes.
- Do not create default top-level `queries`, `actions`, `forms`, `hooks`, or
  `composables` folders.
- Do not scaffold page stories by default.
- Do not implement MCP behavior in this wave.
