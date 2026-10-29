<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\AccessCode;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\File;
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
        'username',
        'given_username',
        'email',
        'password',
        'default_actor_id',
        'default_token_id',
        'character_data',
        'agent_link',
        'channel_id',
        'is_gm',
        'is_gamemaster',
        'access_reviewed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // Each signs the user in on its own, so none leaves the server.
        'username',
        'given_username',
        'pin_hash',
        'pattern_hash',
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
            'is_gamemaster' => 'boolean',
            'access_reviewed_at' => 'datetime',
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

    /** The account that signs in with any one of: username (any case), PIN or pattern. */
    public static function findByAccess(string $method, string $value): ?self
    {
        return match ($method) {
            'username' => self::whereRaw('lower(username) = ?', [mb_strtolower(trim($value))])->first(),
            'pin' => AccessCode::validPin($value) ? self::where('pin_hash', AccessCode::hash('pin', $value))->first() : null,
            'pattern' => AccessCode::validPattern($value) ? self::where('pattern_hash', AccessCode::hash('pattern', $value))->first() : null,
            default => null,
        };
    }

    /** Whether another account already signs in with this username, PIN or pattern. */
    public function accessTaken(string $method, string $value): bool
    {
        $other = self::findByAccess($method, $value);

        return $other !== null && ! $other->is($this);
    }

    public function setPin(?string $pin): void
    {
        $this->pin_hash = $pin === null ? null : AccessCode::hash('pin', $pin);
    }

    public function setPattern(?string $pattern): void
    {
        $this->pattern_hash = $pattern === null ? null : AccessCode::hash('pattern', $pattern);
    }

    /** The account that always exists and manages the others. */
    public static function gamemaster(): ?self
    {
        return self::where('is_gamemaster', true)->first();
    }

    /**
     * A player the gamemaster adds: the demo character until Foundry sends a real one, and a place
     * on the gamemaster's session, so they never type a session code.
     */
    public static function addPlayer(string $username): self
    {
        $demoData = File::exists(base_path('json.json'))
            ? json_decode(File::get(base_path('json.json')), true)
            : null;

        $player = self::create([
            'name' => $username,
            'username' => $username,
            'given_username' => $username,
            'character_data' => $demoData,
            'default_actor_id' => $demoData['_id'] ?? null,
            'default_token_id' => $demoData['_id'] ?? null,
        ]);

        if ($gamemaster = self::gamemaster()) {
            $session = Session::ensureActiveFor($gamemaster);
            if ($player->channel_id !== $session->code) {
                $session->joinPlayer($player);
            }
        }

        return $player;
    }
}
