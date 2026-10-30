<?php

namespace Tests\Learner\Feature\Authentication;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\Learner\TestCase;
use VistiqCore\Models\Role;
use VistiqLearner\Models\User;

class LearnerAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_learner_can_register_with_valid_data(): void
    {
        $response = $this->post('/register', [
            'name' => 'Hoc Vien Moi',
            'email' => 'hocvien@example.com',
            'password' => 'MatKhauMoi123',
            'password_confirmation' => 'MatKhauMoi123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'hocvien@example.com',
            'name' => 'Hoc Vien Moi',
        ]);
    }

    public function test_learner_can_register_with_minimum_8_characters_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'Tam Ky Tu',
            'email' => 'tamkytu@example.com',
            'password' => 'Pass1234',
            'password_confirmation' => 'Pass1234',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'tamkytu@example.com',
            'name' => 'Tam Ky Tu',
        ]);
    }

    public function test_registration_validation_rules(): void
    {
        // Missing fields
        $this->post('/register', [])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        // Password too short (<8 chars)
        $this->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'Short12', // 7 chars
            'password_confirmation' => 'Short12',
        ])->assertSessionHasErrors('password');

        // Password without numbers
        $this->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'OnlyLettersPassword',
            'password_confirmation' => 'OnlyLettersPassword',
        ])->assertSessionHasErrors('password');

        // Mismatched confirmation
        $this->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'MatKhauMoi123',
            'password_confirmation' => 'KhongKhop123',
        ])->assertSessionHasErrors('password');

        // Duplicate email
        User::factory()->create(['email' => 'existing@example.com']);
        $this->post('/register', [
            'name' => 'Test',
            'email' => 'EXISTING@example.com',
            'password' => 'MatKhauMoi123',
            'password_confirmation' => 'MatKhauMoi123',
        ])->assertSessionHasErrors('email');
    }

    public function test_learner_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'status' => 'active',
        ]);

        $response = $this->post('/login', [
            'email' => 'LEARNER@example.com',
            'password' => 'CorrectPassword123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_learner_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'learner@example.com',
            'password' => Hash::make('CorrectPassword123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'learner@example.com',
            'password' => 'WrongPassword999',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_learner_login_is_throttled_after_consecutive_failures(): void
    {
        User::factory()->create([
            'email' => 'learner@example.com',
            'password' => Hash::make('CorrectPassword123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'learner@example.com',
                'password' => 'WrongPassword',
            ])->assertSessionHasErrors('email');
        }

        // 6th attempt should be throttled
        $response = $this->post('/login', [
            'email' => 'learner@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('quá nhiều lần', session('errors')->first('email'));
    }

    public function test_locked_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'locked@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'status' => 'locked',
        ]);

        $this->post('/login', [
            'email' => 'locked@example.com',
            'password' => 'CorrectPassword123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_user_is_rejected_from_learner_login(): void
    {
        $this->seed(AdminCatalogSeeder::class);
        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminPassword123'),
        ]);
        $adminRole = Role::where('code', 'admin')->first();
        $adminUser->roles()->attach($adminRole);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'AdminPassword123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Tài khoản quản trị', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_forgot_password_does_not_enumerate_email(): void
    {
        $responseNonExistent = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $responseNonExistent->assertSessionHas('status', 'Nếu email này có tài khoản Vistiq, hướng dẫn đặt lại mật khẩu sẽ được gửi đến hộp thư của bạn.');

        $user = User::factory()->create(['email' => 'real@example.com']);
        $responseReal = $this->post('/forgot-password', [
            'email' => 'real@example.com',
        ]);

        $responseReal->assertSessionHas('status', 'Nếu email này có tài khoản Vistiq, hướng dẫn đặt lại mật khẩu sẽ được gửi đến hộp thư của bạn.');
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'resetme@example.com']);
        $token = Password::createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'resetme@example.com',
            'password' => 'BrandNewPassword123',
            'password_confirmation' => 'BrandNewPassword123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->assertTrue(Hash::check('BrandNewPassword123', $user->fresh()->password));
    }

    public function test_learner_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/logout');
        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
