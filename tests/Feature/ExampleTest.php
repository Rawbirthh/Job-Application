<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_route_remains_a_named_route_to_the_root_path()
    {
        $this->assertTrue(Route::has('home'));
        $this->assertSame('/', route('home', absolute: false));
    }
}
