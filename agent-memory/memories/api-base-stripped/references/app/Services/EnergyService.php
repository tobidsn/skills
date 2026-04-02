<?php

namespace App\Services;

use App\Models\Energy;
use App\Models\Member;
use App\Models\MemberEnergy;
use Exception;
use Illuminate\Support\Facades\DB;

final class EnergyService
{
    public function __construct(
        private AntiCasService $antiCasService
    ) {}

    public function buyEnergy(Member $member, int $energyId): MemberEnergy
    {
        if (!config('app.enable_buy_energy')) {
            throw new Exception('Buy energy feature is currently disabled');
        }

        $energy = Energy::select(['id', 'energy', 'points'])
            ->where('id', $energyId)
            ->first();

        if (!$energy) {
            throw new Exception('Energy not found');
        }

        DB::beginTransaction();

        try {
            if (app()->environment('prod', 'production')) {
                $transactionId = $this->antiCasService->burnPoints(
                    $member->contestant_id,
                    (int) config('app.points_loyalty_card_id'),
                    $energy->points
                );
            } else {
                $transactionId = 'dev-' . time() . '-' . rand(1000, 9999);
            }

            $memberEnergy = MemberEnergy::create([
                'member_id' => $member->id,
                'energy_id' => $energy->id,
                'energy' => $energy->energy,
                'points' => $energy->points,
                'contestant_id' => $member->contestant_id,
                'transaction_id' => $transactionId,
            ]);

            $member->addEnergy($energy->energy);

            DB::commit();

            return $memberEnergy;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
