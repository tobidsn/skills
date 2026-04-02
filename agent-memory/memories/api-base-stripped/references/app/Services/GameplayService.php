<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerMenuOption;
use App\Models\Member;
use App\Models\MemberGamePlay;
use App\Models\MemberGamePlayItem;
use App\Models\Reward;
use App\Models\RewardMember;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class GameplayService
{
    public function __construct(
        protected RewardService $rewardService
    ) {}
    /**
     * Start a new gameplay session for member
     */
    public function startGameplay(Member $member): array
    {
        $this->validateMemberEnergy($member);

        DB::beginTransaction();

        try {
            $energyCost = (int) config('app.energy_gameplay', 3);

            $this->deductEnergy($member, $energyCost);

            $customer = $this->getRandomCustomer($member);

            if (!$customer) {
                throw new BadRequestException('No customers available');
            }

            $gameplay = MemberGamePlay::create([
                'key' => Str::uuid(),
                'member_id' => $member->id,
                'customer_id' => $customer->id,
                'energy_spent' => $energyCost,
                'status' => 'key_generated',
            ]);

            $customerWithOptions = $this->loadCustomerWithMenuOptions($customer);

            DB::commit();

            return [
                'key' => $gameplay->key,
                'customer' => $customerWithOptions,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Gameplay start failed', [
                'member_id' => $member->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate member has sufficient energy
     */
    private function validateMemberEnergy(Member $member): void
    {
        $requiredEnergy = (int) config('app.energy_gameplay', 3);

        if ($member->total_energy < $requiredEnergy) {
            throw new BadRequestException('Insufficient energy to serve customer');
        }
    }

    /**
     * Deduct energy from member
     */
    private function deductEnergy(Member $member, int $amount): void
    {
        $member->decrement('total_energy', $amount);
    }

    /**
     * Get random customer, avoiding recently served ones
     */
    private function getRandomCustomer(Member $member): ?Customer
    {
        $recentCustomerIds = $this->getRecentCustomerIds($member, 1);

        $customer = Customer::select(['id', 'code', 'dialog', 'best_reward_id', 'good_reward_id'])
            ->whereNotIn('id', $recentCustomerIds)
            ->inRandomOrder()
            ->first();

        if (!$customer) {
            $customer = Customer::select(['id', 'code', 'dialog', 'best_reward_id', 'good_reward_id'])
                ->inRandomOrder()
                ->first();
        }

        return $customer;
    }

    /**
     * Get recent customer IDs from member's gameplay history
     */
    private function getRecentCustomerIds(Member $member, int $limit = 5): array
    {
        return MemberGamePlay::where('member_id', $member->id)
            ->select('customer_id')
            ->latest()
            ->limit($limit)
            ->pluck('customer_id')
            ->toArray();
    }

    /**
     * Load customer with menu options grouped by category
     */
    private function loadCustomerWithMenuOptions(Customer $customer): Customer
    {
        return Customer::select(['id', 'code', 'dialog', 'best_reward_id', 'good_reward_id'])
            ->with([
                'menuOptions' => function ($query) {
                    $query->select(['id', 'customer_id', 'category', 'food_id', 'score', 'order'])
                        ->orderBy('order', 'asc')
                        ->with([
                            'food' => function ($foodQuery) {
                                $foodQuery->select(['id', 'slug', 'image']);
                            }
                        ]);
                }
            ])
            ->findOrFail($customer->id);
    }

    /**
     * Load customer for gameplay submission
     */
    private function loadCustomerForSubmission(string $customerId): Customer
    {
        return Customer::select(['id', 'best_reward_id', 'good_reward_id'])
            ->with([
                'menuOptions' => function ($query) {
                    $query->select(['id', 'customer_id', 'category', 'score']);
                },
                'bestReward' => function ($query) {
                    $query->select(['id', 'title', 'description', 'image']);
                }
            ])
            ->findOrFail($customerId);
    }

    /**
     * Submit gameplay with menu selections
     */
    public function submitGameplay(Member $member, string $key, array $selections): array
    {
        $gameplay = $this->validateGameplayKey($member, $key);
        $customer = $this->loadCustomerForSubmission($gameplay->customer_id);
        $this->validateMenuSelections($customer, $selections);

        DB::beginTransaction();

        try {
            $scoreBreakdown = $this->calculateScore($selections);
            $totalScore = $scoreBreakdown['total_score'];
            $scoreLevel = $this->determineScoreLevel($totalScore);

            $gameplay->update([
                'service_score' => $totalScore,
                'status' => 'completed',
            ]);

            $this->createGameplayItems($gameplay, $customer, $selections, $scoreBreakdown);

            $member->update([
                'total_customer_served' => DB::raw('total_customer_served + 1'),
                'total_score' => DB::raw('total_score + ' . (int)$totalScore),
            ]);

            $reward = $this->awardReward($member, $customer, $gameplay, $scoreLevel);

            if ($reward) {
                $gameplay->update(['reward_member_id' => $reward->id]);
            }

            DB::commit();

            return [
                'gameplay' => $gameplay->fresh(),
                'score_level' => $scoreLevel,
                'reward' => $reward,
                'best_reward' => $customer->bestReward,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Gameplay submission failed', [
                'member_id' => $member->id,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate gameplay key and status
     */
    private function validateGameplayKey(Member $member, string $key): MemberGamePlay
    {
        $gameplay = MemberGamePlay::select(['id', 'key', 'member_id', 'customer_id', 'status'])
            ->where('key', $key)
            ->first();

        if (!$gameplay) {
            throw new BadRequestException('Gameplay session not found');
        }

        if ($gameplay->member_id !== $member->id) {
            throw new BadRequestException('Unauthorized access to gameplay');
        }

        if ($gameplay->status !== 'key_generated') {
            throw new BadRequestException('Gameplay already submitted');
        }

        return $gameplay;
    }

    /**
     * Validate menu selections belong to customer
     */
    private function validateMenuSelections(Customer $customer, array $selections): void
    {
        $customerOptionIds = $customer->menuOptions
            ->pluck('id')
            ->toArray();

        foreach ($selections as $category => $optionId) {
            if (!$optionId) {
                continue;
            }

            if (!in_array($optionId, $customerOptionIds)) {
                throw new BadRequestException('Selected options do not belong to this customer');
            }

            $option = $customer->menuOptions->firstWhere('id', $optionId);
            $expectedCategory = str_replace('_option_id', '', $category);

            if ($option && $option->category !== $expectedCategory) {
                throw new BadRequestException('Selected option does not match category');
            }
        }
    }

    /**
     * Calculate total score and breakdown
     */
    private function calculateScore(array $selections): array
    {
        $scores = [
            'main_dish_score' => 0,
            'side_dish_score' => 0,
            'drink_score' => 0,
            'total_score' => 0,
        ];

        $optionIds = array_filter($selections);

        if (empty($optionIds)) {
            return $scores;
        }

        $options = CustomerMenuOption::select(['id', 'score'])
            ->whereIn('id', $optionIds)
            ->get()
            ->keyBy('id');

        if ($selections['main_dish_option_id'] && isset($options[$selections['main_dish_option_id']])) {
            $scores['main_dish_score'] = $options[$selections['main_dish_option_id']]->score;
        }

        if ($selections['side_dish_option_id'] && isset($options[$selections['side_dish_option_id']])) {
            $scores['side_dish_score'] = $options[$selections['side_dish_option_id']]->score;
        }

        if ($selections['drink_option_id'] && isset($options[$selections['drink_option_id']])) {
            $scores['drink_score'] = $options[$selections['drink_option_id']]->score;
        }

        $scores['total_score'] = $scores['main_dish_score'] + $scores['side_dish_score'] + $scores['drink_score'];

        return $scores;
    }

    /**
     * Determine score level based on total
     */
    private function determineScoreLevel(int $totalScore): string
    {
        if ($totalScore >= 80) {
            return 'best';
        }

        if ($totalScore >= 60) {
            return 'good';
        }

        return 'bad';
    }

    /**
     * Award reward based on score level
     */
    private function awardReward(Member $member, Customer $customer, MemberGamePlay $gameplay, string $scoreLevel): ?RewardMember
    {
        $rewardId = match($scoreLevel) {
            'best' => $customer->best_reward_id,
            'good' => $customer->good_reward_id,
            default => null,
        };

        if (!$rewardId) {
            return null;
        }

        $reward = Reward::select(['id', 'title', 'description', 'image', 'offer_id', 'loyalty_id'])
            ->find($rewardId);

        if (!$reward) {
            return null;
        }

        return $this->rewardService->createRewardMember(
            $member,
            $reward,
            $gameplay->id,
            MemberGamePlay::class
        );
    }

    private function createGameplayItems(MemberGamePlay $gameplay, Customer $customer, array $selections, array $scoreBreakdown): void
    {
        $optionIds = array_filter($selections);

        if (empty($optionIds)) {
            return;
        }

        $menuOptions = CustomerMenuOption::select(['id', 'customer_id', 'category', 'food_id', 'score'])
            ->whereIn('id', $optionIds)
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($selections as $categoryKey => $optionId) {
            if (!$optionId || !isset($menuOptions[$optionId])) {
                continue;
            }

            $category = str_replace('_option_id', '', $categoryKey);
            $menuOption = $menuOptions[$optionId];
            $scoreKey = $category . '_score';

            $items[] = [
                'member_game_play_id' => $gameplay->id,
                'customer_id' => $customer->id,
                'category' => $category,
                'option_id' => $optionId,
                'food_id' => $menuOption->food_id,
                'score' => $scoreBreakdown[$scoreKey] ?? 0,
            ];
        }

        if (!empty($items)) {
            MemberGamePlayItem::insert($items);
        }
    }
}
