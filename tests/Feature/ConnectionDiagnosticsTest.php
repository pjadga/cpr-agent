<?php

namespace Tests\Feature;

use App\Events\CyberpunkAgentChannel;
use App\Models\Config;
use App\Models\User;
use App\Support\ConnectionDiagnostics;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Three things have to be running before a character sheet can load: the web
 * app, Reverb, and Foundry with the Bridge module connected. When one is
 * missing the raw failure is the same in every case -- the sheet showed
 * "Request failed with status code 500", which names the symptom and none of
 * the cause.
 *
 * These tests pin the part that makes the difference: every one of those
 * failures now arrives with a code the client can turn into an explanation, and
 * the settings page (the only screen that can fix any of it) still renders.
 */
class ConnectionDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private function configuredUser(): User
    {
        $user = User::factory()->create(['character_data' => ['name' => 'V', '_id' => 'abc']]);

        Config::create([
            'user_id' => $user->id,
            'api_key' => 'test-key',
            'foundry_url' => 'http://192.168.1.60:30000',
        ]);

        return $user;
    }

    /**
     * Make broadcasting fail the way a stopped Reverb does: the Pusher HTTP
     * call to the events API refuses the connection.
     */
    private function breakReverb(): void
    {
        Event::listen(CyberpunkAgentChannel::class, function () {
            throw new BroadcastException('Pusher error: cURL error 7: Failed to connect to 127.0.0.1 port 8085');
        });
    }

    // =================================================================
    // Reverb down
    // =================================================================

    #[Test]
    public function a_dead_reverb_answers_json_callers_with_a_named_cause(): void
    {
        // Was a bare 500. 503 says "your request was fine, a dependency is
        // missing", and the code is what lets the sheet say which one.
        $this->breakReverb();

        $this->actingAs($this->configuredUser())
            ->postJson('/api/getactor', ['actorId' => 'Actor.abc'])
            ->assertStatus(503)
            ->assertJson(['error' => 'realtime_unavailable']);
    }

    #[Test]
    public function the_named_cause_reaches_every_endpoint_that_broadcasts(): void
    {
        // The handler is global on purpose: roughly thirty controllers dispatch
        // this event, and none of them should need to know about it.
        $this->breakReverb();
        $user = $this->configuredUser();

        foreach ([
            '/api/getactors',
            '/api/statrollrequest',
            '/api/listvisibletokens',
        ] as $endpoint) {
            $this->actingAs($user)
                ->postJson($endpoint, ['label' => 'int'])
                ->assertStatus(503)
                ->assertJson(['error' => 'realtime_unavailable'], "endpoint {$endpoint}");
        }
    }

    #[Test]
    public function a_dead_reverb_does_not_take_down_the_settings_page(): void
    {
        // This is the page you go to in order to fix the problem. It used to
        // 500 for the same reason everything else did.
        $this->breakReverb();

        $this->actingAs($this->configuredUser())
            ->get('/settings')
            ->assertOk()
            ->assertSee('Live updates are offline');
    }

    #[Test]
    public function the_settings_page_explains_a_dead_reverb_rather_than_just_flagging_it(): void
    {
        $this->breakReverb();

        $response = $this->actingAs($this->configuredUser())->get('/settings');

        // The fix is the part that matters: Reverb is a separate process and
        // opening the site does not start it.
        $response->assertSee('agent:serve', escape: false);
    }

    #[Test]
    public function a_browser_page_load_is_not_turned_into_json(): void
    {
        // The JSON handler must not swallow ordinary page loads, or a broken
        // Reverb would replace every HTML error with a bare JSON blob.
        $this->breakReverb();

        $this->actingAs($this->configuredUser())
            ->get('/settings')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    // =================================================================
    // Foundry not configured
    // =================================================================

    #[Test]
    public function an_unconfigured_account_gets_a_named_cause_instead_of_a_redirect(): void
    {
        // An XHR cannot usefully follow a 302 to an HTML settings page: axios
        // reports something unrelated to the actual cause.
        $user = User::factory()->create(['character_data' => null]);
        Config::create(['user_id' => $user->id, 'api_key' => null, 'foundry_url' => null]);

        $this->actingAs($user)
            ->postJson('/api/getactor', ['actorId' => 'Actor.abc'])
            ->assertStatus(409)
            ->assertJson(['error' => 'foundry_not_configured']);
    }

    #[Test]
    public function a_browser_visit_still_redirects_to_settings(): void
    {
        // The redirect is right for a real page load; only JSON callers change.
        $user = User::factory()->create(['character_data' => null]);
        Config::create(['user_id' => $user->id, 'api_key' => null, 'foundry_url' => null]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('settings'));
    }

    #[Test]
    public function the_settings_page_says_when_no_foundry_is_saved(): void
    {
        $user = User::factory()->create(['character_data' => null]);
        Config::create(['user_id' => $user->id, 'api_key' => 'k', 'foundry_url' => null]);

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSee('No Foundry address saved yet');
    }

    // =================================================================
    // Foundry configured, but nothing answering
    // =================================================================

    #[Test]
    public function the_settings_page_distinguishes_a_silent_foundry_from_a_dead_reverb(): void
    {
        // Reverb accepted the message and nothing replied: the Bridge is not
        // connected. Different cause, different fix, so it must not be reported
        // as the Reverb case.
        Event::fake([CyberpunkAgentChannel::class]);

        $response = $this->actingAs($this->configuredUser())->get('/settings');

        $response->assertOk()
            ->assertSee('Foundry did not answer')
            ->assertDontSee('Live updates are offline');
    }

    // =================================================================
    // The catalogue itself
    // =================================================================

    #[Test]
    public function every_known_code_carries_a_title_a_detail_and_a_fix(): void
    {
        // A code with no hint is only half an explanation, which is the state
        // this whole change exists to leave behind.
        foreach (['realtime_unavailable', 'foundry_not_configured', 'foundry_no_response'] as $code) {
            $this->assertTrue(ConnectionDiagnostics::has($code), "missing code {$code}");

            $entry = ConnectionDiagnostics::get($code);

            foreach (['title', 'detail', 'hint'] as $field) {
                $this->assertArrayHasKey($field, $entry);
                $this->assertNotEmpty($entry[$field], "{$code}.{$field} is empty");
            }
        }
    }

    #[Test]
    public function an_account_with_no_foundry_is_told_to_finish_step_one(): void
    {
        // This guard also tested `config('services.foundry.base_url')`, which
        // defaulted to the retired pre-Bridge relay and so was never empty. The
        // condition could not be false, so the prompt never rendered and a fresh
        // account was shown the actor picker with nothing to pick from.
        $user = User::factory()->create(['character_data' => null]);
        Config::create(['user_id' => $user->id, 'api_key' => 'k', 'foundry_url' => null]);

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSee('SYSTEM OFFLINE');
    }

    #[Test]
    public function a_configured_account_gets_the_actor_picker_rather_than_the_prompt(): void
    {
        $this->actingAs($this->configuredUser())->get('/settings')
            ->assertOk()
            ->assertDontSee('SYSTEM OFFLINE');
    }

    #[Test]
    public function an_account_with_no_character_yet_gets_a_code_not_just_prose(): void
    {
        // The blank dashboard: /api/actorsheet answers 400 here, the sheet had
        // nothing to branch on, so it stopped loading, rendered no branch and
        // showed an empty page with the reason only in the browser console.
        $user = User::factory()->create(['character_data' => null]);
        Config::create([
            'user_id' => $user->id,
            'api_key' => 'k',
            'foundry_url' => 'http://192.168.1.60:30000',
        ]);

        $this->actingAs($user)
            ->postJson('/api/actorsheet')
            ->assertStatus(400)
            ->assertJson(['error' => 'no_character_data']);
    }

    #[Test]
    public function the_empty_sheet_response_no_longer_claims_success(): void
    {
        // It used to answer a failure with "Data successfully submitted!".
        $user = User::factory()->create(['character_data' => null]);
        Config::create([
            'user_id' => $user->id,
            'api_key' => 'k',
            'foundry_url' => 'http://192.168.1.60:30000',
        ]);

        $this->actingAs($user)
            ->postJson('/api/actorsheet')
            ->assertJsonPath('success', false)
            ->assertJsonMissing(['message' => 'Data successfully submitted!']);
    }

    #[Test]
    public function an_unknown_code_degrades_to_a_generic_explanation(): void
    {
        $this->assertFalse(ConnectionDiagnostics::has('nonsense'));
        $this->assertFalse(ConnectionDiagnostics::has(null));
        $this->assertSame('Something went wrong', ConnectionDiagnostics::get('nonsense')['title']);
    }
}
