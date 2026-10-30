<?php

namespace Tests\Admin\Feature\Integration;

use Tests\Admin\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_is_the_learner_surface(): void
    {
        $this->get('/')->assertOk();
    }
}
