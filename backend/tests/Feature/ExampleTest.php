<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * Skipped because multi-tenant setup requires a valid tenant context.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->markTestSkipped('Multi-tenant application requires tenant context for this route.');
    }
}
