<?php

declare(strict_types=1);

namespace App\Models;

use App\Trait\CreatedUpdatedBy;
use App\Trait\ManagesPublicPromoCache;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Promo extends Model
{
    use CreatedUpdatedBy;
    use HasFactory;
    use HasUuids;
    use ManagesPublicPromoCache;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'category_id',
        'points',
        'quota',
        'quota_used',
        'reward_id',
        'is_reward',
        'is_active',
        'image',
        'url',
        'image_detail',
        'description',
        'tnc',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'points' => 'integer',
        'quota' => 'integer',
        'quota_used' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function imageFile()
    {
        return $this->belongsTo(File::class, 'image');
    }

    public function getImageUrlAttribute()
    {
        return $this->imageFile?->getFirstMediaUrl('file');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    /**
     * Get the category that owns the promo.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PromoCategory::class);
    }

    /**
     * Get the user who created the promo.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the promo.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the vouchers that belong to this promo.
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * Scope a query to only include active promos.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include promos within date range.
     */
    public function scopeWithinDateRange($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('start_date')
                ->orWhere('start_date', '<=', now()->startOfDay());
        })->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', now()->startOfDay());
        });
    }

    /**
     * Scope a query to only include current and future promos.
     */
    public function scopeCurrentAndFuture($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>=', now());
        });
    }

    /**
     * Scope a query to only include promos with available quota.
     */
    public function scopeWithAvailableQuota($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('quota')
                ->orWhereColumn('quota_used', '<', 'quota');
        });
    }

    /**
     * Check if promo has available quota.
     */
    public function hasAvailableQuota(): bool
    {
        return $this->quota === null || $this->quota_used < $this->quota;
    }

    /**
     * Increment quota used count.
     */
    public function incrementQuotaUsed(): int
    {
        return $this->increment('quota_used');
    }
}
