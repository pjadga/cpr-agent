<?php

namespace Tests\Unit;

use App\Http\Handlers\DeltaApplier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for the DeltaApplier (extracted from FoundryController).
 */
class FoundryCallbackHelperTest extends TestCase
{
    protected DeltaApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applier = new DeltaApplier();
    }

    #[Test]
    public function it_can_parse_dot_notation_paths()
    {
        $array = [];
        $this->applier->apply($array, ['system.stats.body.value' => 8]);

        $this->assertEquals(8, $array['system']['stats']['body']['value']);
    }

    #[Test]
    public function it_can_handle_nested_updates()
    {
        $array = [
            'system' => [
                'stats' => [
                    'body' => ['value' => 5],
                    'ref' => ['value' => 6],
                ],
            ],
        ];

        $this->applier->apply($array, ['system.stats.body.value' => 8]);

        $this->assertEquals(8, $array['system']['stats']['body']['value']);
        $this->assertEquals(6, $array['system']['stats']['ref']['value']);
    }

    #[Test]
    public function it_can_delete_values_with_null()
    {
        $array = [
            'system' => [
                'stats' => [
                    'body' => ['value' => 8],
                    'ref' => ['value' => 7],
                ],
            ],
        ];

        $this->applier->apply($array, ['system.stats.body' => null]);

        $this->assertArrayNotHasKey('body', $array['system']['stats']);
        $this->assertEquals(7, $array['system']['stats']['ref']['value']);
    }

    #[Test]
    public function it_can_add_items_to_array()
    {
        $array = ['items' => []];

        $newItem = [
            '_id' => 'item-001',
            'name' => 'Pistol',
        ];

        $this->applier->apply($array, ['items.+item-001' => $newItem]);

        $this->assertCount(1, $array['items']);
        $this->assertEquals('Pistol', $array['items'][0]['name']);
    }

    #[Test]
    public function it_can_update_existing_item_by_id()
    {
        $array = [
            'items' => [
                ['_id' => 'item-001', 'name' => 'Pistol', 'equipped' => false],
                ['_id' => 'item-002', 'name' => 'Rifle', 'equipped' => true],
            ],
        ];

        $this->applier->apply($array, ['items.item-001.equipped' => true]);

        $this->assertTrue($array['items'][0]['equipped']);
        $this->assertEquals('Pistol', $array['items'][0]['name']);
    }

    #[Test]
    public function it_can_delete_item_by_id()
    {
        $array = [
            'items' => [
                ['_id' => 'item-001', 'name' => 'Pistol'],
                ['_id' => 'item-002', 'name' => 'Rifle'],
            ],
        ];

        $this->applier->apply($array, ['items.item-001' => null]);

        $this->assertCount(1, $array['items']);
        $this->assertEquals('Rifle', $array['items'][0]['name']);
    }

    #[Test]
    public function it_prevents_duplicate_items_when_adding()
    {
        $array = [
            'items' => [
                ['_id' => 'item-001', 'name' => 'Pistol'],
            ],
        ];

        $updatedItem = ['_id' => 'item-001', 'name' => 'Heavy Pistol'];
        $this->applier->apply($array, ['items.+item-001' => $updatedItem]);

        $this->assertCount(1, $array['items']);
        $this->assertEquals('Heavy Pistol', $array['items'][0]['name']);
    }

    #[Test]
    public function it_handles_deep_nested_paths()
    {
        $array = [];

        $this->applier->apply($array, ['a.b.c.d.e.f' => 'deep value']);

        $this->assertEquals('deep value', $array['a']['b']['c']['d']['e']['f']);
    }

    #[Test]
    public function it_handles_numeric_values()
    {
        $array = [];

        $this->applier->apply($array, ['system.hp.value' => 45]);
        $this->applier->apply($array, ['system.hp.max' => 50]);

        $this->assertEquals(45, $array['system']['hp']['value']);
        $this->assertEquals(50, $array['system']['hp']['max']);
    }

    #[Test]
    public function it_handles_boolean_values()
    {
        $array = [];

        $this->applier->apply($array, ['system.equipped' => true]);
        $this->applier->apply($array, ['system.broken' => false]);

        $this->assertTrue($array['system']['equipped']);
        $this->assertFalse($array['system']['broken']);
    }

    #[Test]
    public function it_handles_array_values()
    {
        $array = [];

        $this->applier->apply($array, ['system.modifiers' => [1, 2, 3]]);

        $this->assertEquals([1, 2, 3], $array['system']['modifiers']);
    }

    #[Test]
    public function it_overwrites_existing_values()
    {
        $array = [
            'system' => [
                'hp' => ['value' => 50],
            ],
        ];

        $this->applier->apply($array, ['system.hp.value' => 30]);

        $this->assertEquals(30, $array['system']['hp']['value']);
    }
}
