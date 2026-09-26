<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ActorSheet::cleanItem whitelists which system fields reach the sheet, so a
 * field left out of it is dropped however complete the stored actor is. The
 * cyberware UI groups by body location and tells a location apart from the
 * cyberware fitted into it, and none of that survived the filter.
 */
class ActorSheetCyberwareTest extends TestCase
{
    use RefreshDatabase;

    private function actorWithCyberware(): array
    {
        return [
            '_id' => 'actor1',
            'name' => 'Jimmy',
            'type' => 'character',
            'system' => [],
            'items' => [
                [
                    '_id' => 'arm1',
                    'name' => 'Cyberarm',
                    'type' => 'cyberware',
                    'system' => [
                        'type' => 'cyberArm',
                        'isFoundational' => true,
                        'isInstalled' => true,
                        'core' => false,
                        'size' => 0,
                        'humanityLoss' => ['roll' => '2d6', 'static' => 7],
                        'installedItems' => ['list' => [], 'slots' => 4, 'usedSlots' => 0],
                    ],
                ],
                [
                    '_id' => 'fw1',
                    'name' => 'Fashionware (7 Option Slots)',
                    'type' => 'cyberware',
                    'system' => [
                        'type' => 'fashionware',
                        'isFoundational' => true,
                        'core' => true,
                        'size' => 1,
                        'installedItems' => ['list' => [], 'slots' => 7, 'usedSlots' => 0],
                    ],
                ],
            ],
        ];
    }

    private function sheetItems(): array
    {
        $user = User::factory()->create([
            'default_actor_id' => 'actor1',
            'character_data' => $this->actorWithCyberware(),
        ]);

        $response = $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/actorsheet');

        $response->assertStatus(200);

        return collect($response->json('data.items'))->keyBy('_id')->all();
    }

    #[Test]
    public function it_passes_the_cpr_body_location_through_as_type()
    {
        $items = $this->sheetItems();

        // Grouping reads system.type; it used to arrive renamed and the whole
        // section collapsed into a single "Other Cyberware" heading.
        $this->assertSame('cyberArm', $items['arm1']['system']['type']);
        $this->assertSame('fashionware', $items['fw1']['system']['type']);
    }

    #[Test]
    public function it_keeps_the_legacy_cyberware_type_alias()
    {
        $items = $this->sheetItems();

        $this->assertSame('cyberArm', $items['arm1']['system']['cyberwareType']);
    }

    #[Test]
    public function it_passes_the_flags_that_separate_a_location_from_its_cyberware()
    {
        $items = $this->sheetItems();

        // core marks the innate body location, which carries no action.
        $this->assertTrue($items['fw1']['system']['core']);
        $this->assertFalse($items['arm1']['system']['core']);
        $this->assertTrue($items['arm1']['system']['isFoundational']);
    }

    #[Test]
    public function it_distinguishes_an_unknown_install_state_from_a_known_one()
    {
        $items = $this->sheetItems();

        // isInstalled is a derived getter: present on a freshly serialised
        // actor, absent otherwise. null has to survive as "unknown" so the
        // sheet can fall back instead of reading it as "not installed".
        $this->assertTrue($items['arm1']['system']['isInstalled']);
        $this->assertNull($items['fw1']['system']['isInstalled']);
    }

    #[Test]
    public function it_passes_size_and_humanity_loss()
    {
        $items = $this->sheetItems();

        $this->assertSame(0, $items['arm1']['system']['size']);
        $this->assertSame(['roll' => '2d6', 'static' => 7], $items['arm1']['system']['humanityLoss']);
    }
}
