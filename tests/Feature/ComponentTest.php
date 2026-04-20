<?php

namespace Tests\Feature;

use Tests\TestCase;

class ComponentTest extends TestCase
{
    public function test_guest_layout_component_can_be_rendered(): void
    {
        $view = $this->blade('<x-guest-layout>Test content</x-guest-layout>');

        $view->assertSee('Test content');
    }

    //Cyberpunk blade loads the Vue, so no template.
}
