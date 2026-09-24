<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mirrors Illuminate\View\Middleware\ShareErrorsFromSession, which shares
        // an empty $errors bag on every real request but doesn't run for the
        // blade()/component() test helpers.
        View::share('errors', new ViewErrorBag);
    }
}
