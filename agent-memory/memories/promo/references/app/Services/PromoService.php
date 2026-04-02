<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Member;
use App\Models\MemberBurnPoint;
use App\Models\MemberPromo;
use App\Models\Promo;
use App\Models\PromoCategory;
use App\Models\Voucher;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class PromoService extends BaseService
{
    public function __construct(
        Promo $promo,
        private AntiCasService $antiCasService,
        private RewardService $rewardService
    ) {
        parent::__construct($promo);
    }

    /**
     * Get all promo categories
     */
    public function getPromoCategories(): Collection
    {
        return PromoCategory::active()
            ->ordered()
            ->select('id', 'name', 'description')
            ->get();
    }

    /**
     * Get all promos with pagination, filtering by category and search
     */
    public function getPromos(array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->select('id', 'title', 'image', 'points', 'is_active', 'start_date')
            ->active()
            ->latest();

        // Apply pagination
        $perPage = $filters['load'] ?? 10;

        return $query->paginate($perPage);
    }

    /**
     * Get promo detail
     */
    public function getPromoDetail(string $id): Promo
    {
        return $this->model->with(['category'])
            ->active()
            ->withinDateRange()
            ->where('id', $id)
            ->first();
    }

    /**
     * Get promo detail with max redeem status for a specific member
     */
    public function getPromoDetailWithMaxRedeemStatus(string $id, Member $member): ?Promo
    {
        $promo = $this->getPromoDetail($id);

        if (! $promo) {
            return null;
        }

        $promo->status_max_redeem = $this->checkMaxRedeemStatus($promo, $member);

        return $promo;
    }

    /**
     * Check if member has reached max redeem limit for a promo
     */
    public function checkMaxRedeemStatus(Promo $promo, Member $member): bool
    {
        if ($promo->max_redeem <= 0) {
            return false;
        }

        $redeemCount = MemberBurnPoint::where('member_id', $member->id)
            ->where('promo_id', $promo->id)
            ->where('status', 'success')
            ->count();

        return $redeemCount >= $promo->max_redeem;
    }

    /**
     * Claim promo by burning points and creating voucher
     */
    public function claimPromo(string $promoId, Member $member): MemberBurnPoint
    {
        $promo = $this->getPromoDetail($promoId);
        if (! $promo) {
            throw new Exception('Promo not found or not available');
        }

        $this->validateMaxRedeem($promo, $member);

        try {
            DB::beginTransaction();

            // get voucher code from Voucher model
            $voucher = $this->getVoucherCode($promoId);
            if (! $voucher) {
                throw new Exception('Opps, kode voucher tidak tersedia');
            }

            $voucherCode = $voucher->code;
            $voucherId = $voucher->id;
            $points = -$promo->points;

            if (app()->environment('prod', 'production')) {
                $transactionId = $this->antiCasService->burnPoints(
                    $member->contestant_id,
                    (int) config('app.points_loyalty_card_id'),
                    $points
                );
            } else {
                $transactionId = '1234567890';
            }

            // Create member burn point record
            $memberBurnPoint = MemberBurnPoint::create([
                'member_id' => $member->id,
                'promo_id' => $promoId,
                'points' => $promo->points,
                'status' => 'success',
                'transaction_id' => $transactionId,
                'voucher_code' => $voucherCode,
                'voucher_id' => $voucherId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // update voucher
            $voucher->update([
                'is_used' => true,
                'member_id' => $member->id,
                'email' => $member->email,
                'used_at' => now(),
            ]);

            // give member reward
            if ($promo->is_reward) {
                $reward = $promo->reward;
                $rewardMember = $this->rewardService->createRewardMember($member, $reward, $memberBurnPoint->id, MemberBurnPoint::class);
                $memberBurnPoint->update([
                    'reward_member_id' => $rewardMember->id,
                ]);
            }

            DB::commit();

            return $memberBurnPoint;

        } catch (Exception $exception) {
            Log::error('Error claiming promo: '.$exception->getMessage(), [
                'promo_id' => $promoId,
                'member_id' => $member->id,
                'exception' => $exception,
            ]);

            DB::rollBack();

            throw $exception;
        }
    }

    /**
     * Get voucher code from Voucher model
     */
    private function getVoucherCode(string $promoId): ?Voucher
    {
        $voucher = Voucher::where('promo_id', $promoId)
            ->where('is_used', false)
            ->lockForUpdate()
            ->first();

        return $voucher ?? null;
    }

    /**
     * Get member's vouchers
     */
    public function getVouchers(Member $member): Collection
    {
        return MemberBurnPoint::where('member_id', $member->id)
            ->with(['promo', 'voucher', 'rewardMember'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getVoucherDetail(string $id, Member $member): MemberBurnPoint
    {
        return MemberBurnPoint::where('member_id', $member->id)
            ->with(['promo', 'voucher', 'rewardMember'])
            ->where('id', $id)
            ->first();
    }

    private function validateMaxRedeem(Promo $promo, Member $member): void
    {
        if ($this->checkMaxRedeemStatus($promo, $member)) {
            throw new Exception('Oops, kamu sudah mencapai batas maksimal klaim promo ini');
        }
    }

    public function claimMysteryBox(Member $member): MemberPromo
    {
        if (today() > Carbon::parse(config('app.campaign_ended_date'))) {
            throw new BadRequestException('Mystery box hanya tersedia setelah kampanye berakhir');
        }

        if (! $this->canClaimMysteryBox($member)) {
            throw new BadRequestException('Kamu sudah mengklaim semua mystery box yang tersedia');
        }

        try {
            DB::beginTransaction();

            $promo = $this->getRandomEligiblePromo();
            if (! $promo) {
                throw new BadRequestException('Tidak ada promo yang tersedia untuk mystery box');
            }

            if (! $promo->hasAvailableQuota()) {
                throw new BadRequestException('Quota promo sudah habis');
            }

            $voucherCode = null;
            $voucher = null;

            if ($promo->is_reward && $promo->reward_id) {
                $memberPromo = MemberPromo::create([
                    'member_id' => $member->id,
                    'promo_id' => $promo->id,
                    'type' => 'reward_member',
                    'status' => 'completed',
                    'voucher_code' => null,
                ]);

                $reward = $promo->reward;
                $rewardMember = $this->rewardService->createRewardMember(
                    $member,
                    $reward,
                    $memberPromo->id,
                    MemberPromo::class
                );

                $memberPromo->update([
                    'reward_member_id' => $rewardMember->id,
                ]);
            } else {
                $voucher = $this->getVoucherCode($promo->id);
                if (! $voucher) {
                    throw new BadRequestException('Voucher tidak tersedia untuk promo ini');
                }

                $voucherCode = $voucher->code;

                $memberPromo = MemberPromo::create([
                    'member_id' => $member->id,
                    'promo_id' => $promo->id,
                    'type' => 'voucher',
                    'status' => 'completed',
                    'voucher_code' => $voucherCode,
                ]);

                $voucher->update([
                    'is_used' => true,
                    'member_id' => $member->id,
                    'email' => $member->email,
                    'used_at' => now(),
                ]);
            }

            $promo->incrementQuotaUsed();

            DB::commit();

            $memberPromo->load(['promo', 'rewardMember']);

            return $memberPromo;

        } catch (Exception $exception) {
            Log::error('Error claiming mystery box: '.$exception->getMessage(), [
                'member_id' => $member->id,
                'exception' => $exception,
            ]);

            DB::rollBack();

            throw $exception;
        }
    }

    public function canClaimMysteryBox(Member $member): bool
    {
        $allowedBoxes = $member->getMysteryBoxLevel();
        $claimedBoxes = $this->getMemberMysteryBoxCount($member);

        return $claimedBoxes < $allowedBoxes;
    }

    public function getMemberMysteryBoxCount(Member $member): int
    {
        return MemberPromo::select('id')
            ->where('member_id', $member->id)
            ->whereIn('type', ['reward_member', 'voucher'])
            ->count('id');
    }

    private function getRandomEligiblePromo(): ?Promo
    {
        return $this->model->select('id', 'title', 'image', 'description', 'reward_id', 'is_reward', 'quota', 'quota_used')
            ->with(['reward'])
            ->active()
            ->withAvailableQuota()
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('is_reward', true)
                        ->whereNotNull('reward_id');
                })->orWhere(function ($q) {
                    $q->where('is_reward', false)
                        ->whereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('vouchers')
                                ->whereRaw('vouchers.promo_id = promos.id')
                                ->where('is_used', false);
                        });
                });
            })
            ->inRandomOrder()
            ->lockForUpdate()
            ->first();
    }
}
