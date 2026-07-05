<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'default_actor_id',
        'default_token_id',
        'character_data',
        'agent_link',
        'channel_id',
        'is_gm',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'character_data' => 'array',
            'is_gm' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($user) {
            if (empty($user->agent_link)) {
                $user->agent_link = (string) Str::uuid();
            }
            if (empty($user->channel_id)) {
                $user->channel_id = self::generateChannelId();
            }
        });
    }

    public static function generateChannelId(): string
    {
        $words = [
            'neon', 'cyber', 'punk', 'chrome', 'glitch', 'static', 'blade', 'runner',
            'hacker', 'grid', 'data', 'wire', 'matrix', 'synth', 'wave', 'alloy',
            'neural', 'link', 'deck', 'jack', 'signal', 'buffer', 'core', 'port',
            'node', 'flux', 'spark', 'beam', 'circuit', 'code',
        ];

        $word1 = $words[array_rand($words)];
        $word2 = $words[array_rand($words)];
        $number = rand(100, 999);

        return "{$word1}-{$word2}-{$number}";
    }

    /**
     * Get the user's agent link ID.
     * Ensures one is generated and saved if it's missing.
     */
    public function getAgentLinkAttribute($value)
    {
        if (empty($value)) {
            $value = (string) Str::uuid();
            $this->agent_link = $value;
            if ($this->exists) {
                $this->save();
            }
        }

        return $value;
    }

    /**
     * Get the user's channel ID.
     * Ensures one is generated and saved if it's missing.
     */
    public function getChannelIdAttribute($value)
    {
        if (empty($value)) {
            $value = self::generateChannelId();
            $this->channel_id = $value;
            if ($this->exists) {
                $this->save();
            }
        }

        return $value;
    }

    /**
     * Whether this user is the GM of a currently active (non-expired) session.
     * Used to protect a session-owning GM from being auto-demoted by Foundry
     * sync payloads (e.g. testing the world from a player login).
     */
    public function ownsActiveSession(): bool
    {
        return Session::where('gm_user_id', $this->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function getActorId(): string
    {
        return $this->default_actor_id ?? '';
    }

    public function getTokenId(): string
    {
        return $this->default_token_id ?? '';
    }

    public function config(): HasOne
    {
        return $this->hasOne(Config::class);
    }
}
