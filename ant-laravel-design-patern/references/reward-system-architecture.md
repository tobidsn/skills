Canonical reference for **ant-laravel-design-patern**: scalable reward-system architecture (patterns, layout, and code sketches).

# Scalable Reward System Architecture for Laravel

## Design Pattern Overview

This architecture combines multiple design patterns to create a scalable, maintainable reward system:

### 1. **Strategy Pattern**
- Different reward types implement common interface
- Eliminates conditional explosion
- Easy to add new reward types

### 2. **Factory Pattern**
- Creates appropriate reward handlers based on type
- Centralizes object creation logic

### 3. **Chain of Responsibility**
- Processes multiple rewards in sequence
- Each handler can pass to the next

### 4. **Action Pattern (Laravel)**
- Single responsibility actions for specific operations
- Testable and reusable

### 5. **Event-Driven Architecture**
- Decouples reward processing from side effects
- Easy to add notifications, logging, etc.

## Folder Structure

```
app/
├── Actions/
│   └── Rewards/
│       ├── ProcessCheckInRewardsAction.php
│       ├── CalculateRewardValueAction.php
│       └── ValidateRewardEligibilityAction.php
├── Services/
│   └── Rewards/
│       ├── RewardService.php
│       ├── RewardProcessorFactory.php
│       └── RewardEligibilityService.php
├── Strategies/
│   └── Rewards/
│       ├── Contracts/
│       │   ├── RewardStrategyInterface.php
│       │   └── RewardProcessorInterface.php
│       └── Processors/
│           ├── EnergyRewardProcessor.php
│           ├── ScoreRewardProcessor.php
│           ├── VoucherRewardProcessor.php
│           ├── ItemRewardProcessor.php
│           ├── MultipleRewardProcessor.php
│           └── PaymentBasedRewardProcessor.php
├── Models/
│   ├── Reward.php
│   ├── UserReward.php
│   ├── RewardTemplate.php
│   └── CheckIn.php
├── Enums/
│   ├── RewardType.php
│   └── RewardStatus.php
├── Events/
│   ├── RewardProcessed.php
│   └── CheckInCompleted.php
├── Listeners/
│   ├── SendRewardNotification.php
│   └── LogRewardActivity.php
└── Repositories/
    ├── RewardRepository.php
    └── UserRewardRepository.php
```

## Core Interfaces & Classes

### 1. Core Contracts

```php
<?php

namespace App\Strategies\Rewards\Contracts;

use App\Models\User;
use App\Models\RewardTemplate;

interface RewardProcessorInterface
{
    public function canProcess(RewardTemplate $template): bool;
    public function process(User $user, RewardTemplate $template, array $context = []): RewardResult;
    public function getPriority(): int;
}

interface RewardStrategyInterface
{
    public function execute(User $user, array $rewards, array $context = []): array;
}
```

### 2. Reward Result Value Object

```php
<?php

namespace App\ValueObjects;

final readonly class RewardResult
{
    public function __construct(
        public bool $success,
        public string $type,
        public mixed $value,
        public ?string $message = null,
        public array $metadata = []
    ) {}

    public static function success(string $type, mixed $value, array $metadata = []): self
    {
        return new self(true, $type, $value, null, $metadata);
    }

    public static function failed(string $type, string $message): self
    {
        return new self(false, $type, null, $message);
    }
}
```

### 3. Reward Types Enum

```php
<?php

namespace App\Enums;

enum RewardType: string
{
    case ENERGY = 'energy';
    case SCORE = 'score';
    case VOUCHER = 'voucher';
    case ITEM = 'item';
    case MULTIPLE = 'multiple';
    case PAYMENT_BASED = 'payment_based';

    public function getProcessorClass(): string
    {
        return match ($this) {
            self::ENERGY => EnergyRewardProcessor::class,
            self::SCORE => ScoreRewardProcessor::class,
            self::VOUCHER => VoucherRewardProcessor::class,
            self::ITEM => ItemRewardProcessor::class,
            self::MULTIPLE => MultipleRewardProcessor::class,
            self::PAYMENT_BASED => PaymentBasedRewardProcessor::class,
        };
    }
}
```

### 4. Models

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Enums\RewardType;

final class RewardTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'type',
        'value',
        'conditions',
        'metadata',
        'is_active',
        'priority'
    ];

    protected $casts = [
        'type' => RewardType::class,
        'conditions' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer'
    ];

    public function userRewards(): HasMany
    {
        return $this->hasMany(UserReward::class, 'template_id');
    }
}

final class UserReward extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'template_id',
        'check_in_id',
        'value',
        'metadata',
        'processed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'processed_at' => 'datetime'
    ];
}
```

### 5. Reward Processors (Strategy Implementations)

```php
<?php

namespace App\Strategies\Rewards\Processors;

use App\Strategies\Rewards\Contracts\RewardProcessorInterface;
use App\Models\User;
use App\Models\RewardTemplate;
use App\ValueObjects\RewardResult;

final class EnergyRewardProcessor implements RewardProcessorInterface
{
    public function canProcess(RewardTemplate $template): bool
    {
        return $template->type === RewardType::ENERGY;
    }

    public function process(User $user, RewardTemplate $template, array $context = []): RewardResult
    {
        $energyAmount = $this->calculateEnergyAmount($template, $context);

        $user->increment('energy', $energyAmount);

        return RewardResult::success(
            type: 'energy',
            value: $energyAmount,
            metadata: ['previous_energy' => $user->energy - $energyAmount]
        );
    }

    public function getPriority(): int
    {
        return 100;
    }

    private function calculateEnergyAmount(RewardTemplate $template, array $context): int
    {
        $baseAmount = $template->value;

        // Apply multipliers based on context (streak, level, etc.)
        $multiplier = $context['energy_multiplier'] ?? 1;

        return (int) ($baseAmount * $multiplier);
    }
}

final class ScoreRewardProcessor implements RewardProcessorInterface
{
    public function canProcess(RewardTemplate $template): bool
    {
        return $template->type === RewardType::SCORE;
    }

    public function process(User $user, RewardTemplate $template, array $context = []): RewardResult
    {
        $scoreAmount = $this->calculateScoreAmount($template, $context);

        $user->increment('score', $scoreAmount);

        return RewardResult::success(
            type: 'score',
            value: $scoreAmount,
            metadata: ['previous_score' => $user->score - $scoreAmount]
        );
    }

    public function getPriority(): int
    {
        return 90;
    }

    private function calculateScoreAmount(RewardTemplate $template, array $context): int
    {
        $baseAmount = $template->value;
        $multiplier = $context['score_multiplier'] ?? 1;

        return (int) ($baseAmount * $multiplier);
    }
}

final class VoucherRewardProcessor implements RewardProcessorInterface
{
    public function __construct(
        private VoucherService $voucherService
    ) {}

    public function canProcess(RewardTemplate $template): bool
    {
        return $template->type === RewardType::VOUCHER;
    }

    public function process(User $user, RewardTemplate $template, array $context = []): RewardResult
    {
        $voucherId = $template->metadata['voucher_id'] ?? null;

        if (!$voucherId) {
            return RewardResult::failed('voucher', 'Voucher ID not specified');
        }

        $voucher = $this->voucherService->assignToUser($user, $voucherId);

        return RewardResult::success(
            type: 'voucher',
            value: $voucher->id,
            metadata: ['voucher_code' => $voucher->code]
        );
    }

    public function getPriority(): int
    {
        return 80;
    }
}

final class MultipleRewardProcessor implements RewardProcessorInterface
{
    public function __construct(
        private RewardProcessorFactory $factory
    ) {}

    public function canProcess(RewardTemplate $template): bool
    {
        return $template->type === RewardType::MULTIPLE;
    }

    public function process(User $user, RewardTemplate $template, array $context = []): RewardResult
    {
        $subRewards = $template->metadata['rewards'] ?? [];
        $results = [];

        foreach ($subRewards as $rewardConfig) {
            $processor = $this->factory->make($rewardConfig['type']);
            $subTemplate = new RewardTemplate($rewardConfig);

            $result = $processor->process($user, $subTemplate, $context);
            $results[] = $result;
        }

        return RewardResult::success(
            type: 'multiple',
            value: $results,
            metadata: ['sub_rewards_count' => count($results)]
        );
    }

    public function getPriority(): int
    {
        return 70;
    }
}
```

### 6. Factory

```php
<?php

namespace App\Services\Rewards;

use App\Strategies\Rewards\Contracts\RewardProcessorInterface;
use App\Enums\RewardType;
use Illuminate\Container\Container;

final class RewardProcessorFactory
{
    public function __construct(
        private Container $container
    ) {}

    public function make(RewardType $type): RewardProcessorInterface
    {
        $processorClass = $type->getProcessorClass();

        return $this->container->make($processorClass);
    }

    public function makeAll(): array
    {
        return collect(RewardType::cases())
            ->map(fn(RewardType $type) => $this->make($type))
            ->toArray();
    }
}
```

### 7. Main Service

```php
<?php

namespace App\Services\Rewards;

use App\Models\User;
use App\Models\CheckIn;
use App\Actions\Rewards\ProcessCheckInRewardsAction;
use App\Events\CheckInCompleted;

final class RewardService
{
    public function __construct(
        private ProcessCheckInRewardsAction $processRewardsAction,
        private RewardEligibilityService $eligibilityService
    ) {}

    public function processCheckInRewards(User $user, CheckIn $checkIn): array
    {
        // Validate eligibility
        if (!$this->eligibilityService->isEligibleForRewards($user, $checkIn)) {
            return [];
        }

        // Get applicable rewards
        $rewards = $this->getApplicableRewards($user, $checkIn);

        if (empty($rewards)) {
            return [];
        }

        // Process rewards
        $results = $this->processRewardsAction->execute($user, $rewards, [
            'check_in' => $checkIn,
            'context' => $this->buildContext($user, $checkIn)
        ]);

        // Fire event for side effects
        event(new CheckInCompleted($user, $checkIn, $results));

        return $results;
    }

    private function getApplicableRewards(User $user, CheckIn $checkIn): array
    {
        return RewardTemplate::where('is_active', true)
            ->where(function ($query) use ($user, $checkIn) {
                // Add conditions based on user level, streak, time, etc.
                $query->whereJsonContains('conditions->user_level', '<=', $user->level)
                      ->orWhereNull('conditions->user_level');
            })
            ->orderBy('priority', 'desc')
            ->get()
            ->toArray();
    }

    private function buildContext(User $user, CheckIn $checkIn): array
    {
        return [
            'user_level' => $user->level,
            'check_in_streak' => $user->check_in_streak,
            'is_weekend' => now()->isWeekend(),
            'is_first_check_in_today' => $this->isFirstCheckInToday($user),
            'energy_multiplier' => $this->calculateEnergyMultiplier($user),
            'score_multiplier' => $this->calculateScoreMultiplier($user),
        ];
    }
}
```

### 8. Main Action

```php
<?php

namespace App\Actions\Rewards;

use App\Models\User;
use App\Services\Rewards\RewardProcessorFactory;
use App\Repositories\UserRewardRepository;
use App\ValueObjects\RewardResult;
use Illuminate\Support\Facades\DB;

final class ProcessCheckInRewardsAction
{
    public function __construct(
        private RewardProcessorFactory $factory,
        private UserRewardRepository $userRewardRepository
    ) {}

    public function execute(User $user, array $rewards, array $context = []): array
    {
        return DB::transaction(function () use ($user, $rewards, $context) {
            $results = [];

            foreach ($rewards as $rewardTemplate) {
                $processor = $this->factory->make($rewardTemplate['type']);

                if (!$processor->canProcess($rewardTemplate)) {
                    continue;
                }

                try {
                    $result = $processor->process($user, $rewardTemplate, $context);

                    if ($result->success) {
                        $this->recordReward($user, $rewardTemplate, $result, $context);
                    }

                    $results[] = $result;

                } catch (\Exception $e) {
                    $results[] = RewardResult::failed(
                        $rewardTemplate['type'],
                        "Processing failed: {$e->getMessage()}"
                    );
                }
            }

            return $results;
        });
    }

    private function recordReward(
        User $user,
        array $template,
        RewardResult $result,
        array $context
    ): void {
        $this->userRewardRepository->create([
            'user_id' => $user->id,
            'template_id' => $template['id'],
            'check_in_id' => $context['check_in']->id ?? null,
            'value' => $result->value,
            'metadata' => array_merge($result->metadata, [
                'processed_at' => now(),
                'processor_class' => get_class($processor ?? null)
            ])
        ]);
    }
}
```

## Example Flow

### 1. User Check-In Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CheckInService;
use App\Services\Rewards\RewardService;
use Illuminate\Http\JsonResponse;

final class CheckInController extends Controller
{
    public function __construct(
        private CheckInService $checkInService,
        private RewardService $rewardService
    ) {}

    public function store(): JsonResponse
    {
        $user = auth()->user();

        // Process check-in
        $checkIn = $this->checkInService->processCheckIn($user);

        // Process rewards
        $rewards = $this->rewardService->processCheckInRewards($user, $checkIn);

        return response()->json([
            'success' => true,
            'data' => [
                'check_in' => $checkIn,
                'rewards' => $rewards
            ]
        ]);
    }
}
```

### 2. Usage Example

```php
// Service Provider Registration
$this->app->bind(RewardProcessorInterface::class, EnergyRewardProcessor::class);

// Adding a new reward type is simple:
// 1. Create new processor implementing RewardProcessorInterface
// 2. Add to RewardType enum
// 3. Register in service provider
// No existing code needs to be modified!

// Example: Adding XP Reward
enum RewardType: string
{
    case XP = 'xp';
    // ... existing cases
}

final class XpRewardProcessor implements RewardProcessorInterface
{
    public function canProcess(RewardTemplate $template): bool
    {
        return $template->type === RewardType::XP;
    }

    public function process(User $user, RewardTemplate $template, array $context = []): RewardResult
    {
        $user->increment('xp', $template->value);
        return RewardResult::success('xp', $template->value);
    }
}
```

## Key Benefits

### 1. **No Conditional Explosion**
- Each reward type has its own processor
- Factory handles instantiation
- No if/switch statements for reward types

### 2. **SOLID Principles**
- **S**: Each processor has single responsibility
- **O**: Easy to extend with new reward types
- **L**: All processors are substitutable
- **I**: Focused interfaces
- **D**: Depends on abstractions, not concretions

### 3. **Easy Extension**
- Add new reward type = Create processor + Update enum
- No existing code modification needed
- Independent testing of each processor

### 4. **Performance Optimized**
- Database transactions for consistency
- Lazy loading of processors
- Efficient querying with proper indexing
- Event-driven side effects

### 5. **Laravel-Friendly**
- Uses Laravel's service container
- Integrates with Eloquent models
- Follows Laravel conventions
- Supports caching and queuing

This architecture ensures your reward system remains maintainable and scalable as your application grows!
