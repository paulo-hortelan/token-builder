<?php

namespace Shetabit\TokenBuilder\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'token',
        'expired_at',
        'usage_count',
        'max_usage_limit',
        'data',
        'type',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'expired_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'usage_count' => 'integer',
        'max_usage_limit' => 'integer',
        'data' => 'json',
    ];

    /**
     * Retrieve related model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function tokenable()
    {
        return $this->morphTo();
    }

    /**
     * Mark token as used
     *
     * @return mixed
     */
    public function use()
    {
        $this->increment('usage_count');

        return $this;
    }

    /**
     * Determine if token has used
     *
     * @return bool
     */
    public function hasUsed()
    {
        return $this->usage_count > 0 ;
    }

    /**
     * Determine if token has expired
     *
     * @return bool
     */
    public function hasExpired()
    {
        return $this->expired_at === null ? false : now()->gt($this->expired_at);
    }

    /**
     * Mark token as expired
     *
     * @return mixed
     */
    public function markAsExpired()
    {
        $this->forceFill(['expired_at' => now()])->save();

        return $this;
    }

    /**
     * Determine usage limit is enabled.
     *
     * @return bool
     */
    public function hasMaxUsageLimit()
    {
        return $this->max_usage_limit > 0;
    }

    /**
     * Determine usage limit has exceed.
     *
     * @return bool
     */
    public function hasExceedMaxUsage()
    {
        $maxUsageLimit = $this->max_usage_limit;
        $usageCount = $this->usage_count;

        return $this->hasMaxUsageLimit() && ($usageCount >= $maxUsageLimit);
    }

    /**
     * Determine token is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return !($this->hasExpired() || $this->hasExceedMaxUsage());
    }

    /**
     * Filter valid tokens
     *
     * @param $query
     *
     * @return mixed
     */
    public function scopeValid($query)
    {
        return $query
            ->where(function($query) { // check usage limit
                return $query
                    ->where('max_usage_limit', '=', '0')
                    ->orWhereRaw('usage_count < max_usage_limit');
            })
            ->where(function($query) { // check expiration time
                return $query
                    ->whereNull('expired_at')
                    ->orWhere('expired_at', '>', now());
            });
    }

    /**
     * Increase minutes to token expiration date
     *
     * @return mixed
     */
    public function addMinutes($minutes)
    {
        $expiresAt = Carbon::parse($this->expired_at);
        $this->forceFill(['expired_at' => $expiresAt->addMinutes($minutes)])->save();

        return $this;
    }    
}
