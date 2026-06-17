<?php

namespace Tests\Unit;

use App\Http\Handlers\DeltaApplier;
use PHPUnit\Framework\TestCase;

class DeltaApplierTest extends TestCase
{
    private DeltaApplier $applier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applier = new DeltaApplier();
    }

    #[Test]
    public function it_applies_simple_dot_notation()
    {
        $data = ['system' => ['stats' => ['body' => ['value' => 10]]]];
        $this->applier->apply($data, ['system.stats.body.value' => 20]);

        $this->assertEquals(20, $data['system']['stats']['body']['value']);
    }

    #[Test]
    public function it_appends_item_with_plus_syntax()
    {
        $data = ['items' => []];
        $newItem = ['_id' => 'item-1', 'name' => 'Heavy Pistol'];
        $this->applier->apply($data, ['items.+item-1' => $newItem]);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('item-1', $data['items'][0]['_id']);
    }

    #[Test]
    public function it_updates_existing_item_with_plus_syntax()
    {
        $data = ['items' => [['_id' => 'item-1', 'name' => 'Old Name']]];
        $updatedItem = ['_id' => 'item-1', 'name' => 'New Name'];
        $this->applier->apply($data, ['items.+item-1' => $updatedItem]);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('New Name', $data['items'][0]['name']);
    }

    #[Test]
    public function it_deep_updates_item_field()
    {
        $data = ['items' => [['_id' => 'item-1', 'system' => ['equipped' => false]]]];
        $this->applier->apply($data, ['items.item-1.system.equipped' => true]);

        $this->assertTrue($data['items'][0]['system']['equipped']);
    }

    #[Test]
    public function it_deletes_item_with_null()
    {
        $data = ['items' => [['_id' => 'item-1'], ['_id' => 'item-2']]];
        $this->applier->apply($data, ['items.item-1' => null]);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('item-2', $data['items'][0]['_id']);
    }

    #[Test]
    public function it_replaces_entire_item()
    {
        $data = ['items' => [['_id' => 'item-1', 'name' => 'Old']]];
        $this->applier->apply($data, ['items.item-1' => ['_id' => 'item-1', 'name' => 'New']]);

        $this->assertEquals('New', $data['items'][0]['name']);
    }

    #[Test]
    public function it_deletes_field_with_null()
    {
        $data = ['system' => ['stats' => ['body' => ['value' => 10, 'max' => 20]]]];
        $this->applier->apply($data, ['system.stats.body.max' => null]);

        $this->assertArrayNotHasKey('max', $data['system']['stats']['body']);
    }

    #[Test]
    public function it_creates_nested_arrays()
    {
        $data = [];
        $this->applier->apply($data, ['a.b.c' => 'deep']);

        $this->assertEquals('deep', $data['a']['b']['c']);
    }

    #[Test]
    public function it_handles_multiple_deltas()
    {
        $data = ['system' => ['stats' => ['body' => ['value' => 10]]]];
        $this->applier->apply($data, [
            'system.stats.body.value' => 20,
            'system.stats.ref.value' => 8,
        ]);

        $this->assertEquals(20, $data['system']['stats']['body']['value']);
        $this->assertEquals(8, $data['system']['stats']['ref']['value']);
    }
}
