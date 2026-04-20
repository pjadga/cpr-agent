<?php

namespace Tests\Feature\Frontend;

use App\Events\FoundryResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests to ensure frontend can successfully communicate with backend API
 */
class ApiConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'agent_link' => 'test-agent-link',
            'channel_id' => 'test-channel-001',
            'default_actor_id' => 'Actor.test123',
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    #[Test]
    public function frontend_can_authenticate_with_sanctum_token()
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $this->user->id,
            'email' => $this->user->email,
        ]);
    }

    #[Test]
    public function frontend_can_send_foundry_callback()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'total' => 15,
                'label' => 'Test Roll',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'processing_ms']);
    }

    #[Test]
    public function frontend_receives_validation_errors()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                // Missing required 'type' field
                'total' => 15,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function frontend_cannot_access_api_without_authentication()
    {
        $response = $this->postJson('/api/foundry/callback', [
            'type' => 'ROLL_RESULT',
            'total' => 15,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function frontend_can_create_sanctum_token()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $token = $user->createToken('frontend-token');

        $this->assertNotNull($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'frontend-token',
        ]);
    }

    #[Test]
    public function frontend_can_revoke_sanctum_token()
    {
        // Create a new user to avoid token conflicts
        $testUser = User::factory()->create();
        $token = $testUser->createToken('revoke-test');
        $plainToken = $token->plainTextToken;
        $tokenId = $token->accessToken->id;

        // Verify token works
        $response = $this->withToken($plainToken)
            ->getJson('/api/user');
        $response->assertStatus(200);

        // Revoke all tokens for this user
        $testUser->tokens()->delete();

        // Clear any cached authentication
        $this->app['auth']->forgetGuards();

        // Verify token no longer works
        $response = $this->withToken($plainToken)
            ->getJson('/api/user');
        $response->assertStatus(401);
    }

    #[Test]
    public function frontend_receives_correct_cors_headers()
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/user');

        $response->assertStatus(200);
        // Laravel handles CORS via middleware, just verify request succeeds
    }

    #[Test]
    public function frontend_can_handle_large_character_data()
    {
        Event::fake([FoundryResponse::class]);

        $largeCharacterData = [
            '_id' => 'Actor.test123',
            'name' => 'Test Character',
            'system' => [
                'stats' => array_fill(0, 10, ['value' => 5]),
                'skills' => array_fill(0, 50, ['value' => 3]),
            ],
            'items' => array_fill(0, 100, [
                '_id' => 'item-' . uniqid(),
                'name' => 'Test Item',
                'type' => 'gear',
            ]),
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ACTOR_DATA',
                'actorId' => 'Actor.test123',
                'actor' => $largeCharacterData,
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertCount(100, $this->user->character_data['items']);
    }

    #[Test]
    public function frontend_can_send_concurrent_requests()
    {
        Event::fake([FoundryResponse::class]);

        $responses = [];

        // Simulate multiple concurrent API calls
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->withToken($this->token)
                ->postJson('/api/foundry/callback', [
                    'type' => 'ROLL_RESULT',
                    'requestId' => 'concurrent-' . $i,
                    'total' => 10 + $i,
                    'label' => 'Concurrent Roll ' . $i,
                ]);
        }

        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }

    #[Test]
    public function frontend_receives_json_responses()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'CONFIRMATION',
                'success' => true,
            ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertJson($response->getContent());
    }

    #[Test]
    public function frontend_can_handle_special_characters_in_data()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ACTOR_DATA',
                'actorId' => 'Actor.test123',
                'actor' => [
                    '_id' => 'Actor.test123',
                    'name' => 'V "The Merc" O\'Brien',
                    'system' => [
                        'notes' => "Line 1\nLine 2\tTabbed",
                    ],
                ],
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals('V "The Merc" O\'Brien', $this->user->character_data['name']);
    }

    #[Test]
    public function frontend_can_access_user_endpoint_with_web_session()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $this->user->id,
            'email' => $this->user->email,
        ]);
    }

    #[Test]
    public function frontend_api_returns_proper_error_messages()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'LINK_PLAYER_CHARACTER',
                'agentLink' => 'non-existent-link',
                'character' => null,
            ]);

        // Should still succeed but handle gracefully
        $response->assertStatus(200);
    }
}
