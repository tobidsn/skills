<?php

namespace App\Http\Controllers\Api;

use App\Enums\RewardType;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\Resources\RewardOwnedResource;
use App\Http\Resources\API\Resources\RewardResource;
use App\Models\Reward;
use App\Services\ApiResponse;
use App\Services\RewardService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class RewardController extends Controller
{
    public function __construct(
        protected RewardService $rewardService,
    ) {}

    /**
     * Claim a reward
     *
     * Claims a reward for the authenticated member.
     *
     * @param Request $request
     * @param string $reward_member_id
     * @return JsonResponse
     */
    public function claimRewardMember(Request $request, $reward_member_id)
    {
        $member = $request->user();

        try {
            $rewardMemer = $this->rewardService->claimRewardMember($member, $reward_member_id);
        } catch (BadRequestException $exception) {
            Log::warning($exception->getMessage(), ['member_id' => $member->id]);
            return ApiResponse::messageOnly('Reward already claimed');
        } catch (Exception $exception) {
            return ApiResponse::error($exception->getMessage());
        }

        return ApiResponse::json(RewardOwnedResource::make($rewardMemer), 'Reward claimed successfully');
    }

    /**
     * Get my rewards with cursor pagination
     *
     * Retrieves all rewards owned by the authenticated member using cursor-based pagination.
     * Cursor pagination provides better performance for large datasets by using cursor values
     * instead of offset-based pagination. Use the `next_cursor` from the response meta to
     * fetch the next page of results.
     *
     * @queryParam type string Filter rewards by type. Allowed values: absence, service, mystery_box, checkin. Example: absence
     * @queryParam cursor string Cursor value from previous response to fetch next page. Example: eyJpZCI6MTAsInRpbWVzdGFtcCI6MTY...
     * @queryParam load integer Number of items per page (1-20). Default: 10. Example: 10
     *
     * Response includes:
     * - `data`: Array of reward items (structure defined by RewardOwnedResource)
     * - `meta.types`: Available reward types for filtering
     * - `meta.attributes.next_cursor`: Cursor for next page (null if no more pages)
     * - `meta.attributes.prev_cursor`: Cursor for previous page (null if on first page)
     * - `meta.attributes.has_more`: Boolean indicating if more pages exist
     * - `meta.attributes.per_page`: Number of items per page
     */
    public function myRewards(Request $request)
    {
        $validTypes = array_merge(
            array_column(RewardType::cases(), 'value'),
            ['checkin']
        );

        $request->validate([
            'type' => ['nullable', 'string', Rule::in($validTypes)],
            'cursor' => ['nullable', 'string'],
            'load' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $member = $request->user();
        $perPage = $request->query('load', 10);
        $cursor = $request->query('cursor');
        $data = $this->rewardService->getMyRewards($member, $request->query('type'), $perPage, $cursor);
        $types = array_column(RewardType::cases(), 'value');

        return ApiResponse::cursorPaginate(
            RewardOwnedResource::collection($data),
            ['types' => $types]
        );
    }

    /**
     * Get checkin rewards
     *
     * Retrieves all active rewards available for check-ins, ordered by display order.
     */
    public function getCheckinRewards(Request $request)
    {
        $rewards = $this->rewardService->getCheckinRewards();

        return ApiResponse::json(RewardResource::collection($rewards));
    }
}
