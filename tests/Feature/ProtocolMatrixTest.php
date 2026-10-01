<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\Session;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Agent and the GM's Foundry are two separate servers, and there is no rule
 * that says they share a scheme. All three combinations happen in the wild:
 *
 *   http  Agent -> https Foundry   LAN-hosted Agent, Foundry behind a tunnel
 *   http  Agent -> http  Foundry   both on the LAN, the plain self-host case
 *   https Agent -> http  Foundry   hosted/Herd/Tailscale Agent, LAN Foundry
 *
 * Each one breaks something different, and the failures are quiet: images stop
 * loading, the session cookie is silently discarded, the WebSocket dials a port
 * nothing listens on. These tests pin the server-side half of that matrix. The
 * browser-side half (image proxying, mixed-content probes, Reverb endpoint
 * derivation) is in tests/Frontend/ProtocolMatrix.test.js.
 */
class ProtocolMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Re-evaluate a config file with a given environment, then restore it.
     *
     * config/session.php derives `secure` from APP_URL at load time, so the
     * only honest way to test that rule is to load the file again under each
     * environment rather than to restate the expression here.
     *
     * @param  array<string, string|null>  $env  null unsets the variable
     * @return array<string, mixed>
     */
    private function configUnderEnv(string $file, array $env): array
    {
        $restore = [];

        foreach ($env as $key => $value) {
            $putenv = getenv($key);

            $restore[$key] = [
                array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
                array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
                $putenv === false ? null : $putenv,
            ];

            // Laravel's Env repository reads $_SERVER, $_ENV *and* putenv, so
            // all three have to move together or the .env value leaks back in.
            if ($value === null) {
                unset($_SERVER[$key], $_ENV[$key]);
                putenv($key);
            } else {
                $_SERVER[$key] = $value;
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        try {
            return require config_path($file);
        } finally {
            foreach ($restore as $key => [$server, $envValue, $putenv]) {
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }

                if ($envValue === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $envValue;
                }

                if ($putenv === null) {
                    putenv($key);
                } else {
                    putenv("{$key}={$putenv}");
                }
            }
        }
    }

    /**
     * Render /profile and return its CSP header.
     *
     * CSP is opt-in (`CSP_ENABLED`, default false), so the header only exists
     * where someone has switched it on, the hosted instance. Turn it on here
     * rather than skip, or the policy goes untested everywhere.
     */
    private function cspFor(User $user): ?string
    {
        config(['csp.enabled' => true]);

        return $this->actingAs($user)->get('/profile')->headers->get('Content-Security-Policy');
    }

    private function useRealBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        $this->app->forgetInstance(BroadcastManager::class);
        Broadcast::clearResolvedInstances();
    }

    private function userWithFoundry(string $foundryUrl, bool $isGm = false): User
    {
        $user = User::factory()->create([
            'is_gm' => $isGm,
            'character_data' => ['name' => 'Test'],
        ]);

        Config::create([
            'user_id' => $user->id,
            'api_key' => 'test-key',
            'foundry_url' => $foundryUrl,
        ]);

        return $user;
    }

    // =================================================================
    // Session cookie: the failure that looks like "login is broken"
    // =================================================================

    /**
     * A Secure cookie on a plain-http origin is not stored by the browser at
     * all. Every request then arrives session-less: login bounces back to
     * login, POSTs 419, and /api/broadcasting/auth 401s so the sheet never
     * populates. None of that names the scheme as the cause, which is why the
     * flag must follow APP_URL rather than default to true.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function appUrlSchemes(): array
    {
        return [
            'http on a LAN IP' => ['http://192.168.1.50:8000', false],
            'http on a hostname' => ['http://cyberpunk.test', false],
            'http on localhost' => ['http://localhost:8000', false],
            'https hosted' => ['https://agent.example.com', true],
            'https ddev' => ['https://cyberpunk.ddev.site', true],
        ];
    }

    #[Test]
    #[DataProvider('appUrlSchemes')]
    public function session_cookie_is_secure_only_when_the_agent_is_served_over_https(string $appUrl, bool $expected): void
    {
        $config = $this->configUnderEnv('session.php', [
            'APP_URL' => $appUrl,
            'SESSION_SECURE_COOKIE' => null,
        ]);

        $this->assertSame($expected, (bool) $config['secure'], "APP_URL {$appUrl}");
    }

    #[Test]
    public function an_explicit_session_secure_cookie_still_wins_over_the_derivation(): void
    {
        // The escape hatch has to keep working: someone terminating TLS in a
        // way APP_URL does not describe needs to be able to say so.
        $config = $this->configUnderEnv('session.php', [
            'APP_URL' => 'http://192.168.1.50:8000',
            'SESSION_SECURE_COOKIE' => 'true',
        ]);

        $this->assertTrue((bool) $config['secure']);
    }

    #[Test]
    public function a_missing_app_url_does_not_produce_a_secure_cookie(): void
    {
        // A half-written .env should fail toward "login works", not toward the
        // silent no-cookie state.
        $config = $this->configUnderEnv('session.php', [
            'APP_URL' => null,
            'SESSION_SECURE_COOKIE' => null,
        ]);

        $this->assertFalse((bool) $config['secure']);
    }

    // =================================================================
    // Reverb: what the browser and the Bridge are told to dial
    // =================================================================

    #[Test]
    public function reverb_frontend_config_is_empty_on_a_single_origin_install(): void
    {
        // Nothing set means the client derives host, port and scheme from
        // window.location, which is the only answer that is correct on all
        // three topologies. Anything non-null here overrides that derivation,
        // so an accidental default is not a harmless default.
        $config = $this->configUnderEnv('broadcasting.php', [
            'VITE_REVERB_HOST' => null,
            'VITE_REVERB_PORT' => null,
            'VITE_REVERB_SCHEME' => null,
            'REVERB_APP_KEY' => 'app-key-1',
        ]);

        $frontend = $config['connections']['reverb']['frontend'];

        $this->assertSame('app-key-1', $frontend['key']);
        $this->assertNull($frontend['host']);
        $this->assertNull($frontend['port']);
        $this->assertNull($frontend['scheme']);
    }

    #[Test]
    public function reverb_frontend_config_passes_through_a_genuine_split_origin(): void
    {
        $config = $this->configUnderEnv('broadcasting.php', [
            'VITE_REVERB_HOST' => 'ws.example.com',
            'VITE_REVERB_PORT' => '8085',
            'VITE_REVERB_SCHEME' => 'https',
            'REVERB_APP_KEY' => 'app-key-1',
        ]);

        $frontend = $config['connections']['reverb']['frontend'];

        $this->assertSame('ws.example.com', $frontend['host']);
        $this->assertSame('8085', $frontend['port']);
        $this->assertSame('https', $frontend['scheme']);
    }

    #[Test]
    public function blank_vite_reverb_values_read_as_unset_rather_than_as_empty_strings(): void
    {
        // `VITE_REVERB_HOST=` in a .env is how people "turn it off". An empty
        // string reaching the browser is not the same as absent: it would beat
        // the ?? fallback chain and produce wsHost: ''.
        $config = $this->configUnderEnv('broadcasting.php', [
            'VITE_REVERB_HOST' => '',
            'VITE_REVERB_PORT' => '',
            'VITE_REVERB_SCHEME' => '',
        ]);

        $frontend = $config['connections']['reverb']['frontend'];

        $this->assertNull($frontend['host']);
        $this->assertNull($frontend['port']);
        $this->assertNull($frontend['scheme']);
    }

    #[Test]
    public function the_injected_reverb_script_omits_null_values_entirely(): void
    {
        // partials/reverb-config.blade.php array_filters nulls out, so
        // window.reverbConfig has no key at all rather than an explicit null.
        // bootstrap.js uses ??, which treats both the same, but only if the
        // filter is actually there.
        config(['broadcasting.connections.reverb.frontend' => [
            'key' => 'app-key-1',
            'host' => null,
            'port' => null,
            'scheme' => null,
        ]]);

        $rendered = view('partials.reverb-config')->render();

        $this->assertStringContainsString('app-key-1', $rendered);
        $this->assertStringNotContainsString('"host"', $rendered);
        $this->assertStringNotContainsString('"scheme"', $rendered);
        $this->assertStringNotContainsString('"port"', $rendered);
    }

    // =================================================================
    // Foundry setup code: the address the Bridge is handed
    // =================================================================

    /**
     * @return array<string, array{0: string}>
     */
    public static function agentUrls(): array
    {
        return [
            'http LAN IP' => ['http://192.168.1.50:8000'],
            'http hostname' => ['http://cyberpunk.test'],
            'https hosted' => ['https://agent.example.com'],
            'https non-standard port' => ['https://agent.example.com:8443'],
        ];
    }

    #[Test]
    #[DataProvider('agentUrls')]
    public function the_setup_code_hands_the_bridge_app_url_verbatim(string $appUrl): void
    {
        // The Bridge derives its WebSocket scheme, host and port from this one
        // string. It used to be rebuilt from the *Reverb* scheme plus
        // APP_DOMAIN, so a plain-http self-host was handed https://<domain>,
        // an address its Bridge could never reach.
        config([
            'app.url' => $appUrl,
            'broadcasting.connections.reverb.key' => 'reverb-key-1',
        ]);

        $gm = $this->userWithFoundry('http://192.168.1.60:30000', isGm: true);

        $payload = $this->decodeSetupCode($gm);

        $this->assertSame($appUrl, $payload['agentUrl']);
    }

    #[Test]
    public function a_trailing_slash_on_app_url_is_trimmed_before_it_reaches_the_bridge(): void
    {
        // The Bridge builds `${apiUrl}/api/broadcasting/auth`; a trailing slash
        // there produces a double slash, which some proxies 404.
        config(['app.url' => 'https://agent.example.com/']);

        $gm = $this->userWithFoundry('http://192.168.1.60:30000', isGm: true);

        $this->assertSame('https://agent.example.com', $this->decodeSetupCode($gm)['agentUrl']);
    }

    #[Test]
    public function the_setup_code_omits_the_reverb_port_on_a_single_origin_install(): void
    {
        // Omitted, not zero and not 443: the Bridge only overrides its derived
        // port `if (data.reverbPort)`, so a null keeps it deriving from the
        // Agent URL, the right answer on http and https alike.
        config(['app.url' => 'https://agent.example.com', 'vite.reverb.port' => null]);

        $gm = $this->userWithFoundry('http://192.168.1.60:30000', isGm: true);

        $this->assertNull($this->decodeSetupCode($gm)['reverbPort']);
    }

    #[Test]
    public function the_setup_code_carries_the_reverb_port_when_reverb_is_genuinely_elsewhere(): void
    {
        config(['app.url' => 'https://agent.example.com', 'vite.reverb.port' => '8085']);

        $gm = $this->userWithFoundry('http://192.168.1.60:30000', isGm: true);

        $this->assertSame('8085', $this->decodeSetupCode($gm)['reverbPort']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSetupCode(User $gm): array
    {
        $response = $this->actingAs($gm)->getJson(route('settings.foundry-setup-code'));
        $response->assertOk();

        return json_decode(base64_decode($response->json('code')), true);
    }

    // =================================================================
    // TLS termination: the Agent behind Herd, DDEV, Tailscale, a tunnel
    // =================================================================

    #[Test]
    public function a_forwarded_https_request_is_treated_as_secure(): void
    {
        // Herd secure, the DDEV router, Tailscale Serve and every tunnel speak
        // plain http to PHP and set X-Forwarded-Proto. Without trusted proxies
        // the app believes it is on http, generates http URLs into an https
        // page, and every one of them is blocked as mixed content.
        $gm = $this->userWithFoundry('http://192.168.1.60:30000');

        $response = $this->actingAs($gm)
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://cyberpunk.test/profile');

        $response->assertOk();
        $this->assertTrue($response->baseRequest->isSecure());
    }

    #[Test]
    public function an_unforwarded_request_is_not_treated_as_secure(): void
    {
        $gm = $this->userWithFoundry('http://192.168.1.60:30000');

        $response = $this->actingAs($gm)->get('http://cyberpunk.test/profile');

        $response->assertOk();
        $this->assertFalse($response->baseRequest->isSecure());
    }

    // =================================================================
    // CSP: the Foundry origin the page is allowed to load images from
    // =================================================================

    /**
     * @return array<string, array{0: string}>
     */
    public static function foundryUrls(): array
    {
        return [
            'http LAN Foundry' => ['http://192.168.1.60:30000'],
            'https tunnelled Foundry' => ['https://foundry.example.com'],
            'https Foundry on a port' => ['https://foundry.example.com:30000'],
        ];
    }

    #[Test]
    #[DataProvider('foundryUrls')]
    public function the_csp_allows_images_from_the_current_users_own_foundry(string $foundryUrl): void
    {
        $user = $this->userWithFoundry($foundryUrl);

        $csp = $this->cspFor($user);

        $this->assertNotNull($csp);
        $this->assertStringContainsString($foundryUrl, $csp);
    }

    #[Test]
    public function the_csp_does_not_leak_another_users_foundry(): void
    {
        // This used to read Config::first(), so on a shared instance one
        // arbitrary GM's Foundry was allow-listed for everybody and every other
        // GM's token art was blocked.
        $this->userWithFoundry('https://someone-else.example.com');
        $mine = $this->userWithFoundry('http://192.168.1.60:30000');

        $csp = $this->cspFor($mine);

        $this->assertStringContainsString('http://192.168.1.60:30000', $csp);
        $this->assertStringNotContainsString('someone-else.example.com', $csp);
    }

    /**
     * img-src as the /embed page gets it, framed by a Foundry at $referer.
     */
    private function embedImgSrc(?string $referer): string
    {
        config(['csp.enabled' => true]);
        $headers = $referer ? ['Referer' => $referer] : [];
        $csp = $this->get('/embed', $headers)->headers->get('Content-Security-Policy');
        preg_match('/img-src[^;]*/', (string) $csp, $m);

        return $m[0] ?? '';
    }

    #[Test]
    public function the_embed_allows_images_from_the_foundry_that_frames_it(): void
    {
        // The embed is auth-less, so there is no user config to allow-list.
        // Without the parent origin every item icon was blocked by img-src.
        $imgSrc = $this->embedImgSrc('http://localhost:30000/game');

        $this->assertStringContainsString('http://localhost:30000', $imgSrc);
        $this->assertStringNotContainsString('/game', $imgSrc);
    }

    #[Test]
    public function the_embed_ignores_a_missing_or_non_http_referer(): void
    {
        $this->assertStringNotContainsString('localhost', $this->embedImgSrc(null));
        $this->assertStringNotContainsString('javascript', $this->embedImgSrc('javascript:alert(1)'));
    }

    #[Test]
    public function only_the_embed_takes_its_image_origin_from_the_referer(): void
    {
        config(['csp.enabled' => true]);
        $csp = $this->get('/login', ['Referer' => 'http://evil.example.com/'])->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('evil.example.com', (string) $csp);
    }

    #[Test]
    public function a_user_with_no_foundry_configured_still_gets_a_usable_img_policy(): void
    {
        $user = User::factory()->create(['character_data' => ['name' => 'Test']]);

        $csp = $this->cspFor($user);

        $this->assertNotNull($csp);
        $this->assertMatchesRegularExpression('/img-src[^;]*\'self\'/', $csp);
    }

    // =================================================================
    // Image proxy: the whole reason https Agent -> http Foundry works
    // =================================================================

    #[Test]
    public function the_proxy_fetches_a_plain_http_foundry_for_an_https_page(): void
    {
        // Topology C. The browser refuses http images on an https page, so the
        // server fetches them instead. This is the case the proxy exists for.
        Http::fake(['*' => Http::response('IMAGEBYTES', 200, ['Content-Type' => 'image/png'])]);

        $user = $this->userWithFoundry('http://192.168.1.60:30000');

        $this->actingAs($user)
            ->get(route('proxy-image', ['url' => 'http://192.168.1.60:30000/tokens/solo.png']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        Http::assertSentCount(1);
    }

    #[Test]
    public function the_proxy_also_serves_an_https_foundry(): void
    {
        // Topology A does not need the proxy for mixed content, but ngrok's
        // interstitial still routes through it, so https targets must work.
        Http::fake(['*' => Http::response('IMAGEBYTES', 200, ['Content-Type' => 'image/webp'])]);

        $user = $this->userWithFoundry('https://foundry.example.com');

        $this->actingAs($user)
            ->get(route('proxy-image', ['url' => 'https://foundry.example.com/tokens/solo.webp']))
            ->assertOk();

        Http::assertSentCount(1);
    }

    #[Test]
    public function the_proxy_matches_an_explicit_default_port_against_an_implicit_one(): void
    {
        // https://host and https://host:443 are the same origin. A textual
        // comparison would reject one of them, and which one you get depends on
        // whether the GM pasted the URL from the browser bar or typed it.
        Http::fake(['*' => Http::response('IMAGEBYTES', 200, ['Content-Type' => 'image/png'])]);

        $user = $this->userWithFoundry('https://foundry.example.com');

        $this->actingAs($user)
            ->get(route('proxy-image', ['url' => 'https://foundry.example.com:443/tokens/solo.png']))
            ->assertOk();

        $user->config->update(['foundry_url' => 'http://foundry.example.com:80']);

        $this->actingAs($user)
            ->get(route('proxy-image', ['url' => 'http://foundry.example.com/tokens/solo.png']))
            ->assertOk();
    }

    #[Test]
    public function a_scheme_swap_across_default_ports_is_refused(): void
    {
        // Documenting a sharp edge rather than endorsing it. The guard says
        // scheme need not match, but the port defaults *from* the scheme, so
        // https://host (443) never matches http://host (80). In practice image
        // URLs are built from foundry_url so the schemes agree; a GM who stores
        // https:// and whose actor art carries absolute http:// links would see
        // those images 403 instead of proxying.
        Http::fake(['*' => Http::response('IMAGEBYTES', 200, ['Content-Type' => 'image/png'])]);

        $user = $this->userWithFoundry('https://foundry.example.com');

        $this->actingAs($user)
            ->get(route('proxy-image', ['url' => 'http://foundry.example.com/tokens/solo.png']))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    #[Test]
    public function a_scheme_swap_on_a_shared_explicit_port_is_allowed(): void
    {
        // The counterpart: when the port is stated on both sides the scheme
        // genuinely is ignored, which is what a tunnel flipping http/https in
        // front of the same Foundry port needs.
        Http::fake(['*' => Http::response('IMAGEBYTES', 200, ['Content-Type' => 'image/png'])]);

        $user = $this->userWithFoundry('http://foundry.example.com:30000');

        $this->actingAs($user)
            ->get(route('proxy-image', ['url' => 'https://foundry.example.com:30000/tokens/solo.png']))
            ->assertOk();

        Http::assertSentCount(1);
    }

    #[Test]
    public function the_proxy_is_not_a_general_purpose_fetcher_on_any_topology(): void
    {
        // The endpoint takes a URL and has the server request it from inside
        // the server's own network. Registration is open on the hosted
        // instance, so the host pin is the only thing between that and SSRF.
        Http::fake(['*' => Http::response('IMAGEBYTES', 200, ['Content-Type' => 'image/png'])]);

        $user = $this->userWithFoundry('https://foundry.example.com');

        foreach ([
            'http://169.254.169.254/latest/meta-data/',
            'http://127.0.0.1:6379/',
            'https://foundry.example.com.evil.test/x.png',
            'https://evil.test/?x=foundry.example.com',
        ] as $url) {
            $this->actingAs($user)
                ->get(route('proxy-image', ['url' => $url]))
                ->assertForbidden();
        }

        Http::assertNothingSent();
    }

    // =================================================================
    // Base URL handed to the browser for image building
    // =================================================================

    #[Test]
    #[DataProvider('foundryUrls')]
    public function getbaseurl_returns_the_foundry_url_with_its_scheme_intact(string $foundryUrl): void
    {
        // useImageProxy decides whether to proxy by comparing this scheme
        // against window.location.protocol. Normalising the scheme here would
        // make that decision on the wrong information.
        $user = $this->userWithFoundry($foundryUrl);

        $this->actingAs($user)->getJson('/api/getbaseurl')
            ->assertOk()
            ->assertSee($foundryUrl, escape: false);
    }

    #[Test]
    public function a_trailing_slash_is_stripped_from_a_saved_foundry_url(): void
    {
        // getImageUrl concatenates baseUrl + '/tokens/x.png'; a stored trailing
        // slash yields a double slash, and Foundry 404s it.
        $user = $this->userWithFoundry('http://192.168.1.60:30000');

        $this->actingAs($user)->post(route('config.store'), [
            'foundry_url' => 'https://foundry.example.com/',
        ]);

        $this->assertSame('https://foundry.example.com', $user->fresh()->config->foundry_url);
    }

    // =================================================================
    // Broadcast auth, which every topology needs to survive
    // =================================================================

    #[Test]
    public function the_gm_bridge_authorises_its_channel_with_a_bearer_token(): void
    {
        // The Bridge runs inside Foundry's page and has no Agent session
        // cookie, only a Sanctum token. That is what keeps it working on a
        // plain-http Agent, where the cookie is (correctly) not Secure, and on
        // an https Agent framed by an http Foundry, where a SameSite cookie
        // would not be sent cross-site anyway.
        $gm = $this->userWithFoundry('http://192.168.1.60:30000', isGm: true);
        $session = Session::ensureActiveFor($gm);

        $this->withHeader('Authorization', 'Bearer '.$gm->createToken('FoundryClient')->plainTextToken)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-cpr-agent.'.$session->code,
            ])
            ->assertOk();
    }

    #[Test]
    public function the_players_browser_authorises_the_same_channel_with_a_session_cookie(): void
    {
        $gm = $this->userWithFoundry('http://192.168.1.60:30000', isGm: true);
        $session = Session::ensureActiveFor($gm);

        $player = $this->userWithFoundry('http://192.168.1.60:30000');
        $player->update(['channel_id' => $session->code]);

        $this->actingAs($player)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-cpr-agent.'.$session->code,
            ])
            ->assertOk();
    }

    #[Test]
    public function a_bearer_token_does_not_authorise_someone_elses_channel(): void
    {
        // Channel isolation is the multi-user boundary, and it is credential
        // based rather than transport based, so it holds identically on every
        // topology in this file.
        $gm = $this->userWithFoundry('http://192.168.1.60:30000', isGm: true);
        Session::ensureActiveFor($gm);

        $otherGm = $this->userWithFoundry('https://foundry.example.com', isGm: true);
        $otherSession = Session::ensureActiveFor($otherGm);

        // phpunit.xml forces BROADCAST_CONNECTION=null, and the null
        // broadcaster's auth() is a no-op, it returns 200 without ever
        // consulting routes/channels.php. Point at a real broadcaster so the
        // channel callback actually runs; no network is involved in signing.
        $this->useRealBroadcaster();

        $this->withHeader('Authorization', 'Bearer '.$gm->createToken('FoundryClient')->plainTextToken)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-cpr-agent.'.$otherSession->code,
            ])
            ->assertForbidden();
    }
}
