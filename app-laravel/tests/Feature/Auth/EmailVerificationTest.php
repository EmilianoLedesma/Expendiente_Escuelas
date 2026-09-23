<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response
            ->assertSeeVolt('pages.auth.verify-email')
            ->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('tramite.preregistro', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_is_sent_from_the_tramite_to_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/tramite/preregistro')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_reaches_the_tramite(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/tramite/preregistro')->assertOk();
    }

    public function test_every_tramite_route_requires_a_verified_email(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'tramite/'));

        $this->assertNotEmpty($rutas);
        foreach ($rutas as $ruta) {
            $this->assertContains('verified', $ruta->gatherMiddleware(), $ruta->uri());
            $this->assertContains('auth', $ruta->gatherMiddleware(), $ruta->uri());
        }
    }

    public function test_resend_when_already_verified_goes_to_the_tramite(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('pages.auth.verify-email')
            ->call('sendVerification')
            ->assertRedirect(route('tramite.preregistro', absolute: false));
    }
}
