<?php

declare(strict_types=1);

namespace App\Trait;

use Illuminate\Support\Facades\Cache;

trait ManagesPublicPromoCache
{
    /**
     * Clear promo categories cache
     */
    public static function clearPromoCategoriesCache(): void
    {
        Cache::forget('public:promo:categories');
    }

    /**
     * Clear promo list cache for common patterns
     */
    public static function clearPromoListCache(): void
    {
        // Clear common pagination cache keys (pages 1-10)
        for ($page = 1; $page <= 10; $page++) {
            $filters = [
                ['load' => 10, 'category_id' => null],
                ['load' => 20, 'category_id' => null],
                ['load' => 15, 'category_id' => null],
            ];

            foreach ($filters as $filter) {
                $cacheKey = 'public:promo:list:'.md5(serialize($filter).'_page_'.$page);
                Cache::forget($cacheKey);
            }
        }

        // Clear category-specific cache if we knew the category IDs
        // This is a simplified approach for demonstration
    }

    /**
     * Clear specific promo detail cache
     */
    public static function clearPromoDetailCache(string $promoId): void
    {
        Cache::forget('public:promo:detail:'.$promoId);
    }

    /**
     * Clear all public promo cache
     */
    public static function clearAllPublicPromoCache(): void
    {
        self::clearPromoCategoriesCache();
        self::clearPromoListCache();

        // In a production environment with Redis, you could use:
        // Cache::flush() // but this clears ALL cache
        // Or use cache tags if supported by your cache driver
    }

    /**
     * Generate cache key for promo list
     */
    public static function generatePromoListCacheKey(array $filters, int $page): string
    {
        return 'public:promo:list:'.md5(serialize($filters).'_page_'.$page);
    }

    /**
     * Generate cache key for promo detail
     */
    public static function generatePromoDetailCacheKey(string $promoId): string
    {
        return 'public:promo:detail:'.$promoId;
    }
}
