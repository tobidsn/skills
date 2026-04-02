<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BuyEnergyRequest;
use App\Http\Resources\API\Resources\EnergyResource;
use App\Http\Resources\API\Resources\MemberEnergyResource;
use App\Models\Energy;
use App\Services\ApiResponse;
use App\Services\EnergyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class EnergyController extends Controller
{
    public function __construct(
        private EnergyService $energyService
    ) {}

    /**
     * Get all available energy packages
     *
     * Returns a list of all energy packages that members can purchase using their points.
     * Each package contains the energy amount and points required for purchase.
     *
     * Response structure is automatically documented from EnergyResource
     */
    public function index(): JsonResponse
    {
        $energies = Energy::select(['id', 'energy', 'points'])
            ->orderBy('energy', 'asc')
            ->get();

        return ApiResponse::json(EnergyResource::collection($energies));
    }

    /**
     * Buy energy package
     *
     * Purchase an energy package by deducting points from member's MyM Rewards account.
     * The transaction is processed through AntiCAS in production environment.
     * Upon successful purchase, the energy is added to member's total energy balance.
     *
     * Requires Bearer token authentication via Laravel Sanctum.
     *
     * @bodyParam energy_id integer required The energy package ID to purchase. Example: 1
     *
     * Response structure is automatically documented from MemberEnergyResource
     */
    public function buy(BuyEnergyRequest $request): JsonResponse
    {
        try {
            $member = $request->user();
            $data = $request->validated();

            $memberEnergy = $this->energyService->buyEnergy($member, $data['energy_id']);

            return ApiResponse::json(MemberEnergyResource::make($memberEnergy));
        } catch (Exception $exception) {
            Log::error('Failed to buy energy', [
                'member_id' => $request->user()->id,
                'energy_id' => $request->energy_id,
                'error' => $exception->getMessage(),
            ]);

            return ApiResponse::messageOnly($exception->getMessage(), 500);
        }
    }
}

