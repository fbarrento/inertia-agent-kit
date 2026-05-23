# Worked Example

A walkthrough that ties every rule together for one feature: a `CreateWaitlistSignup` action that accepts a `CreateWaitlistSignupData` and persists a `WaitlistSignup` through an injected `WaitlistSignupRepository` port.

## 1. The Data Object

`src/Data/CreateWaitlistSignupData.php`:

```php
namespace Vendor\Package\Data;

use FBarrento\DataFactory\HasDataFactory;

final readonly class CreateWaitlistSignupData
{
    use HasDataFactory;

    public function __construct(
        public string $email,
        public string $source,
        public ?string $invitedBy = null,
    ) {}
}
```

## 2. The Factory

`tests/Factories/CreateWaitlistSignupDataFactory.php`:

```php
namespace Tests\Factories;

use FBarrento\DataFactory\Factory;
use Vendor\Package\Data\CreateWaitlistSignupData;

class CreateWaitlistSignupDataFactory extends Factory
{
    protected string $class = CreateWaitlistSignupData::class;

    protected function definition(): array
    {
        return [
            'email' => $this->fake->safeEmail(),
            'source' => 'organic',
            'invitedBy' => null,
        ];
    }

    public function invited(): static
    {
        return $this->state(['source' => 'invite']);
    }
}
```

## 3. The Port (Service Interface)

`src/Services/WaitlistSignupRepository.php`:

```php
namespace Vendor\Package\Services;

use Vendor\Package\Data\CreateWaitlistSignupData;
use Vendor\Package\WaitlistSignup;

interface WaitlistSignupRepository
{
    public function save(CreateWaitlistSignupData $createWaitlistSignupData): WaitlistSignup;
}
```

## 4. The In-Memory Adapter

`tests/Utils/Services/InMemoryWaitlistSignupRepository.php`:

```php
namespace Tests\Utils\Services;

use Ramsey\Uuid\Uuid;
use Vendor\Package\Data\CreateWaitlistSignupData;
use Vendor\Package\Services\WaitlistSignupRepository;
use Vendor\Package\WaitlistSignup;

final class InMemoryWaitlistSignupRepository implements WaitlistSignupRepository
{
    /** @var array<string, WaitlistSignup> */
    public array $saved = [];

    public function save(CreateWaitlistSignupData $createWaitlistSignupData): WaitlistSignup
    {
        $waitlistSignup = new WaitlistSignup(
            id: Uuid::uuid4()->toString(),
            email: $createWaitlistSignupData->email,
            source: $createWaitlistSignupData->source,
            createdAt: new \DateTimeImmutable(),
        );

        $this->saved[$waitlistSignup->id] = $waitlistSignup;

        return $waitlistSignup;
    }
}
```

## 5. The Action

`src/Actions/CreateWaitlistSignup.php`:

```php
namespace Vendor\Package\Actions;

use Vendor\Package\Data\CreateWaitlistSignupData;
use Vendor\Package\Services\WaitlistSignupRepository;
use Vendor\Package\WaitlistSignup;

final readonly class CreateWaitlistSignup
{
    public function __construct(
        private WaitlistSignupRepository $waitlistSignupRepository,
    ) {}

    public function handle(CreateWaitlistSignupData $createWaitlistSignupData): WaitlistSignup
    {
        return $this->waitlistSignupRepository->save($createWaitlistSignupData);
    }
}
```

## 6. The Test

`tests/Unit/Actions/CreateWaitlistSignupTest.php`:

```php
use Tests\Utils\Services\InMemoryWaitlistSignupRepository;
use Vendor\Package\Actions\CreateWaitlistSignup;
use Vendor\Package\Data\CreateWaitlistSignupData;

beforeEach(function (): void {
    $this->waitlistSignupRepository = new InMemoryWaitlistSignupRepository();
    $this->createWaitlistSignup = new CreateWaitlistSignup($this->waitlistSignupRepository);
});

test('creates a waitlist signup from data',
    /**
     * @throws Throwable
     */
    function (): void {
        $createWaitlistSignupData = CreateWaitlistSignupData::factory()->make();

        $waitlistSignup = $this->createWaitlistSignup->handle($createWaitlistSignupData);

        expect($waitlistSignup->id)->toBeString()
            ->and($waitlistSignup->email)->toBe($createWaitlistSignupData->email)
            ->and($waitlistSignup->source)->toBe('organic')
            ->and($this->waitlistSignupRepository->saved)->toHaveCount(1);
    });

test('records an invited signup with the invited state',
    /**
     * @throws Throwable
     */
    function (): void {
        $createWaitlistSignupData = CreateWaitlistSignupData::factory()->invited()->make();

        $waitlistSignup = $this->createWaitlistSignup->handle($createWaitlistSignupData);

        expect($waitlistSignup->source)->toBe('invite');
    });
```

## What This Example Demonstrates

- **Testing:** `test()` not `it()`, typed `@throws Throwable`, `beforeEach()` setup with `new`, `->and()` chaining.
- **Naming:** every variable matches its type — `$createWaitlistSignupData`, `$waitlistSignup`, `$waitlistSignupRepository`. No `$data`, `$result`, `$repo`.
- **Architecture:** CQRS (action mutates), constructor injection, port (`WaitlistSignupRepository`) with an in-memory adapter in `tests/Utils/`.
- **Data factory:** `HasDataFactory` trait, factory in `tests/Factories/`, named `invited()` state instead of inline `state([...])`, `->make()` by default.
