<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    //
    protected function setUp(): void
    {
        parent::setUp();

        \NoCaptcha::shouldReceive('verifyResponse')
            ->andReturn(true);

        \NoCaptcha::shouldReceive('display')
            ->andReturn('<div id="captcha"></div>');

        \NoCaptcha::shouldReceive('renderJs')
            ->andReturn('<script></script>');
    }
}
