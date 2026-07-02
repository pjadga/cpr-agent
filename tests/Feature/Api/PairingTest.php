<?php

namespace Tests\Feature\Api;

use App\Events\CyberpunkAgentChannel;
use App\Models\AgentPairing;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PairingTest extends TestCase
{
    use RefreshDatabase;

    protected User $gm;

    protected User $player;

    protected Session $session;

    protected string $gmToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gm = User::factory()->create(['is_gm' => true]);
        $this->session = Session::create(['gm_user_id' => $this->gm->id]);
        $this->session->setFoundryConfig(['foundry_url' => 'https://gm-foundry.example.com']);

        $this->player = User::factory()->create(['is_gm' => false]);
        $this->gmToken = $this->gm->createToken('gm-token')->plainTextToken;
    }

    private function registerPairing(array $overrides = []): AgentPairing
    {
        return AgentPairing::create(array_merge([
            'nonce' => 'nonce-abc',
            'channel_id' => $this->session->code,
            'foundry_user_id' => 'foundry-user-1',
            'foundry_user_name' => 'Chris',
            'foundry_url' => 'https://gm-foundry.example.com',
            'expires_at' => now()->addMinutes(5),
        ], $overrides));
    }

    #[Test]
    public function gm_can_register_a_pairing()
    {
        $response = $this->withToken($this->gmToken)->postJson('/api/pairing/register', [
            'nonce' => 'nonce-xyz',
            'channel_id' => $this->session->code,
            'foundry_user_id' => 'foundry-user-9',
            'foundry_user_name' => 'Remote Player',
        ]);

        $response->assertOk()->assertJson(['success' => true, 'nonce' => 'nonce-xyz']);
        $this->assertDatabaseHas('agent_pairings', [
            'nonce' => 'nonce-xyz',
            'foundry_user_id' => 'foundry-user-9',
            'foundry_url' => 'https://gm-foundry.example.com', // inherited from session config
        ]);
    }

    #[Test]
    public function non_gm_cannot_register_a_pairing()
    {
        $playerToken = $this->player->createToken('p')->plainTextToken;

        $this->withToken($playerToken)->postJson('/api/pairing/register', [
            'nonce' => 'nope',
            'channel_id' => $this->session->code,
            'foundry_user_id' => 'x',
        ])->assertForbidden();
    }

    #[Test]
    public function gm_cannot_register_for_a_session_they_do_not_own()
    {
        $this->withToken($this->gmToken)->postJson('/api/pairing/register', [
            'nonce' => 'nope',
            'channel_id' => 'SOMEONE-ELSE',
            'foundry_user_id' => 'x',
        ])->assertNotFound();
    }

    #[Test]
    public function player_can_claim_a_pairing_and_is_configured_and_broadcast()
    {
        Event::fake([CyberpunkAgentChannel::class]);
        $this->registerPairing();

        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/pairing/claim', ['nonce' => 'nonce-abc']);

        $response->assertOk()->assertJson([
            'success' => true,
            'channel_id' => $this->session->code,
            'foundry_url' => 'https://gm-foundry.example.com',
        ]);

        $player = $this->player->fresh();
        $this->assertEquals($this->session->code, $player->channel_id);
        $this->assertNotEmpty($player->agent_link);
        $this->assertEquals('https://gm-foundry.example.com', $player->config->foundry_url);

        $this->assertDatabaseHas('agent_pairings', [
            'nonce' => 'nonce-abc',
            'claimed_by_user_id' => $player->id,
            'agent_link' => $player->agent_link,
        ]);

        Event::assertDispatched(CyberpunkAgentChannel::class, function ($e) use ($player) {
            return $e->payload['type'] === 'PAIR_AGENT'
                && $e->payload['foundryUserId'] === 'foundry-user-1'
                && $e->payload['agentLink'] === $player->agent_link
                && $e->payload['channel_id'] === $this->session->code;
        });
    }

    #[Test]
    public function claiming_increments_session_player_count()
    {
        $this->registerPairing();

        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/pairing/claim', ['nonce' => 'nonce-abc'])->assertOk();

        $this->assertEquals(1, $this->session->fresh()->player_count);
    }

    #[Test]
    public function unknown_nonce_is_rejected()
    {
        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/pairing/claim', ['nonce' => 'ghost'])
            ->assertStatus(404)
            ->assertJson(['error' => 'pairing_not_found']);
    }

    #[Test]
    public function expired_nonce_is_rejected()
    {
        $this->registerPairing(['expires_at' => now()->subMinute()]);

        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/pairing/claim', ['nonce' => 'nonce-abc'])
            ->assertStatus(410)
            ->assertJson(['error' => 'pairing_expired']);
    }

    #[Test]
    public function nonce_claimed_by_another_user_is_rejected()
    {
        $other = User::factory()->create();
        $this->registerPairing([
            'claimed_by_user_id' => $other->id,
            'claimed_at' => now(),
            'agent_link' => 'other-link',
        ]);

        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/pairing/claim', ['nonce' => 'nonce-abc'])
            ->assertStatus(409)
            ->assertJson(['error' => 'pairing_already_claimed']);
    }

    #[Test]
    public function reclaiming_own_pairing_is_idempotent()
    {
        $this->registerPairing([
            'claimed_by_user_id' => $this->player->id,
            'claimed_at' => now(),
            'agent_link' => 'my-link',
        ]);

        $this->actingAs($this->player, 'sanctum')
            ->postJson('/api/pairing/claim', ['nonce' => 'nonce-abc'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
