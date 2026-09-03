<?php

namespace Tests\Feature\Api;

use App\Models\Config;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The image proxy hands a URL to the server and has it fetched, so the set of
 * URLs it will accept is a security boundary, not a convenience.
 */
class ImageProxyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['character_data' => ['name' => 'Test']]);

        Config::create([
            'user_id' => $this->user->id,
            'api_key' => 'test-key',
            'foundry_url' => 'http://192.168.1.50:30000',
        ]);

        Http::fake(['*' => Http::response('IMAGEBYTES', 200, ['Content-Type' => 'image/png'])]);
    }

    private function proxy(string $url)
    {
        return $this->actingAs($this->user)->get(route('proxy-image', ['url' => $url]));
    }

    #[Test]
    public function it_fetches_images_from_the_users_own_foundry(): void
    {
        // The reason the endpoint exists: a LAN Foundry on plain http, which
        // the browser will not load directly from an https page.
        $this->proxy('http://192.168.1.50:30000/tokens/solo.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_refuses_hosts_that_are_not_the_users_foundry(): void
    {
        // Without this the endpoint is a general-purpose fetcher pointed at
        // whatever the caller names, from inside the server's network.
        $this->proxy('http://169.254.169.254/latest/meta-data/')->assertForbidden();
        $this->proxy('http://127.0.0.1:6379/')->assertForbidden();
        $this->proxy('https://example.com/pixel.png')->assertForbidden();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_refuses_a_matching_host_on_a_different_port(): void
    {
        $this->proxy('http://192.168.1.50:8080/internal.png')->assertForbidden();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_refuses_non_http_schemes(): void
    {
        $this->proxy('file:///etc/passwd')->assertForbidden();

        Http::assertNothingSent();
    }

    #[Test]
    public function it_refuses_when_the_user_has_no_foundry_configured(): void
    {
        $this->user->config->update(['foundry_url' => null]);

        $this->proxy('http://192.168.1.50:30000/tokens/solo.png')->assertForbidden();

        Http::assertNothingSent();
    }
}
