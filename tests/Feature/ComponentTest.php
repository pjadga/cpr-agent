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

    public function test_app_layout_component_can_be_rendered(): void
    {
        $view = $this->blade('<x-app-layout>Test content</x-app-layout>');

        $view->assertSee('Test content');
    }
}
