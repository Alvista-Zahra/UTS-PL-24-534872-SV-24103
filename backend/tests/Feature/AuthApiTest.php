<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_an_api_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Inventory User',
            'email' => 'inventory@example.test',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'inventory@example.test')
            ->assertJsonPath('message', 'Registrasi berhasil.');

        $this->assertDatabaseHas('users', [
            'email' => 'inventory@example.test',
        ]);
        $this->assertNotSame(
            'password123',
            User::where('email', 'inventory@example.test')->value('password'),
        );
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_user_can_login_and_use_the_returned_token(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.test',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('message', 'Login berhasil.');

        $this->withToken($response->json('data.token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.test',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'login@example.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
