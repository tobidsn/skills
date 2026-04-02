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

final class PromoCategory extends Model
{
    use CreatedUpdatedBy;
    use HasFactory;
    use HasUuids;
    use ManagesPublicPromoCache;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the promos that belong to this category.
     */
    public function promos(): HasMany
    {
        return $this->hasMany(Promo::class, 'category_id');
    }

    /**
     * Get the user who created the promo category.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the promo category.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the promo count for this category.
     */
    public function getPromoCountAttribute(): int
    {
        return $this->promos()->count();
    }

    /**
     * Scope a query to only include active promo categories.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to order promo categories by sort order and name.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}
