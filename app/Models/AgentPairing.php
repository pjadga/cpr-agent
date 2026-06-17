<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentPairing extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nonce',
        'channel_id',
        'foundry_user_id',
        'foundry_user_name',
        'foundry_url',
        'claimed_by_user_id',
        'agent_link',
        'claimed_at',
        'expires_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AgentPairing $pairing) {
            if (empty($pairing->id)) {
                $pairing->id = (string) Str::uuid();
            }
        });
    }

    public function isExpired(): bool
    {
        return now()->gte($this->expires_at);
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    /**
     * Pairings that can still be claimed (unclaimed and not expired).
     */
    public function scopeClaimable(Builder $query): Builder
    {
        return $query->whereNull('claimed_at')->where('expires_at', '>', now());
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }
}
