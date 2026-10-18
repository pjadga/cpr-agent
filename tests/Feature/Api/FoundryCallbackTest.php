<?php

namespace Tests\Feature\Api;

use App\Events\FoundryResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FoundryCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'agent_link' => 'test-agent-link-123',
            'channel_id' => 'test-channel-001',
            'default_actor_id' => 'Actor.abc123',
            'is_gm' => false,
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    #[Test]
    public function it_requires_authentication()
    {
        $response = $this->postJson('/api/foundry/callback', [
            'type' => 'ACTOR_DATA',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_validates_required_type_field()
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function it_handles_link_player_character_request()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'LINK_PLAYER_CHARACTER',
                'character' => ['id' => 'Actor.xyz789', 'name' => 'V'],
                'agentLink' => $this->user->agent_link,
                'isGM' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'agent_link' => $this->user->agent_link,
        ]);

        $this->user->refresh();
        $this->assertEquals('Actor.xyz789', $this->user->default_actor_id);
        $this->assertFalse($this->user->is_gm);
    }

    #[Test]
    public function it_handles_gm_link_player_character_request()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        $gmToken = $gm->createToken('gm-token')->plainTextToken;

        $targetUser = User::factory()->create([
            'agent_link' => 'player-link-456',
        ]);

        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($gmToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'GM_LINK_PLAYER_CHARACTER',
                'agentLink' => 'player-link-456',
                'character' => 'Actor.player123',
                'isGM' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $targetUser->refresh();
        $this->assertEquals('Actor.player123', $targetUser->default_actor_id);
    }

    #[Test]
    public function it_handles_actor_data_update()
    {
        Event::fake([FoundryResponse::class]);

        $actorData = [
            '_id' => 'Actor.abc123',
            'name' => 'V',
            'system' => [
                'stats' => [
                    'body' => ['value' => 8],
                    'ref' => ['value' => 7],
                ],
                'derivedStats' => [
                    'hp' => ['value' => 45, 'max' => 50],
                ],
            ],
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ACTOR_DATA',
                'actorId' => 'Actor.abc123',
                'actor' => $actorData,
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'processing_ms']);

        $this->user->refresh();
        $this->assertEquals($actorData, $this->user->character_data);

        Event::assertDispatched(FoundryResponse::class);
    }

    #[Test]
    public function it_handles_sheet_delta_updates()
    {
        // Set initial character data
        $this->user->character_data = [
            'system' => [
                'stats' => [
                    'body' => ['value' => 8],
                ],
                'derivedStats' => [
                    'hp' => ['value' => 45, 'max' => 50],
                ],
            ],
        ];
        $this->user->save();

        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.abc123',
                'delta' => [
                    'system.derivedStats.hp.value' => 40,
                    'system.stats.body.value' => 9,
                ],
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertEquals(40, $this->user->character_data['system']['derivedStats']['hp']['value']);
        $this->assertEquals(9, $this->user->character_data['system']['stats']['body']['value']);

        Event::assertDispatched(FoundryResponse::class);
    }

    #[Test]
    public function it_handles_roll_result_callback()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'req-123',
                'actorId' => 'Actor.abc123',
                'total' => 15,
                'isCriticalSuccess' => false,
                'isCriticalFailure' => false,
                'label' => 'Handgun Attack',
                'formula' => '1d10+5',
                'rawRoll' => [
                    'total' => 15,
                    'formula' => '1d10+5',
                ],
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['type'] === 'ROLL_RESULT'
                && $event->payload['total'] === 15
                && $event->payload['label'] === 'Handgun Attack';
        });
    }

    #[Test]
    public function it_handles_item_creation_delta()
    {
        $this->user->character_data = [
            'items' => [],
        ];
        $this->user->save();

        Event::fake([FoundryResponse::class]);

        $newItem = [
            '_id' => 'item-001',
            'name' => 'Heavy Pistol',
            'type' => 'weapon',
            'system' => [
                'damage' => '2d6',
                'equipped' => true,
            ],
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.abc123',
                'delta' => [
                    'items.+item-001' => $newItem,
                ],
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertCount(1, $this->user->character_data['items']);
        $this->assertEquals('Heavy Pistol', $this->user->character_data['items'][0]['name']);
    }

    #[Test]
    public function it_handles_item_deletion_delta()
    {
        $this->user->character_data = [
            'items' => [
                ['_id' => 'item-001', 'name' => 'Pistol'],
                ['_id' => 'item-002', 'name' => 'Rifle'],
            ],
        ];
        $this->user->save();

        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.abc123',
                'delta' => [
                    'items.item-001' => null,
                ],
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertCount(1, $this->user->character_data['items']);
        $this->assertEquals('Rifle', $this->user->character_data['items'][0]['name']);
    }

    #[Test]
    public function it_handles_item_update_delta()
    {
        $this->user->character_data = [
            'items' => [
                [
                    '_id' => 'item-001',
                    'name' => 'Pistol',
                    'system' => ['equipped' => false],
                ],
            ],
        ];
        $this->user->save();

        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.abc123',
                'delta' => [
                    'items.item-001.system.equipped' => true,
                ],
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertTrue($this->user->character_data['items'][0]['system']['equipped']);
    }

    #[Test]
    public function it_caches_response_for_request_id()
    {
        Event::fake([FoundryResponse::class]);

        $requestId = 'unique-request-123';

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => $requestId,
                'total' => 20,
                'label' => 'Test Roll',
            ]);

        $response->assertStatus(200);

        $cached = Cache::get('foundry_response_'.$requestId);
        $this->assertNotNull($cached);
        $this->assertEquals('ROLL_RESULT', $cached['type']);
        $this->assertEquals(20, $cached['total']);
    }

    #[Test]
    public function it_broadcasts_with_channel_id()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'CONFIRMATION',
                'action' => 'UPDATE_ACTOR',
                'success' => true,
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['channel_id'] === 'test-channel-001';
        });
    }

    #[Test]
    public function it_updates_gm_status_when_provided()
    {
        $this->assertFalse($this->user->is_gm);

        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ACTOR_DATA',
                'actorId' => 'Actor.abc123',
                'actor' => ['_id' => 'Actor.abc123'],
                'isGM' => true,
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertTrue($this->user->is_gm);
    }

    #[Test]
    public function it_handles_death_save_roll()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'death-save-001',
                'actorId' => 'Actor.abc123',
                'total' => 8,
                'isCriticalSuccess' => false,
                'isCriticalFailure' => true,
                'label' => 'Death Save',
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['label'] === 'Death Save'
                && $event->payload['isCriticalFailure'] === true;
        });
    }

    #[Test]
    public function it_handles_damage_roll()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'damage-001',
                'actorId' => 'Actor.abc123',
                'total' => 12,
                'label' => 'Heavy Pistol (Damage)',
                'formula' => '2d6',
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class);
    }

    #[Test]
    public function it_handles_initiative_roll()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'init-001',
                'actorId' => 'Actor.abc123',
                'total' => 14,
                'label' => 'Initiative',
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class);
    }

    #[Test]
    public function it_handles_refresh_signal()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'REFRESH_SIGNAL',
                'target' => 'VISIBLE_TOKENS_DATA',
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['type'] === 'REFRESH_SIGNAL'
                && $event->payload['target'] === 'VISIBLE_TOKENS_DATA';
        });
    }

    #[Test]
    public function it_handles_confirmation_response()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'CONFIRMATION',
                'action' => 'UPDATE_ACTOR',
                'actorId' => 'Actor.abc123',
                'success' => true,
                'requestId' => 'update-001',
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class);
    }

    #[Test]
    public function it_identifies_user_by_actor_id_when_agent_link_missing()
    {
        $userWithoutLink = User::factory()->create([
            'agent_link' => null,
            'default_actor_id' => 'Actor.xyz999',
        ]);
        $token = $userWithoutLink->createToken('test')->plainTextToken;

        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ACTOR_DATA',
                'actorId' => 'Actor.xyz999',
                'actor' => ['_id' => 'Actor.xyz999', 'name' => 'Test'],
            ]);

        $response->assertStatus(200);

        $userWithoutLink->refresh();
        $this->assertNotNull($userWithoutLink->character_data);
    }

    #[Test]
    public function it_handles_cyberdeck_program_operations()
    {
        $this->user->character_data = [
            'items' => [
                [
                    '_id' => 'deck-001',
                    'type' => 'cyberdeck',
                    'system' => [
                        'installedItems' => [
                            'list' => ['prog-001'],
                            'usedSlots' => 2,
                            'slots' => 10,
                        ],
                    ],
                ],
            ],
        ];
        $this->user->save();

        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.abc123',
                'delta' => [
                    'items.deck-001.system.installedItems.list' => ['prog-001', 'prog-002'],
                    'items.deck-001.system.installedItems.usedSlots' => 4,
                ],
                'agentLink' => $this->user->agent_link,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $deck = collect($this->user->character_data['items'])->firstWhere('_id', 'deck-001');
        $this->assertCount(2, $deck['system']['installedItems']['list']);
        $this->assertEquals(4, $deck['system']['installedItems']['usedSlots']);
    }

    #[Test]
    public function it_broadcasts_cyberware_install_options_without_stripping_fields()
    {
        Event::fake([FoundryResponse::class]);

        // validatePayload() allowlists fields and the validated array is what
        // gets broadcast, so a field missing from the rules is dropped in
        // transit. That is what left the install modal with no targets and no
        // humanity figures, rendering its "cannot be installed" branch instead.
        $response = $this->withToken($this->token)
            ->postJson('/api/foundry/callback', [
                'type' => 'CYBERWARE_INSTALL_OPTIONS',
                'requestId' => 'req-cyber-001',
                'actorId' => 'abc123',
                'itemId' => 'item-neuralware-1',
                'itemName' => 'Neural Link',
                'isFoundational' => true,
                'canInstall' => true,
                'reason' => null,
                'targets' => [
                    ['id' => 'foundational-1', 'name' => 'Cyberaudio Suite'],
                ],
                'humanityLoss' => ['roll' => '2d6', 'static' => 7],
                'success' => true,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['type'] === 'CYBERWARE_INSTALL_OPTIONS'
                && $event->payload['itemId'] === 'item-neuralware-1'
                && $event->payload['itemName'] === 'Neural Link'
                && $event->payload['isFoundational'] === true
                && $event->payload['canInstall'] === true
                && $event->payload['targets'][0]['name'] === 'Cyberaudio Suite'
                && $event->payload['humanityLoss']['roll'] === '2d6'
                && $event->payload['humanityLoss']['static'] === 7;
        });
    }

    #[Test]
    public function it_does_not_let_another_actors_data_overwrite_the_assigned_character()
    {
        $this->user->character_data = ['_id' => 'abc123', 'name' => 'Mine', 'system' => ['stats' => ['body' => ['value' => 5]]]];
        $this->user->save();
        Cache::forget("user_char_{$this->user->id}");

        $this->withToken($this->token)->postJson('/api/foundry/callback', [
            'type' => 'ACTOR_DATA',
            'actor' => ['_id' => 'someone-else', 'name' => 'Other'],
            'agentLink' => $this->user->agent_link,
        ]);
        $this->withToken($this->token)->postJson('/api/foundry/callback', [
            'type' => 'SHEET_DELTA',
            'actorId' => 'Actor.someone-else',
            'delta' => ['system.stats.body.value' => 1],
            'agentLink' => $this->user->agent_link,
        ]);

        $cached = Cache::get("user_char_{$this->user->id}");
        $this->assertNull($cached, 'Neither payload should have touched the cache');
        $this->user->refresh();
        $this->assertSame('abc123', $this->user->character_data['_id']);
        $this->assertSame(5, $this->user->character_data['system']['stats']['body']['value']);
    }
}
