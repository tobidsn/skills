<?php

namespace App\Services;

use App\Models\CheckinReward;
use App\Models\Member;
use App\Models\MemberCheckin;
use App\Services\Checkin\Contracts\RewardDistributorInterface;
use App\Services\Checkin\Distributors\EnergyDistributor;
use App\Services\Checkin\Distributors\MultipleDistributor;
use App\Services\Checkin\Distributors\RewardDistributor;
use App\Services\Checkin\Distributors\ScoreDistributor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class CheckinService
{
    protected array $distributors = [];

    public function __construct(
        protected RewardService $rewardService
    ) {
        $this->registerDistributors();
    }

    private function registerDistributors(): void
    {
        $energyDistributor = new EnergyDistributor();
        $scoreDistributor = new ScoreDistributor();
        $rewardDistributor = new RewardDistributor($this->rewardService);
        $multipleDistributor = new MultipleDistributor(
            $energyDistributor,
            $scoreDistributor,
            $rewardDistributor
        );

        $this->distributors = [
            $energyDistributor,
            $scoreDistributor,
            $rewardDistributor,
            $multipleDistributor,
        ];
    }

    public function getCheckinList(Member $member): Collection
    {
        $checkinRewards = CheckinReward::query()
            ->select('id', 'day_number', 'type', 'energy', 'score', 'reward_id')
            ->with('reward:id,title,description,image,type')
            ->orderBy('day_number')
            ->get();

        $memberCheckins = MemberCheckin::query()
            ->select('id', 'member_id', 'checkin_reward_id', 'energy', 'score', 'reward_member_id', 'created_at')
            ->where('member_id', $member->id)
            ->get()
            ->keyBy('checkin_reward_id');

        return $checkinRewards->map(function (CheckinReward $checkinReward) use ($memberCheckins) {
            $memberCheckin = $memberCheckins->get($checkinReward->id);

            $checkinReward->is_completed = $memberCheckin !== null;
            $checkinReward->completed_at = $memberCheckin?->created_at;

            return $checkinReward;
        });
    }

    public function getCurrentDayNumber(Member $member): int
    {
        $lastCheckin = MemberCheckin::query()
            ->select('id', 'member_id', 'checkin_reward_id', 'energy', 'score', 'reward_member_id', 'day_number')
            ->where('member_id', $member->id)
            ->latest()
            ->first();

        if (! $lastCheckin) {
            return 1;
        }

        return $lastCheckin->day_number + 1;
    }

    public function hasTodayCheckin(Member $member): bool
    {
        return $member->hasTodayCheckin();
    }

    public function claimCheckin(Member $member): MemberCheckin
    {
        if ($this->hasTodayCheckin($member)) {
            throw new BadRequestException('You have already checked in today');
        }

        $currentDay = $this->getCurrentDayNumber($member);

        if ($currentDay > 30) {
            throw new BadRequestException('You have completed all 30 days of check-in');
        }

        $checkinReward = CheckinReward::with('reward')
            ->select('id', 'day_number', 'type', 'energy', 'score', 'reward_id')
            ->where('day_number', $currentDay)
            ->first();

        if (! $checkinReward) {
            throw new BadRequestException('No check-in reward available for today');
        }

        return DB::transaction(function () use ($member, $checkinReward, $currentDay) {
            $memberCheckin = MemberCheckin::create([
                'member_id' => $member->id,
                'checkin_reward_id' => $checkinReward->id,
                'day_number' => $currentDay,
                'energy' => $checkinReward->energy ?? 0,
                'score' => $checkinReward->score ?? 0,
            ]);

            $distributor = $this->getDistributor($checkinReward->type);

            $distributor->distribute($member, $checkinReward, $memberCheckin);

            $memberCheckin->load([
                'checkinReward:id,day_number,type',
                'rewardMember:id,uuid,title,description,image,small_image,is_claimed',
            ]);

            return $memberCheckin;
        });
    }

    private function getDistributor(string $type): RewardDistributorInterface
    {
        foreach ($this->distributors as $distributor) {
            if ($distributor->canHandle($type)) {
                return $distributor;
            }
        }

        throw new BadRequestException("No distributor found for type: {$type}");
    }
}

