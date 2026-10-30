<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Learner\TestCase;
use VistiqLearner\Models\User;

class ApiAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_session_endpoint_returns_csrf_and_guest_state(): void
    {
        $this->getJson('/api/v1/session')->assertOk()->assertJsonStructure(['data' => ['csrf_token', 'user']])->assertJsonPath('data.user', null);
    }

    public function test_registration_creates_hashed_password_and_authenticated_session(): void
    {
        $response = $this->postJson('/api/v1/register', ['name' => 'Learner', 'email' => ' LEARNER@example.test ', 'password' => 'ValidPassword123', 'password_confirmation' => 'ValidPassword123']);

        $response->assertCreated()->assertJsonPath('data.user.email', 'learner@example.test')->assertJsonMissingPath('data.user.password')->assertJsonMissingPath('data.user.remember_token');
        $user = User::where('email', 'learner@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('ValidPassword123', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/v1/session')->assertJsonPath('data.user.id', $user->id);
    }

    public function test_duplicate_registration_returns_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/register', ['name' => 'Duplicate', 'email' => $user->email, 'password' => 'ValidPassword123', 'password_confirmation' => 'ValidPassword123'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_correct_existing_password_logs_in_and_logout_ends_the_session(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123']);

        $this->postJson('/api/v1/login', ['email' => strtoupper($user->email), 'password' => 'ValidPassword123'])->assertOk()->assertJsonPath('data.user.id', $user->id);
        $this->assertAuthenticatedAs($user);
        $this->postJson('/api/v1/logout')->assertOk()->assertJsonPath('data.user', null);
        $this->assertGuest();
    }

    public function test_incorrect_password_returns_422_and_protected_api_returns_401(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123']);

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'WrongPassword123'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->getJson('/api/v1/exams')->assertUnauthorized();
    }

    public function test_locked_account_cannot_login(): void
    {
        $user = User::factory()->create(['password' => 'ValidPassword123', 'status' => 'locked']);

        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'ValidPassword123'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_learner_and_admin_surfaces_are_public_without_a_gateway_key(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/admin/login')->assertOk();
    }

    public function test_mutation_without_csrf_token_is_rejected_outside_testing_mode(): void
    {
        $this->app['env'] = 'local';

        $this->postJson('/api/v1/register', ['name' => 'Learner'])->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
    }
}
