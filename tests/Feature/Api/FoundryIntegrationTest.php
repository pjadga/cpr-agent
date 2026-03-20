<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Events\FoundryResponse;
use Tests\TestCase;

/**
 * Integration tests simulating complete Foundry workflows
 */
class FoundryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $player;
    protected User $gm;
    protected string $playerToken;
    protected string $gmToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = User::factory()->create([
            'agent_link' => 'player-link-001',
            'channel_id' => 'test-channel-001',
            'default_actor_id' => 'Actor.player123',
            'is_gm' => false,
        ]);

        $this->gm = User::factory()->create([
            'agent_link' => 'gm-link-001',
            'channel_id' => 'test-channel-001',
            'is_gm' => true,
        ]);

        $this->playerToken = $this->player->createToken('player-token')->plainTextToken;
        $this->gmToken = $this->gm->createToken('gm-token')->plainTextToken;
    }

    /** @test */
    public function complete_character_link_workflow()
    {
        Event::fake([FoundryResponse::class]);

        // Step 1: Player links their character
        $response = $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'LINK_PLAYER_CHARACTER',
                'character' => ['id' => 'Actor.v001', 'name' => 'V'],
                'agentLink' => $this->player->agent_link,
                'isGM' => false,
            ]);

        $response->assertStatus(200);
        $this->player->refresh();
        $this->assertEquals('Actor.v001', $this->player->default_actor_id);

        // Step 2: Initial character data sync
        $characterData = [
            '_id' => 'Actor.v001',
            'name' => 'V',
            'system' => [
                'stats' => [
                    'body' => ['value' => 8],
                    'ref' => ['value' => 7],
                    'int' => ['value' => 6],
                ],
                'derivedStats' => [
                    'hp' => ['value' => 50, 'max' => 50],
                ],
            ],
            'items' => [],
        ];

        $response = $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ACTOR_DATA',
                'actorId' => 'Actor.v001',
                'actor' => $characterData,
                'agentLink' => $this->player->agent_link,
            ]);

        $response->assertStatus(200);
        $this->player->refresh();
        $this->assertEquals($characterData, $this->player->character_data);

        Event::assertDispatched(FoundryResponse::class);
    }

    /** @test */
    public function complete_combat_workflow()
    {
        Event::fake([FoundryResponse::class]);

        // Setup character with weapon
        $this->player->character_data = [
            'system' => [
                'derivedStats' => [
                    'hp' => ['value' => 50, 'max' => 50],
                ],
            ],
            'items' => [
                [
                    '_id' => 'weapon-001',
                    'name' => 'Heavy Pistol',
                    'type' => 'weapon',
                    'system' => [
                        'damage' => '2d6',
                        'equipped' => true,
                        'ammo' => ['value' => 8, 'max' => 8],
                    ],
                ],
            ],
        ];
        $this->player->save();

        // Step 1: Roll initiative
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'init-001',
                'actorId' => 'Actor.player123',
                'total' => 14,
                'label' => 'Initiative',
                'agentLink' => $this->player->agent_link,
            ]);

        // Step 2: Attack roll
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'attack-001',
                'actorId' => 'Actor.player123',
                'total' => 18,
                'label' => 'Heavy Pistol Attack',
                'isCriticalSuccess' => false,
                'agentLink' => $this->player->agent_link,
            ]);

        // Step 3: Damage roll
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'damage-001',
                'actorId' => 'Actor.player123',
                'total' => 10,
                'label' => 'Heavy Pistol (Damage)',
                'agentLink' => $this->player->agent_link,
            ]);

        // Step 4: Update ammo count
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.player123',
                'delta' => [
                    'items.weapon-001.system.ammo.value' => 7,
                ],
                'agentLink' => $this->player->agent_link,
            ]);

        $this->player->refresh();
        $weapon = collect($this->player->character_data['items'])->firstWhere('_id', 'weapon-001');
        $this->assertEquals(7, $weapon['system']['ammo']['value']);

        Event::assertDispatchedTimes(FoundryResponse::class, 4);
    }

    /** @test */
    public function complete_netrunning_workflow()
    {
        Event::fake([FoundryResponse::class]);

        // Setup character with cyberdeck and programs
        $this->player->character_data = [
            'items' => [
                [
                    '_id' => 'deck-001',
                    'name' => 'Militech Paraline',
                    'type' => 'cyberdeck',
                    'system' => [
                        'installedItems' => [
                            'list' => [],
                            'usedSlots' => 0,
                            'slots' => 10,
                        ],
                    ],
                ],
                [
                    '_id' => 'prog-001',
                    'name' => 'Sword',
                    'type' => 'program',
                    'system' => [
                        'size' => 2,
                        'isRezzed' => false,
                    ],
                ],
            ],
        ];
        $this->player->save();

        // Step 1: Install program
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.player123',
                'delta' => [
                    'items.deck-001.system.installedItems.list' => ['prog-001'],
                    'items.deck-001.system.installedItems.usedSlots' => 2,
                ],
                'agentLink' => $this->player->agent_link,
            ]);

        // Step 2: Rez program
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.player123',
                'delta' => [
                    'items.prog-001.system.isRezzed' => true,
                ],
                'agentLink' => $this->player->agent_link,
            ]);

        // Step 3: Program attack roll
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'prog-attack-001',
                'actorId' => 'Actor.player123',
                'total' => 16,
                'label' => 'Sword (Attack)',
                'agentLink' => $this->player->agent_link,
            ]);

        $this->player->refresh();
        $deck = collect($this->player->character_data['items'])->firstWhere('_id', 'deck-001');
        $program = collect($this->player->character_data['items'])->firstWhere('_id', 'prog-001');

        $this->assertContains('prog-001', $deck['system']['installedItems']['list']);
        $this->assertEquals(2, $deck['system']['installedItems']['usedSlots']);
        $this->assertTrue($program['system']['isRezzed']);

        Event::assertDispatchedTimes(FoundryResponse::class, 3);
    }

    /** @test */
    public function complete_death_save_workflow()
    {
        Event::fake([FoundryResponse::class]);

        // Setup character at 0 HP
        $this->player->character_data = [
            'system' => [
                'derivedStats' => [
                    'hp' => ['value' => 0, 'max' => 50],
                    'deathsave' => [
                        'value' => 0,
                        'penalty' => 0,
                    ],
                ],
            ],
        ];
        $this->player->save();

        // Step 1: First death save (success)
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'death-001',
                'actorId' => 'Actor.player123',
                'total' => 8,
                'label' => 'Death Save',
                'isCriticalSuccess' => false,
                'isCriticalFailure' => false,
                'agentLink' => $this->player->agent_link,
            ]);

        // Step 2: Update death save counter
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.player123',
                'delta' => [
                    'system.derivedStats.deathsave.value' => 1,
                ],
                'agentLink' => $this->player->agent_link,
            ]);

        $this->player->refresh();
        $this->assertEquals(1, $this->player->character_data['system']['derivedStats']['deathsave']['value']);

        Event::assertDispatchedTimes(FoundryResponse::class, 2);
    }

    /** @test */
    public function gm_can_update_player_character()
    {
        Event::fake([FoundryResponse::class]);

        // GM updates player's HP
        $this->player->character_data = [
            'system' => [
                'derivedStats' => [
                    'hp' => ['value' => 50, 'max' => 50],
                ],
            ],
        ];
        $this->player->save();

        $response = $this->withToken($this->gmToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.player123',
                'delta' => [
                    'system.derivedStats.hp.value' => 30,
                ],
                'agentLink' => $this->player->agent_link,
            ]);

        $response->assertStatus(200);

        $this->player->refresh();
        $this->assertEquals(30, $this->player->character_data['system']['derivedStats']['hp']['value']);

        Event::assertDispatched(FoundryResponse::class);
    }

    /** @test */
    public function multiple_players_in_same_channel()
    {
        Event::fake([FoundryResponse::class]);

        $player2 = User::factory()->create([
            'agent_link' => 'player-link-002',
            'channel_id' => 'test-channel-001', // Same channel
            'default_actor_id' => 'Actor.player456',
            'is_gm' => false,
        ]);
        $player2Token = $player2->createToken('player2-token')->plainTextToken;

        // Player 1 rolls
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'roll-p1',
                'actorId' => 'Actor.player123',
                'total' => 15,
                'label' => 'Attack',
                'agentLink' => $this->player->agent_link,
            ]);

        // Player 2 rolls
        $this->withToken($player2Token)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'roll-p2',
                'actorId' => 'Actor.player456',
                'total' => 12,
                'label' => 'Attack',
                'agentLink' => $player2->agent_link,
            ]);

        // Both should broadcast to the same channel
        Event::assertDispatchedTimes(FoundryResponse::class, 2);
        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['channel_id'] === 'test-channel-001';
        });
    }

    /** @test */
    public function item_equip_workflow()
    {
        Event::fake([FoundryResponse::class]);

        $this->player->character_data = [
            'items' => [
                [
                    '_id' => 'armor-001',
                    'name' => 'Light Armorjack',
                    'type' => 'armor',
                    'system' => [
                        'equipped' => false,
                        'sp' => 11,
                    ],
                ],
            ],
        ];
        $this->player->save();

        // Equip the armor
        $response = $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'SHEET_DELTA',
                'actorId' => 'Actor.player123',
                'delta' => [
                    'items.armor-001.system.equipped' => true,
                ],
                'agentLink' => $this->player->agent_link,
            ]);

        $response->assertStatus(200);

        $this->player->refresh();
        $armor = collect($this->player->character_data['items'])->firstWhere('_id', 'armor-001');
        $this->assertTrue($armor['system']['equipped']);

        Event::assertDispatched(FoundryResponse::class);
    }

    /** @test */
    public function face_down_roll_workflow()
    {
        Event::fake([FoundryResponse::class]);

        $response = $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'facedown-001',
                'actorId' => 'Actor.player123',
                'total' => 9,
                'label' => 'Face Down',
                'isCriticalSuccess' => false,
                'isCriticalFailure' => false,
                'agentLink' => $this->player->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['label'] === 'Face Down'
                && $event->payload['total'] === 9;
        });
    }

    /** @test */
    public function stat_and_skill_roll_workflow()
    {
        Event::fake([FoundryResponse::class]);

        // Stat roll (REF)
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'stat-ref-001',
                'actorId' => 'Actor.player123',
                'total' => 14,
                'label' => 'REF',
                'agentLink' => $this->player->agent_link,
            ]);

        // Skill roll (Handgun)
        $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'ROLL_RESULT',
                'requestId' => 'skill-handgun-001',
                'actorId' => 'Actor.player123',
                'total' => 16,
                'label' => 'Handgun',
                'agentLink' => $this->player->agent_link,
            ]);

        Event::assertDispatchedTimes(FoundryResponse::class, 2);
    }

    /** @test */
    public function refresh_signal_workflow()
    {
        Event::fake([FoundryResponse::class]);

        // Token moved, trigger refresh
        $response = $this->withToken($this->playerToken)
            ->postJson('/api/foundry/callback', [
                'type' => 'REFRESH_SIGNAL',
                'target' => 'VISIBLE_TOKENS_DATA',
                'agentLink' => $this->player->agent_link,
            ]);

        $response->assertStatus(200);

        Event::assertDispatched(FoundryResponse::class, function ($event) {
            return $event->payload['type'] === 'REFRESH_SIGNAL'
                && $event->payload['target'] === 'VISIBLE_TOKENS_DATA';
        });
    }
}
