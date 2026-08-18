<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->post('/register', [
            'user_id' => 'test001',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'user_id' => 'test001',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_user_id_is_required(): void
    {
        $response = $this->post('/register', [
            'user_id' => null,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_id_must_be_between_5_and_15_characters(): void
    {
        foreach (['abcd', 'abcdefghijklmnop'] as $userId) {
            $response = $this->post('/register', [
                'user_id' => $userId,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertSessionHasErrors('user_id');
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_id_must_contain_only_lowercase_letters_and_numbers(): void
    {
        foreach (['Test01', 'test-01', 'test_01', 'テスト01'] as $userId) {
            $response = $this->post('/register', [
                'user_id' => $userId,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertSessionHasErrors('user_id');
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_id_must_be_unique(): void
    {
        User::factory()->create([
            'user_id' => 'test001',
        ]);

        $response = $this->post('/register', [
            'user_id' => 'test001',
            'name' => 'Another User',
            'email' => 'another@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_email_is_required_and_must_be_valid(): void
    {
        foreach ([null, 'invalid-email'] as $email) {
            $response = $this->post('/register', [
                'user_id' => 'test001',
                'name' => 'Test User',
                'email' => $email,
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            $response->assertSessionHasErrors('email');
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post('/register', [
            'user_id' => 'test002',
            'name' => 'Another User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_confirmation_must_match(): void
    {
        $response = $this->post('/register', [
            'user_id' => 'test001',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_new_user_is_not_email_verified(): void
    {
        $this->post('/register', [
            'user_id' => 'test001',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('user_id', 'test001')->first();

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
    }
}
