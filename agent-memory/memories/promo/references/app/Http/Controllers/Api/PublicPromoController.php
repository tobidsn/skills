<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\Resources\PromoResource;
use App\Services\ApiResponse;
use App\Services\PromoService;
use App\Trait\ManagesPublicPromoCache;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class PublicPromoController extends Controller
{
    use ManagesPublicPromoCache;

    protected PromoService $promoService;

    public function __construct(PromoService $promoService)
    {
        $this->promoService = $promoService;
    }

    /**
     * Get all promo categories with caching
     */
    public function getPromoCategory(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'public:promo:categories';
            $cacheTime = 60 * 30; // 30 minutes

            $categories = Cache::remember($cacheKey, $cacheTime, function () {
                $categories = $this->promoService->getPromoCategories();
                $categories->prepend((object) [
                    'id' => null,
                    'name' => 'Semua Voucher',
                    'description' => 'Semua Voucher',
                ]);

                return $categories;
            });

            return ApiResponse::json($categories, 'Promo categories retrieved successfully');

        } catch (Exception $exception) {
            Log::error('Error in PublicPromoController::getPromoCategory: '.$exception->getMessage(), [
                'request' => $request->all(),
            ]);

            return ApiResponse::messageOnly('Terjadi kesalahan pada server', 500);
        }
    }

    /**
     * Get all promos with pagination, filtering by category and search (cached)
     */
    public function getPromo(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'load' => ['nullable', 'integer', 'min:1', 'max:20'],
                'category_id' => ['nullable'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $filters = $request->only(['load', 'category_id']);
            $page = $request->get('page', 1);

            // Create cache key using trait method
            $cacheKey = self::generatePromoListCacheKey($filters, $page);
            $cacheTime = 60 * 15; // 15 minutes

            $promosData = Cache::remember($cacheKey, $cacheTime, function () use ($filters) {
                $promos = $this->promoService->getPromos($filters);

                return [
                    'data' => $promos->items(),
                    'current_page' => $promos->currentPage(),
                    'last_page' => $promos->lastPage(),
                    'per_page' => $promos->perPage(),
                    'total' => $promos->total(),
                    'from' => $promos->firstItem(),
                    'to' => $promos->lastItem(),
                ];
            });

            // Create meta data for response
            $meta = [
                'attributes' => [
                    'total' => $promosData['total'],
                    'per_page' => $promosData['per_page'],
                    'current_page' => $promosData['current_page'],
                    'last_page' => $promosData['last_page'],
                ],
                'filtered' => [
                    'load' => $request->get('load', ''),
                    'category_id' => $request->get('category_id', ''),
                    'page' => $request->get('page', 1),
                ],
            ];

            return ApiResponse::json(
                PromoResource::collection(collect($promosData['data'])),
                'Promos retrieved successfully',
                $meta
            );

        } catch (Exception $exception) {
            Log::error('Error in PublicPromoController::getPromo: '.$exception->getMessage(), [
                'request' => $request->all(),
            ]);

            return ApiResponse::messageOnly('Terjadi kesalahan pada server', 500);
        }
    }

    /**
     * Get promo detail with caching (no member-specific data)
     */
    public function getPromoDetail(Request $request, $id): JsonResponse
    {
        try {
            $cacheKey = self::generatePromoDetailCacheKey($id);
            $cacheTime = 60 * 20; // 20 minutes

            $promo = Cache::remember($cacheKey, $cacheTime, function () use ($id) {
                // Get promo without member-specific max_redeem status
                return $this->promoService->getPromoDetail($id);
            });

            if (! $promo) {
                return ApiResponse::messageOnly('Promo not found or not available', 404);
            }

            return ApiResponse::json(new PromoResource($promo), 'Promo detail retrieved successfully');

        } catch (Exception $exception) {
            Log::error('Error in PublicPromoController::getPromoDetail: '.$exception->getMessage(), [
                'request' => $request->all(),
                'promo_id' => $id,
            ]);

            return ApiResponse::messageOnly('Terjadi kesalahan pada server', 500);
        }
    }
}
