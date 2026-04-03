<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for User model
 */
class UserModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_generates_agent_link_on_creation()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->agent_link);
        $this->assertIsString($user->agent_link);
        // UUID format check
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $user->agent_link
        );
    }

    #[Test]
    public function user_generates_channel_id_on_creation()
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->channel_id);
        $this->assertIsString($user->channel_id);
        // Format: word-word-number
        $this->assertMatchesRegularExpression('/^[a-z]+-[a-z]+-\d{3}$/', $user->channel_id);
    }

    #[Test]
    public function user_can_have_custom_agent_link()
    {
        $customLink = 'custom-agent-link-123';
        $user = User::factory()->create([
            'agent_link' => $customLink,
        ]);

        $this->assertEquals($customLink, $user->agent_link);
    }

    #[Test]
    public function user_can_have_custom_channel_id()
    {
        $customChannel = 'custom-channel-001';
        $user = User::factory()->create([
            'channel_id' => $customChannel,
        ]);

        $this->assertEquals($customChannel, $user->channel_id);
    }

    #[Test]
    public function user_character_data_is_cast_to_array()
    {
        $characterData = [
            'name' => 'V',
            'system' => [
                'stats' => ['body' => ['value' => 8]],
            ],
        ];

        $user = User::factory()->create([
            'character_data' => $characterData,
        ]);

        $this->assertIsArray($user->character_data);
        $this->assertEquals($characterData, $user->character_data);
    }

    #[Test]
    public function user_is_gm_is_cast_to_boolean()
    {
        $user = User::factory()->create(['is_gm' => true]);
        $this->assertIsBool($user->is_gm);
        $this->assertTrue($user->is_gm);

        $user2 = User::factory()->create(['is_gm' => false]);
        $this->assertIsBool($user2->is_gm);
        $this->assertFalse($user2->is_gm);
    }

    #[Test]
    public function user_can_store_default_actor_id()
    {
        $user = User::factory()->create([
            'default_actor_id' => 'Actor.abc123',
        ]);

        $this->assertEquals('Actor.abc123', $user->default_actor_id);
    }

    #[Test]
    public function user_can_store_default_token_id()
    {
        $user = User::factory()->create([
            'default_token_id' => 'Token.xyz789',
        ]);

        $this->assertEquals('Token.xyz789', $user->default_token_id);
    }

    #[Test]
    public function get_actor_id_returns_default_actor_id()
    {
        $user = User::factory()->create([
            'default_actor_id' => 'Actor.test123',
        ]);

        $this->assertEquals('Actor.test123', $user->getActorId());
    }

    #[Test]
    public function get_actor_id_returns_empty_string_when_null()
    {
        $user = User::factory()->create([
            'default_actor_id' => null,
        ]);

        $this->assertEquals('', $user->getActorId());
    }

    #[Test]
    public function get_token_id_returns_default_token_id()
    {
        $user = User::factory()->create([
            'default_token_id' => 'Token.test456',
        ]);

        $this->assertEquals('Token.test456', $user->getTokenId());
    }

    #[Test]
    public function get_token_id_returns_empty_string_when_null()
    {
        $user = User::factory()->create([
            'default_token_id' => null,
        ]);

        $this->assertEquals('', $user->getTokenId());
    }

    #[Test]
    public function user_password_is_hidden_in_array()
    {
        $user = User::factory()->create();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
    }

    #[Test]
    public function user_remember_token_is_hidden_in_array()
    {
        $user = User::factory()->create();

        $array = $user->toArray();

        $this->assertArrayNotHasKey('remember_token', $array);
    }

    #[Test]
    public function user_can_create_sanctum_tokens()
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token');

        $this->assertNotNull($token->plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    }

    #[Test]
    public function user_can_have_multiple_tokens()
    {
        $user = User::factory()->create();

        $token1 = $user->createToken('token-1');
        $token2 = $user->createToken('token-2');

        $this->assertNotEquals($token1->plainTextToken, $token2->plainTextToken);
        $this->assertCount(2, $user->tokens);
    }

    #[Test]
    public function generate_channel_id_creates_valid_format()
    {
        $channelId = User::generateChannelId();

        $this->assertMatchesRegularExpression('/^[a-z]+-[a-z]+-\d{3}$/', $channelId);
    }

    #[Test]
    public function generate_channel_id_creates_unique_ids()
    {
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = User::generateChannelId();
        }

        // While not guaranteed to be unique, statistically should be different
        $uniqueIds = array_unique($ids);
        $this->assertGreaterThan(1, count($uniqueIds));
    }

    #[Test]
    public function user_fillable_attributes_are_correct()
    {
        $user = new User();

        $fillable = $user->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('default_actor_id', $fillable);
        $this->assertContains('default_token_id', $fillable);
        $this->assertContains('character_data', $fillable);
        $this->assertContains('agent_link', $fillable);
        $this->assertContains('channel_id', $fillable);
        $this->assertContains('is_gm', $fillable);
    }

    #[Test]
    public function user_can_update_character_data()
    {
        $user = User::factory()->create([
            'character_data' => ['hp' => 50],
        ]);

        $user->character_data = ['hp' => 40];
        $user->save();

        $this->assertEquals(['hp' => 40], $user->fresh()->character_data);
    }

    #[Test]
    public function user_character_data_can_be_null()
    {
        $user = User::factory()->create([
            'character_data' => null,
        ]);

        $this->assertNull($user->character_data);
    }
}
