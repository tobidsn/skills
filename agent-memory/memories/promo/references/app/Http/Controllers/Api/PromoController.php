<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\Resources\MysteryBoxResource;
use App\Http\Resources\API\Resources\PromoResource;
use App\Http\Resources\API\Resources\VoucherResource;
use App\Services\ApiResponse;
use App\Services\PromoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class PromoController extends Controller
{
    protected PromoService $promoService;

    public function __construct(PromoService $promoService)
    {
        $this->promoService = $promoService;
    }

    /**
     * Get all promo categories
     */
    public function getPromoCategory(Request $request): JsonResponse
    {
        try {
            $categories = $this->promoService->getPromoCategories();
            $categories->prepend((object) [
                'id' => null,
                'name' => 'Semua Voucher',
                'description' => 'Semua Voucher',
            ]);

            return ApiResponse::json($categories, 'Promo categories retrieved successfully');

        } catch (Exception $exception) {
            Log::error('Error in getPromoCategory: '.$exception->getMessage(), [
                'request' => $request->all(),
            ]);

            return ApiResponse::messageOnly('Terjadi kesalahan pada server', 500);
        }
    }

    /**
     * Get all promos
     *
     * Retrieves all mistery box with pagination.
     * @tags Mystery Box
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPromo(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'load' => ['nullable', 'integer', 'min:1', 'max:20'],
            ]);

            $promos = $this->promoService->getPromos();

            return ApiResponse::paginate(
                PromoResource::collection($promos),
                [],
                'Promos retrieved successfully'
            );

        } catch (Exception $exception) {
            Log::error('Error in getPromo: '.$exception->getMessage(), [
                'request' => $request->all(),
            ]);

            return ApiResponse::messageOnly('Terjadi kesalahan pada server [10]', 500);
        }
    }

    public function getPromoDetail(Request $request, $id): JsonResponse
    {
        try {
            $member = $request->user();
            $promo = $this->promoService->getPromoDetailWithMaxRedeemStatus($id, $member);

            if (! $promo) {
                return ApiResponse::messageOnly('Promo not found or not available', 404);
            }

            return ApiResponse::json(new PromoResource($promo), 'Promo detail retrieved successfully');

        } catch (Exception $exception) {
            Log::error('Error in getPromoDetail: '.$exception->getMessage(), [
                'request' => $request->all(),
                'promo_id' => $id,
                'member_id' => $request->user()?->id,
            ]);

            return ApiResponse::messageOnly('Terjadi kesalahan pada server', 500);
        }
    }

    public function claimPromo(Request $request, $id): JsonResponse
    {
        try {
            $member = $request->user();

            if (! $member) {
                return ApiResponse::messageOnly('Unauthorized', 401);
            }

            $data = $this->promoService->claimPromo($id, $member);

            return ApiResponse::json(new VoucherResource($data), 'Promo claimed successfully');

        } catch (Exception $exception) {
            Log::error('Error in claimPromo: '.$exception->getMessage(), [
                'request' => $request->all(),
                'promo_id' => $id,
                'member_id' => $request->user()?->id,
            ]);

            $message = $exception->getMessage();
            $statusCode = 400;

            // Handle specific error cases
            if (str_contains($message, 'not found')) {
                $statusCode = 404;
            } elseif (str_contains($message, 'already claimed')) {
                $statusCode = 409;
            } elseif (str_contains($message, 'server')) {
                $statusCode = 500;
            }

            return ApiResponse::messageOnly($message, $statusCode);
        }
    }

    public function vouchers(Request $request)
    {
        try {
            $member = $request->user();

            $data = $this->promoService->getVouchers($member);

            return ApiResponse::json(VoucherResource::collection($data));

        } catch (Exception $exception) {
            Log::error('Error in vouchers: '.$exception->getMessage(), [
                'request' => $request->all(),
                'member_id' => $request->user()?->id,
            ]);

            return ApiResponse::messageOnly('Terjadi kesalahan pada server', 500);
        }
    }

    public function voucherDetail(Request $request, $id)
    {
        $member = $request->user();

        $data = $this->promoService->getVoucherDetail($id, $member);

        return ApiResponse::json(new VoucherResource($data));
    }

    /**
     * Claim a mystery box reward
     *
     * Members can claim mystery boxes after the campaign ends based on their badge level:
     * - BASIC level: 1 mystery box
     * - SUPER level: 2 mystery boxes
     * - EXPERT level: 3 mystery boxes
     *
     * Each claim returns a random active promo with an available voucher code.
     * Members must claim boxes individually (one per request).
     *
     * Response structure is automatically documented from MysteryBoxResource
     * @tags Mystery Box
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function claimMysteryBox(Request $request): JsonResponse
    {
        try {
            $member = $request->user();

            $memberPromo = $this->promoService->claimMysteryBox($member);

            return ApiResponse::json(
                new MysteryBoxResource($memberPromo),
                'Mystery box berhasil diklaim'
            );

        } catch (BadRequestException $exception) {

            $message = $exception->getMessage();
            $statusCode = 400;

            if (str_contains($message, 'sudah mengklaim')) {
                $statusCode = 403;
            } elseif (str_contains($message, 'tidak tersedia')) {
                $statusCode = 404;
            }

            return ApiResponse::messageOnly($message, $statusCode);
        } catch (Exception $exception) {
            Log::error('Error claiming mystery box: '.$exception->getMessage(), [
                'member_id' => $request->user()?->id,
                'exception' => $exception,
            ]);

            return ApiResponse::messageOnly($exception->getMessage(), 500);
        }
    }
}
