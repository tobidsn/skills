<?php

namespace App\Models;

use App\Enums\BadgeLevel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'email',
        'contestant_id',
        'device_id',
        'code',
        'crew',
        'level',
        'total_energy',
        'total_customer_served',
        'total_score',
        'total_absent',
        'blacklist_level',
        'email_string',
        'full_name',
        'gender',
        'phone_number',
        'dob',
        'is_suspended',
        'suspension_ends_at',
        'suspend_level',
        'user_agent',
        'ip_address',
    ];

    public const MAX_SUSPENSION = 3;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suspension_ends_at' => 'datetime',
        ];
    }

    public function rewards()
    {
        return $this->hasMany(RewardMember::class);
    }

    public function campaigns()
    {
        return $this->hasMany(MemberCampaign::class);
    }

    public function campaignLogs()
    {
        return $this->hasMany(MemberCampaignLog::class);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }

    public function blockMembers()
    {
        return $this->hasOne(BlockMember::class);
    }

    public function checkins()
    {
        return $this->hasMany(MemberCheckin::class);
    }

    public function hasTodayCheckin(): bool
    {
        return $this->checkins()
            ->select('id', 'created_at')
            ->whereDate('created_at', today())
            ->exists();
    }

    public function firstCheckinDate(): ?Carbon
    {
        $firstCheckin = $this->checkins()->oldest()->first();

        return $firstCheckin ? $firstCheckin->created_at : null;
    }

    public function getMappedLevel(): ?string
    {
        return match ($this->level) {
            BadgeLevel::BASIC->value => 'crew_baru',
            BadgeLevel::SUPER->value => 'crew_hebat',
            BadgeLevel::EXPERT->value => 'crew_terbaik',
            default => $this->level,
        };
    }

    public function getMysteryBoxLevel(): int
    {
        return match ($this->level) {
            BadgeLevel::BASIC->value => 1,
            BadgeLevel::SUPER->value => 2,
            BadgeLevel::EXPERT->value => 3,
            default => 0,
        };
    }

    public function addEnergy(int $amount): void
    {
        $this->increment('total_energy', $amount);
    }

    public function addScore(int $amount): void
    {
        $this->increment('total_score', $amount);
    }

    public function incrementCustomerServed(): void
    {
        $this->increment('total_customer_served');
    }

    public function isSuspended()
    {
        return $this->is_suspended && $this->suspension_ends_at > now();
    }

    public function suspendForTwoHours()
    {
        $this->suspendForHours(2);
    }

    public function suspendForEightHours()
    {
        $this->suspendForHours(8);
    }

    public function suspendForHours($hours)
    {
        $this->is_suspended = true;
        $this->suspension_ends_at = Carbon::now()->addHours($hours);
        $this->suspend_level = $hours;
        $this->save();

        // +1 blacklist_level
        $this->increment('blacklist_level');
        // condition block member if blacklist_level is 3
        if ($this->blacklist_level === self::MAX_SUSPENSION) {
            $this->blockMember('Auto block member karena suspend 3x');
            activity()
                ->causedBy($this)
                ->log('Auto block member karena suspend 3x');
        }
    }

    public function liftSuspension()
    {
        $this->is_suspended = false;
        $this->suspension_ends_at = null;
        $this->suspend_level = 0;
        $this->save();
    }

    public function unblockMember()
    {
        $this->blockMembers()->delete();
    }

    public function blockMember($reason)
    {
        $this->unblockMember();

        return $this->blockMembers()->create(
            [
                'reason' => $reason,
                'ip' => request()->ip(),
            ]
        );
    }

    // Query scopes for filtering
    public function scopeActive($query)
    {
        return $query->where('is_suspended', false)
            ->whereNull('deleted_at');
    }

    public function scopeNotBlocked($query)
    {
        return $query->whereDoesntHave('blockMembers');
    }

    public function scopeByBlacklistLevel($query, $level)
    {
        return $query->where('blacklist_level', $level);
    }

    public function scopeByGender($query, $gender)
    {
        return $query->where('gender', $gender);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Static methods for common queries
    public static function findByEmail($email)
    {
        return static::where('email', $email)->first();
    }

    public static function findByContestantId($contestantId)
    {
        return static::where('contestant_id', $contestantId)->first();
    }

    public static function findByDeviceId($deviceId)
    {
        return static::where('device_id', $deviceId)->first();
    }

    public static function getActiveMembers()
    {
        return static::active()->notBlocked()->get();
    }

    public static function getSuspendedMembers()
    {
        return static::where('is_suspended', true)->get();
    }

    public static function getBlockedMembers()
    {
        return static::whereHas('blockMembers')->get();
    }
}
