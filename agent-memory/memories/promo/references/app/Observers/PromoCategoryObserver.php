<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PromoCategory;
use App\Trait\ManagesPublicPromoCache;

final class PromoCategoryObserver
{
    use ManagesPublicPromoCache;

    /**
     * Handle the PromoCategory "created" event.
     */
    public function created(PromoCategory $promoCategory): void
    {
        self::clearPromoCategoriesCache();
        self::clearPromoListCache();
    }

    /**
     * Handle the PromoCategory "updated" event.
     */
    public function updated(PromoCategory $promoCategory): void
    {
        self::clearPromoCategoriesCache();
        self::clearPromoListCache();
    }

    /**
     * Handle the PromoCategory "deleted" event.
     */
    public function deleted(PromoCategory $promoCategory): void
    {
        self::clearPromoCategoriesCache();
        self::clearPromoListCache();
    }

    /**
     * Handle the PromoCategory "restored" event.
     */
    public function restored(PromoCategory $promoCategory): void
    {
        self::clearPromoCategoriesCache();
        self::clearPromoListCache();
    }

    /**
     * Handle the PromoCategory "force deleted" event.
     */
    public function forceDeleted(PromoCategory $promoCategory): void
    {
        self::clearPromoCategoriesCache();
        self::clearPromoListCache();
    }
}
