<?php

namespace App\Models;

use App\Traits\HasPocket;
use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Interfaces\WalletFloat;
use Bavix\Wallet\Traits\CanPayFloat;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, Wallet, WalletFloat, Customer
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasPocket;
    use CanPayFloat;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'username',
        'country',
        'city',
        'address',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function referrer()
    {
        return $this->belongsTo(static::class, 'referrer_id');
    }

    public function referred()
    {
        return $this->hasMany(static::class, 'referrer_id');
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function membership()
    {
        return $this->hasOne(Membership::class)
            ->latest();
    }

    public function getValidTillAttribute(): ?Carbon
    {
        if (!$this->membership) {
            return null;
        }

        return $this->membership->valid_till;
    }

    public function getIsMemberAttribute(): bool
    {
        if ($this->valid_till) {
            return !$this->valid_till->isPast();
        }

        return false;
    }

    public function getTaskRemainingAttribute()
    {
        if (! $this->is_member) {
            return 0;
        }

        return $this->membership->task_limit - $this->membership->task_completed;
    }

    /**
     * @throws \Throwable
     */
    public function purchase(Plan $plan): Membership
    {
        throw_unless($plan->canBuy($this), "You Can't Purchase Free Plan.");
        throw_unless($this->purchasedPocket()->payFree($plan), "Error While Purchasing Plan #" . $plan->name);
        return $this->memberships()->create([
            'plan_id' => $plan->id,
            'tomorrow' => now()->addDay(),
            'task_limit' => $plan->task_limit,
            'valid_till' => $plan->validityFor($this),
            'type' => $plan->price ? 'premium' : 'free',
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function purchaseFreePlan(): Membership
    {
        $freePlan = Plan::query()->firstWhere('price', 0);
        throw_unless($freePlan, "There Is No Free Plan.");
        return $this->purchase($freePlan);
    }
}
