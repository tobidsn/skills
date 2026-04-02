<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'promo_id',
        'code',
        'image',
        'member_id',
        'email',
        'is_used',
        'used_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'used_at' => 'datetime',
    ];

    public function promo()
    {
        return $this->belongsTo(Promo::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Check if voucher is valid (not used and not expired)
     */
    public function isValid(): bool
    {
        return ! $this->is_used &&
               $this->expired_at &&
               $this->expired_at->isFuture();
    }

    /**
     * Mark voucher as used
     */
    public function markAsUsed(): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
        ]);
    }

    /**
     * Scope for used vouchers
     */
    public function scopeUsed($query)
    {
        return $query->where('is_used', true);
    }
}
