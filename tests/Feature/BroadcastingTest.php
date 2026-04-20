<?php

namespace Tests\Feature;

use App\Events\FoundryResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Laravel Broadcasting and Reverb integration
 */
class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'channel_id' => 'test-channel-001',
            'agent_link' => 'test-agent-link',
        ]);
    }

    #[Test]
    public function foundry_response_event_can_be_dispatched()
    {
        Event::fake([FoundryResponse::class]);

        $payload = [
            'type' => 'ROLL_RESULT',
            'total' => 15,
            'label' => 'Test Roll',
            'channel_id' => 'test-channel-001',
        ];

        FoundryResponse::dispatch($payload);

        Event::assertDispatched(FoundryResponse::class, function ($event) use ($payload) {
            return $event->payload === $payload;
        });
    }

    #[Test]
    public function foundry_response_broadcasts_on_correct_channel()
    {
        Event::fake([FoundryResponse::class]);

        $payload = [
            'type' => 'ACTOR_DATA',
            'channel_id' => 'custom-channel-123',
        ];

        FoundryResponse::dispatch($payload);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['channel_id'] === 'custom-channel-123';
        });
    }

    #[Test]
    public function broadcasting_auth_requires_authentication()
    {
        $response = $this->getJson('/api/broadcasting/auth');

        $response->assertStatus(401);
    }

    #[Test]
    public function web_session_can_access_broadcasting_auth()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'private-cpr-agent.'.$this->user->channel_id,
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function event_payload_includes_all_required_fields()
    {
        Event::fake([FoundryResponse::class]);

        $payload = [
            'type' => 'ROLL_RESULT',
            'requestId' => 'req-123',
            'actorId' => 'Actor.abc123',
            'total' => 18,
            'isCriticalSuccess' => true,
            'isCriticalFailure' => false,
            'label' => 'Attack Roll',
            'agentLink' => 'agent-link-456',
            'channel_id' => 'test-channel-001',
        ];

        FoundryResponse::dispatch($payload);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['type'] === 'ROLL_RESULT'
                && $event->payload['requestId'] === 'req-123'
                && $event->payload['total'] === 18
                && $event->payload['isCriticalSuccess'] === true
                && $event->payload['channel_id'] === 'test-channel-001';
        });
    }

    #[Test]
    public function multiple_events_can_be_dispatched_sequentially()
    {
        Event::fake([FoundryResponse::class]);

        $payloads = [
            ['type' => 'ROLL_RESULT', 'total' => 10, 'channel_id' => 'test-channel-001'],
            ['type' => 'SHEET_DELTA', 'delta' => ['hp' => 45], 'channel_id' => 'test-channel-001'],
            ['type' => 'CONFIRMATION', 'success' => true, 'channel_id' => 'test-channel-001'],
        ];

        foreach ($payloads as $payload) {
            FoundryResponse::dispatch($payload);
        }

        Event::assertDispatchedTimes(FoundryResponse::class, 3);
    }

    #[Test]
    public function event_handles_null_channel_id_gracefully()
    {
        Event::fake([FoundryResponse::class]);

        $payload = [
            'type' => 'ROLL_RESULT',
            'total' => 15,
            // No channel_id provided
        ];

        FoundryResponse::dispatch($payload);

        Event::assertDispatched(FoundryResponse::class);
    }
}
