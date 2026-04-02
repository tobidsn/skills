<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Reward;
use App\Models\RewardMember;
use Exception;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class RewardService
{
    public function __construct(
        protected AntiCasService $antiCasService,
    ) {}

    public function claimRewardMember(Member $member, string $reward_member_id): RewardMember
    {
        $rewardMember = RewardMember::where('member_id', $member->id)
            ->where('uuid', $reward_member_id)
            ->first();

        if (! $rewardMember) {
            throw new BadRequestException('Reward not found');
        }

        if ($rewardMember->is_claimed) {
            throw new BadRequestException('Reward already claimed');
        }

        $rewardMember->setClaimed();

        try {
            if ($rewardMember->offer_id && $rewardMember->loyalty_id) {

                if (app()->environment('production')) {
                    $transactionId = $this->antiCasService->redeemOffers(
                        $member->contestant_id,
                        $rewardMember->loyalty_id,
                        $rewardMember->offer_id
                    );
                } else {
                    $transactionId = 'dev-' . time() . '-' . rand(1000, 9999);
                }

                $rewardMember->update([
                    'contestant_id' => $member->contestant_id,
                    'transaction_id' => $transactionId,
                ]);
            }
        } catch (Exception $e) {
            $rewardMember->update([
                'is_claimed' => false,
                'claimed_at' => null,
            ]);
            throw new Exception('Gagal mengklaim reward: '.$e->getMessage());
        }

        return $rewardMember;
    }

    public function getMyRewards(Member $member, ?string $type = null, int $perPage = 10, ?string $cursor = null): CursorPaginator
    {
        $columns = ['id', 'uuid', 'title', 'description', 'image', 'type', 'is_claimed', 'created_at'];

        $query = RewardMember::select($columns)
            ->where('member_id', $member->id);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->orderBy('id', 'asc')
            ->orderBy('created_at', 'asc')
            ->cursorPaginate($perPage, $columns, 'cursor', $cursor);
    }

    public function getCheckinRewards(): Collection
    {
        return Reward::where('is_active', true)
            ->select('id', 'title', 'description', 'image', 'type', 'order')
            ->where('type', 'checkin')
            ->orderBy('order', 'asc')
            ->get();
    }

    public function createRewardMember(Member $member, $reward, $rewardableId, string $rewardableType): RewardMember
    {
        if (! $reward) {
            throw new Exception('Reward not found');
        }

        $rewardMember = RewardMember::create([
            'uuid' => Str::uuid()->toString(),
            'member_id' => $member->id,
            'email' => $member->email,
            'reward_id' => $reward->id,
            'type' => $reward->type,
            'title' => $reward->title,
            'description' => $reward->description,
            'image' => $reward->image_url,
            'small_image' => $reward->small_image ?? null,
            'offer_id' => $reward->offer_id ?? null,
            'loyalty_id' => $reward->loyalty_id ?? null,
            'is_claimed' => false,
            'rewardable_id' => $rewardableId,
            'rewardable_type' => $rewardableType,
        ]);

        if (! $rewardMember) {
            throw new Exception('Failed to create reward member');
        }

        return $rewardMember;
    }
}
