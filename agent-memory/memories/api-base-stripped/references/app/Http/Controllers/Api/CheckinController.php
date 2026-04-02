<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClaimCheckinRequest;
use App\Http\Resources\API\Resources\CheckinRewardResource;
use App\Http\Resources\API\Resources\MemberCheckinResource;
use App\Services\ApiResponse;
use App\Services\CheckinService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class CheckinController extends Controller
{
    public function __construct(
        protected CheckinService $checkinService
    ) {}

    /**
     * List all checkin rewards
     *
     * Shows 30 days of check-in rewards with completion status and current day indicator.
     * Day progression is based on total check-in count, not calendar dates.
     * Members can miss days without penalty and continue from where they left off.
     * Still limited to one check-in per calendar day.
     */
    public function index(Request $request)
    {
        $member = $request->user();
        $checkins = $this->checkinService->getCheckinList($member);

        return ApiResponse::json(CheckinRewardResource::collection($checkins));
    }

    /**
     * Claim today's checkin reward
     *
     * Processes the check-in and distributes rewards based on the member's check-in count.
     * Members can only check in once per calendar day but progress continues regardless of missed days.
     * Rewards are based on total check-in count (1st check-in = Day 1, 2nd = Day 2, etc.).
     * Rewards can include energy, score, physical rewards, or multiple combinations.
     */
    public function claim(ClaimCheckinRequest $request)
    {
        try {
            $member = $request->user();
            $memberCheckin = $this->checkinService->claimCheckin($member);

            return ApiResponse::json(
                MemberCheckinResource::make($memberCheckin),
                'Check-in successful'
            );
        } catch (BadRequestException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (Exception $e) {
            Log::error('Checkin claim failed', [
                'member_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}

