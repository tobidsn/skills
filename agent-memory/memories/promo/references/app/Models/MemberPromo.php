<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

final class MemberPromo extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'promo_id',
        'type',
        'status',
        'voucher_code',
        'reward_member_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
        return $this->belongsTo(Voucher::class, 'voucher_code', 'code');
    }

    public function rewardMember(): BelongsTo
    {
        return $this->belongsTo(RewardMember::class, 'reward_member_id', 'id');
    }
}
