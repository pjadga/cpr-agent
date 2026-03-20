<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Api\FoundryController;

/**
 * Unit tests for Foundry callback helper methods
 */
class FoundryCallbackHelperTest extends TestCase
{
    protected FoundryController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FoundryController();
    }
    /** @test */
    public function it_can_parse_dot_notation_paths()
    {
        $array = [];
        $this->controller->arraySetDot($array, 'system.stats.body.value', 8);

        $this->assertEquals(8, $array['system']['stats']['body']['value']);
    }

    /** @test */
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

        $this->controller->arraySetDot($array, 'system.stats.body.value', 8);

        $this->assertEquals(8, $array['system']['stats']['body']['value']);
        $this->assertEquals(6, $array['system']['stats']['ref']['value']);
    }

    /** @test */
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

        $this->controller->arraySetDot($array, 'system.stats.body', null);

        $this->assertArrayNotHasKey('body', $array['system']['stats']);
        $this->assertEquals(7, $array['system']['stats']['ref']['value']);
    }

    /** @test */
    public function it_can_add_items_to_array()
    {
        $array = ['items' => []];

        $newItem = [
            '_id' => 'item-001',
            'name' => 'Pistol',
        ];

        $this->controller->arraySetDot($array, 'items.+item-001', $newItem);

        $this->assertCount(1, $array['items']);
        $this->assertEquals('Pistol', $array['items'][0]['name']);
    }

    /** @test */
    public function it_can_update_existing_item_by_id()
    {
        $array = [
            'items' => [
                ['_id' => 'item-001', 'name' => 'Pistol', 'equipped' => false],
                ['_id' => 'item-002', 'name' => 'Rifle', 'equipped' => true],
            ],
        ];

        $this->controller->arraySetDot($array, 'items.item-001.equipped', true);

        $this->assertTrue($array['items'][0]['equipped']);
        $this->assertEquals('Pistol', $array['items'][0]['name']);
    }

    /** @test */
    public function it_can_delete_item_by_id()
    {
        $array = [
            'items' => [
                ['_id' => 'item-001', 'name' => 'Pistol'],
                ['_id' => 'item-002', 'name' => 'Rifle'],
            ],
        ];

        $this->controller->arraySetDot($array, 'items.item-001', null);

        $this->assertCount(1, $array['items']);
        $this->assertEquals('Rifle', $array['items'][0]['name']);
    }

    /** @test */
    public function it_prevents_duplicate_items_when_adding()
    {
        $array = [
            'items' => [
                ['_id' => 'item-001', 'name' => 'Pistol'],
            ],
        ];

        $updatedItem = ['_id' => 'item-001', 'name' => 'Heavy Pistol'];
        $this->controller->arraySetDot($array, 'items.+item-001', $updatedItem);

        $this->assertCount(1, $array['items']);
        $this->assertEquals('Heavy Pistol', $array['items'][0]['name']);
    }

    /** @test */
    public function it_handles_deep_nested_paths()
    {
        $array = [];

        $this->controller->arraySetDot($array, 'a.b.c.d.e.f', 'deep value');

        $this->assertEquals('deep value', $array['a']['b']['c']['d']['e']['f']);
    }

    /** @test */
    public function it_handles_numeric_values()
    {
        $array = [];

        $this->controller->arraySetDot($array, 'system.hp.value', 45);
        $this->controller->arraySetDot($array, 'system.hp.max', 50);

        $this->assertEquals(45, $array['system']['hp']['value']);
        $this->assertEquals(50, $array['system']['hp']['max']);
    }

    /** @test */
    public function it_handles_boolean_values()
    {
        $array = [];

        $this->controller->arraySetDot($array, 'system.equipped', true);
        $this->controller->arraySetDot($array, 'system.broken', false);

        $this->assertTrue($array['system']['equipped']);
        $this->assertFalse($array['system']['broken']);
    }

    /** @test */
    public function it_handles_array_values()
    {
        $array = [];

        $this->controller->arraySetDot($array, 'system.modifiers', [1, 2, 3]);

        $this->assertEquals([1, 2, 3], $array['system']['modifiers']);
    }

    /** @test */
    public function it_overwrites_existing_values()
    {
        $array = [
            'system' => [
                'hp' => ['value' => 50],
            ],
        ];

        $this->controller->arraySetDot($array, 'system.hp.value', 30);

        $this->assertEquals(30, $array['system']['hp']['value']);
    }
}
