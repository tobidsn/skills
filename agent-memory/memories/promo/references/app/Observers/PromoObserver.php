<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Promo;
use App\Trait\ManagesPublicPromoCache;

final class PromoObserver
{
    use ManagesPublicPromoCache;

    /**
     * Handle the Promo "created" event.
     */
    public function created(Promo $promo): void
    {
        self::clearPromoListCache();
    }

    /**
     * Handle the Promo "updated" event.
     */
    public function updated(Promo $promo): void
    {
        self::clearPromoListCache();
        self::clearPromoDetailCache($promo->id);
    }

    /**
     * Handle the Promo "deleted" event.
     */
    public function deleted(Promo $promo): void
    {
        self::clearPromoListCache();
        self::clearPromoDetailCache($promo->id);
    }

    /**
     * Handle the Promo "restored" event.
     */
    public function restored(Promo $promo): void
    {
        self::clearPromoListCache();
    }

    /**
     * Handle the Promo "force deleted" event.
     */
    public function forceDeleted(Promo $promo): void
    {
        self::clearPromoListCache();
        self::clearPromoDetailCache($promo->id);
    }
}
