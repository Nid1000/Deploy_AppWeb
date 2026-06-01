<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationSmokeTest extends TestCase
{
    public function test_application_boots(): void
    {
        $this->assertTrue($this->app->bound('router'));
    }
}
