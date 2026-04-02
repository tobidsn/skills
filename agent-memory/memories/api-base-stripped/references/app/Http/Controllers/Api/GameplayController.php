<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitGameplayRequest;
use App\Http\Resources\API\Resources\CustomerGameplayResource;
use App\Http\Resources\API\Resources\GameplayResultResource;
use App\Services\ApiResponse;
use App\Services\GameplayService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class GameplayController extends Controller
{
    public function __construct(
        protected GameplayService $gameplayService
    ) {}

    /**
     * Start serving a customer (Gameplay session)
     *
     * Deducts energy from member and returns a random customer with menu options.
     * The system avoids repeating recently served customers when possible.
     * Member must have sufficient energy (configured in ENERGY_GAMEPLAY).
     *
     */
    public function serveCustomer(Request $request): JsonResponse
    {
        try {
            $member = $request->user();

            if (!$member) {
                return ApiResponse::messageOnly('Unauthorized', 401);
            }

            $result = $this->gameplayService->startGameplay($member);

            return ApiResponse::json([
                'key' => $result['key'],
                'customer' => CustomerGameplayResource::make($result['customer']),
            ], 'Gameplay started');

        } catch (BadRequestException $exception) {
            return ApiResponse::error($exception->getMessage(), 400);
        } catch (Exception $exception) {
            Log::error('Error in serveCustomer', [
                'member_id' => $request->user()?->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return ApiResponse::error($exception->getMessage());
        }
    }

    /**
     * Submit gameplay with menu selections
     *
     * Submit selected menu options to complete gameplay session.
     * Calculates score based on selections and awards rewards.
     * At least one menu option must be selected.
     */
    public function submitGameplay(SubmitGameplayRequest $request, string $key): JsonResponse
    {
        try {
            $member = $request->user();

            if (!$member) {
                return ApiResponse::messageOnly('Unauthorized', 401);
            }

            $selections = [
                'main_dish_option_id' => $request->main_dish_option_id,
                'side_dish_option_id' => $request->side_dish_option_id,
                'drink_option_id' => $request->drink_option_id,
            ];

            $result = $this->gameplayService->submitGameplay($member, $key, $selections);

            return ApiResponse::json(
                GameplayResultResource::make($result),
                'Gameplay submitted successfully'
            );

        } catch (BadRequestException $exception) {
            return ApiResponse::error($exception->getMessage(), 400);
        } catch (Exception $exception) {
            Log::error('Error in submitGameplay', [
                'member_id' => $request->user()?->id,
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
            return ApiResponse::error($exception->getMessage());
        }
    }
}
