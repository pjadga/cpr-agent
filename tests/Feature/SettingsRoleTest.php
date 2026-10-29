<?php

namespace Tests\Feature;

use App\Http\Handlers\LinkPlayerHandler;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsRoleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function players_cannot_promote_themselves()
    {
        // The gamemaster account manages accounts; nobody promotes themselves in Settings.
        $user = User::factory()->create(['is_gm' => false]);

        $this->actingAs($user)
            ->patch(route('settings.role'), ['role' => 'gm'])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('error');

        $this->assertFalse($user->fresh()->is_gm);
    }

    #[Test]
    public function gm_with_an_active_session_cannot_demote()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        Session::create(['gm_user_id' => $gm->id, 'expires_at' => now()->addDay()]);

        $this->actingAs($gm)
            ->patch(route('settings.role'), ['role' => 'player'])
            ->assertRedirect(route('settings'))
            ->assertSessionHas('error');

        $this->assertTrue($gm->fresh()->is_gm);
    }

    #[Test]
    public function invalid_role_is_rejected()
    {
        $user = User::factory()->create(['is_gm' => false]);

        $this->actingAs($user)
            ->patch(route('settings.role'), ['role' => 'admin'])
            ->assertSessionHasErrors('role');
    }

    #[Test]
    public function foundry_sync_does_not_demote_a_session_owning_gm()
    {
        $gm = User::factory()->create(['is_gm' => true]);
        Session::create(['gm_user_id' => $gm->id, 'expires_at' => now()->addDay()]);

        // Simulates the GM testing their world from a player login: the Bridge
        // reports isGM=false, but the declared GM keeps session management.
        app(LinkPlayerHandler::class)->handle($gm, [
            'type' => 'LINK_PLAYER_CHARACTER',
            'character' => null,
            'isGM' => false,
        ]);

        $this->assertTrue($gm->fresh()->is_gm);
    }

    #[Test]
    public function foundry_sync_still_demotes_a_gm_without_a_session()
    {
        $user = User::factory()->create(['is_gm' => true]);

        app(LinkPlayerHandler::class)->handle($user, [
            'type' => 'LINK_PLAYER_CHARACTER',
            'character' => null,
            'isGM' => false,
        ]);

        $this->assertFalse($user->fresh()->is_gm);
    }

    #[Test]
    public function foundry_sync_still_promotes_via_verification()
    {
        $user = User::factory()->create(['is_gm' => false]);

        app(LinkPlayerHandler::class)->handle($user, [
            'type' => 'LINK_PLAYER_CHARACTER',
            'character' => null,
            'isGM' => true,
        ]);

        $this->assertTrue($user->fresh()->is_gm);
    }
}
