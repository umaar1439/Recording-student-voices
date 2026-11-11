<?php

namespace Tests\Feature;

use App\Support\QuranSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        QuranSamples::seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('مِحراب التلاوة');
    }
}
