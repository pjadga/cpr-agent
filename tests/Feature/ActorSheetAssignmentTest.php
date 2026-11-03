<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When the stored sheet belongs to another character than the one assigned, the
 * sheet endpoint names the assigned one, so the page asks Foundry for it instead of
 * the id it remembered (a reassignment, or another account in the same browser).
 */
class ActorSheetAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_sheet_is_refused_and_the_assigned_character_named(): void
    {
        $user = User::factory()->create([
            'default_actor_id' => 'Actor.newActor000001',
            'character_data' => ['_id' => 'oldActor000001', 'name' => 'Demo', 'type' => 'character', 'system' => [], 'items' => []],
        ]);

        $this->actingAs($user)->postJson('/api/actorsheet')
            ->assertStatus(400)
            ->assertJson(['error' => 'no_character_data', 'assignedActorId' => 'newActor000001']);
    }

    public function test_the_assigned_sheet_is_served(): void
    {
        $user = User::factory()->create([
            'default_actor_id' => 'newActor000001',
            'character_data' => ['_id' => 'newActor000001', 'name' => 'QA', 'type' => 'character', 'system' => [], 'items' => []],
        ]);

        $this->actingAs($user)->postJson('/api/actorsheet')
            ->assertOk()
            ->assertJson(['uuid' => 'newActor000001']);
    }
}
