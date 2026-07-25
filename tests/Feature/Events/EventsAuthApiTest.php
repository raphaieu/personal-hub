<?php

namespace Tests\Feature\Events;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EventsAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_issues_token_and_sends_verification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Joana Organizadora',
            'email' => 'Joana@Example.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'joana@example.com')
            ->assertJsonPath('data.user.emailVerified', false)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', ['email' => 'joana@example.com']);

        $user = User::query()->where('email', 'joana@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);

        // Token emitido é funcional imediatamente (verificação exigida só para publicar).
        $this->withHeader('Authorization', 'Bearer '.$response->json('data.token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'joana@example.com');
    }

    public function test_register_validates_payload(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'nao-e-email',
            'password' => 'curta',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_returns_token_and_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['email' => 'dono@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'dono@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.emailVerified', true)
            ->assertJsonStructure(['data' => ['token']]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'dono@example.com',
            'password' => 'errada',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('events-app')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Token revogado de fato — em novo processo/request real, o guard rejeita (401).
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_signed_verification_link_marks_email_as_verified_and_redirects_to_front(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('api.verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)
            ->assertRedirect(rtrim((string) config('events.frontend_url'), '/').'/auth/verified');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_link_rejects_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('api.verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('outro-email@example.com'),
        ]);

        $this->get($url)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_notification_only_when_unverified(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/email/verify/resend')
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo($user, VerifyEmail::class);

        Notification::fake();
        $verified = User::factory()->create();
        Sanctum::actingAs($verified);

        $this->postJson('/api/v1/auth/email/verify/resend')
            ->assertOk()
            ->assertJsonPath('message', 'Seu e-mail já está verificado.');

        Notification::assertNothingSent();
    }
}
