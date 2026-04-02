<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberBurnPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'promo_id',
        'points',
        'status',
        'transaction_id',
        'voucher_id',
        'voucher_code',
        'reward_member_id',
        'ip_address',
        'user_agent',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function rewardMember(): BelongsTo
    {
        return $this->belongsTo(RewardMember::class);
    }
}
