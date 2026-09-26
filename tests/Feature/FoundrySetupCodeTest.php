<?php

namespace Tests\Feature;

use App\Models\Config;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FoundrySetupCodeTest extends TestCase
{
    use RefreshDatabase;

    private function gmWithKey(): User
    {
        $gm = User::factory()->create(['is_gm' => true]);
        Config::create(['user_id' => $gm->id, 'api_key' => 'secret-token-123']);

        return $gm;
    }

    #[Test]
    public function gm_gets_a_decodable_setup_code_carrying_their_session_channel()
    {
        config([
            'app.url' => 'https://agent.example.com',
            'broadcasting.connections.reverb.key' => 'reverb-key-1',
        ]);

        $gm = $this->gmWithKey();

        $response = $this->actingAs($gm)->getJson(route('settings.foundry-setup-code'));

        $response->assertOk()->assertJson(['has_channel' => true]);

        $payload = json_decode(base64_decode($response->json('code')), true);
        $this->assertSame('https://agent.example.com', $payload['agentUrl']);

        // A session is ensured on the fly, and the code carries its channel,
        // the same value the GM's live broadcast channel now uses. A personal
        // (non-session) channel_id here used to 404 pairing registration.
        $session = Session::where('gm_user_id', $gm->id)->first();
        $this->assertNotNull($session);
        $this->assertSame($session->code, $payload['channelId']);
        $this->assertSame($session->code, $gm->fresh()->channel_id);

        $this->assertSame('secret-token-123', $payload['apiKey']);
        $this->assertSame('reverb-key-1', $payload['reverbKey']);
    }

    #[Test]
    public function setup_code_response_includes_a_short_code_redeemable_once()
    {
        $gm = $this->gmWithKey();

        $response = $this->actingAs($gm)->getJson(route('settings.foundry-setup-code'));
        $shortCode = $response->json('short_code');

        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/', $shortCode);

        // Public redemption returns the same payload as the base64 blob.
        $expected = json_decode(base64_decode($response->json('code')), true);
        $this->getJson("/api/setup-code/{$shortCode}")
            ->assertOk()
            ->assertJson($expected);

        // Single use: a second redemption fails.
        $this->getJson("/api/setup-code/{$shortCode}")
            ->assertNotFound()
            ->assertJson(['error' => 'setup_code_invalid']);
    }

    #[Test]
    public function short_code_redemption_is_case_insensitive()
    {
        $gm = $this->gmWithKey();

        $shortCode = $this->actingAs($gm)
            ->getJson(route('settings.foundry-setup-code'))
            ->json('short_code');

        $this->getJson('/api/setup-code/'.strtolower($shortCode))->assertOk();
    }

    #[Test]
    public function unknown_short_code_is_rejected()
    {
        $this->getJson('/api/setup-code/ZZZZ-ZZZZ')
            ->assertNotFound()
            ->assertJson(['error' => 'setup_code_invalid']);
    }

    #[Test]
    public function non_gm_is_forbidden()
    {
        $player = User::factory()->create(['is_gm' => false]);
        Config::create(['user_id' => $player->id, 'api_key' => 'x']);

        $this->actingAs($player)->getJson(route('settings.foundry-setup-code'))->assertForbidden();
    }

    #[Test]
    public function gm_without_api_key_is_told_to_generate_one()
    {
        $gm = User::factory()->create(['is_gm' => true]);

        $this->actingAs($gm)->getJson(route('settings.foundry-setup-code'))
            ->assertStatus(422)
            ->assertJson(['error' => 'no_api_key']);
    }
}
