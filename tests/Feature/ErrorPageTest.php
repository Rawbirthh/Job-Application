<?php

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware('web')->get('/__error-page-test/{status}', function (int $status): never {
        abort($status);
    });
});

test('404 renders the branded error page', function (): void {
    $this->get('/__error-page-test/404')
        ->assertNotFound()
        ->assertSee("We couldn't find that page")
        ->assertSee('Error 404')
        ->assertSee('Go back')
        ->assertDontSee('Browse jobs')
        ->assertDontSee('Dashboard')
        ->assertSee('NextStep')
        ->assertSee('nextstep-mark.png');
});

test('403 renders the branded error page', function (): void {
    $this->get('/__error-page-test/403')
        ->assertForbidden()
        ->assertSee("You don't have access to this page")
        ->assertSee('Error 403')
        ->assertSee('Go back')
        ->assertDontSee('Browse jobs')
        ->assertDontSee('Dashboard');
});

test('419 renders the branded error page', function (): void {
    $this->get('/__error-page-test/419')
        ->assertStatus(419)
        ->assertSee('Your session timed out')
        ->assertSee('Go back')
        ->assertDontSee('href="/login"', false);
});
