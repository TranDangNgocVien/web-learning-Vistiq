<?php

namespace Tests\Learner\Feature\Authentication;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\Learner\TestCase;
use VistiqLearner\Models\User;

class PasswordResetStabilityAuditTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_password_reset_revokes_existing_sessions(): void
    {
        $user = User::factory()->create();
        $version = $user->session_version;
        $token = Password::createToken($user);
        DB::table('sessions')->insert([
            'id' => 'audit-compromised-session', 'user_id' => $user->id,
            'payload' => '', 'last_activity' => time(),
        ]);

        $this->post('/reset-password', $this->credentials($user, $token))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertSame($version + 1, $user->fresh()->session_version);
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post('/reset-password', $this->credentials($user, $token))->assertRedirect(route('login'));
        $this->post('/reset-password', $this->credentials($user, $token))->assertSessionHasErrors('email');
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(config('auth.passwords.users.expire') + 1)->minutes();
        $this->post('/reset-password', $this->credentials($user, $token))->assertSessionHasErrors('email');
    }

    private function credentials(User $user, string $token): array
    {
        return [
            'email' => $user->email, 'token' => $token,
            'password' => 'NewAuditPassword123', 'password_confirmation' => 'NewAuditPassword123',
        ];
    }
}
