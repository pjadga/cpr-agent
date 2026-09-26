<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Session extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     */
    protected $table = 'game_sessions';

    protected $fillable = [
        'id',
        'code',
        'gm_user_id',
        'foundry_config',
        'player_count',
        'expires_at',
    ];

    protected $casts = [
        'foundry_config' => 'array',
        'player_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Session $session) {
            if (empty($session->id)) {
                $session->id = (string) Str::uuid();
            }
            if (empty($session->code)) {
                $session->code = self::generateCode();
            }
        });

        static::deleting(function (Session $session) {
            // Disconnect players from the channel and drop their session-scoped
            // character binding. The GM is on this same channel (they join their
            // own session on create) but is handled separately below so their
            // character selection survives.
            User::where('channel_id', $session->code)
                ->where('id', '!=', $session->gm_user_id)
                ->update([
                    'channel_id' => null,
                    'default_actor_id' => null,
                    'default_token_id' => null,
                ]);

            // GM: leave the dead channel (a fresh personal one regenerates on read)
            // but keep their actor/token selection.
            if ($session->gm_user_id) {
                User::where('id', $session->gm_user_id)
                    ->where('channel_id', $session->code)
                    ->update(['channel_id' => null]);
            }
        });
    }

    /**
     * Generate a unique 7-char session code (e.g., "NEON-7X2").
     */
    public static function generateCode(): string
    {
        $words = ['neon', 'cyber', 'punk', 'chrome', 'glitch', 'static', 'blade', 'runner'];
        do {
            $word = $words[array_rand($words)];
            $suffix = strtoupper(Str::random(3));
            $code = "{$word}-{$suffix}";
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * Return the GM's active session, creating one if none exists. Keeps the
     * GM on their own session's channel (the unifying invariant: GM, players
     * and the Foundry Bridge all share cpr-agent.{code}) and keeps the
     * session's Foundry config in step with the GM's latest settings.
     *
     * This is the GM bootstrap: called on explicit "Create Session" and
     * automatically when a GM saves their connection settings, so a fresh GM
     * never has to sequence session-vs-setup-code manually.
     */
    public static function ensureActiveFor(User $gm): self
    {
        $session = self::where('gm_user_id', $gm->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $session) {
            $session = self::create([
                'gm_user_id' => $gm->id,
                'expires_at' => now()->addDays(30),
            ]);
        }

        // Self-heal: the GM always lives on their own session's channel.
        if ($gm->channel_id !== $session->code) {
            $gm->channel_id = $session->code;
            $gm->save();
        }

        $session->syncFoundryConfigFor($gm);

        return $session;
    }

    /**
     * Join a player to this session. Returns the session data needed for auto-configuration.
     */
    public function joinPlayer(User $player): array
    {
        // Update user with this session's channel code
        $player->channel_id = $this->code;
        $player->save();

        // Increment player count
        $this->increment('player_count');

        return [
            'success' => true,
            'channel_id' => $this->code,
            'foundry_config' => $this->foundry_config ?? [],
            'message' => "Joined session {$this->code}. Your Agent Link has been established.",
        ];
    }

    /**
     * Pull the GM's saved Foundry settings onto the session so joining players
     * auto-configure. Called automatically on session create and whenever the GM
     * saves their connection settings, no manual "sync" step required.
     *
     * No-op until the GM has a Foundry URL, so an empty session is never written.
     */
    public function syncFoundryConfigFor(User $gm): void
    {
        $foundryUrl = $gm->config?->foundry_url;

        if (empty($foundryUrl)) {
            return;
        }

        $this->setFoundryConfig([
            'foundry_url' => $foundryUrl,
            'reverb_key' => config('broadcasting.connections.reverb.key'),
        ]);
    }

    /**
     * Set the GM's Foundry Bridge configuration so players can auto-configure.
     */
    public function setFoundryConfig(array $config): void
    {
        // Only store non-sensitive config (no API keys)
        $safe = [
            'foundry_url' => $config['foundry_url'] ?? null,
            'reverb_host' => $config['reverb_host'] ?? null,
            'reverb_port' => $config['reverb_port'] ?? 443,
            'reverb_use_ssl' => $config['reverb_use_ssl'] ?? true,
            'reverb_key' => $config['reverb_key'] ?? null,
        ];

        $this->update(['foundry_config' => $safe]);
    }

    public function gm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gm_user_id');
    }

    /**
     * Check if session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->gte($this->expires_at);
    }
}
