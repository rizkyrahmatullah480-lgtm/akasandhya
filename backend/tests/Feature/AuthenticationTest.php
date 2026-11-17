<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class AuthenticationTest extends ApiTestCase
{
    public function test_admin_can_login_and_receives_a_token(): void
    {
        $this->makeAdmin([
            'name' => 'Admin Utama',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'ADMIN@example.com',
            'password' => 'admin123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonPath('data.user.role', User::ROLE_ADMIN)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->makeAdmin([
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'salah',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_account_without_supported_role_cannot_login(): void
    {
        $this->makeUser([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);

        $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ])->assertStatus(403)->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_read_profile_and_logout(): void
    {
        $user = $this->makeAdmin(['email' => 'admin@example.com']);

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@example.com');

        $this->postJson('/api/logout')->assertOk()->assertJsonPath('success', true);
    }

    public function test_authenticated_user_can_update_profile_and_password(): void
    {
        $user = $this->makeAdmin([
            'email' => 'admin@example.com',
            'password' => Hash::make('passwordlama'),
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/user/profile', [
            'name' => 'Admin Baru',
            'phone' => '081234567890',
        ])->assertOk()
            ->assertJsonPath('data.user.name', 'Admin Baru')
            ->assertJsonPath('data.user.phone', '081234567890');

        $this->putJson('/api/user/password', [
            'current_password' => 'passwordlama',
            'new_password' => 'passwordbaru123',
            'new_password_confirmation' => 'passwordbaru123',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('passwordbaru123', $user->fresh()->password));
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = $this->makeAdmin([
            'password' => Hash::make('passwordlama'),
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/user/password', [
            'current_password' => 'salah',
            'new_password' => 'passwordbaru123',
            'new_password_confirmation' => 'passwordbaru123',
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertTrue(Hash::check('passwordlama', $user->fresh()->password));
    }

    public function test_scanner_cannot_access_admin_only_endpoints(): void
    {
        Sanctum::actingAs($this->makeScanner());

        $this->getJson('/api/events')->assertForbidden();
        $this->getJson('/api/dashboard')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/events', [])->assertForbidden();
    }

    public function test_scanner_can_access_scanner_endpoints(): void
    {
        Sanctum::actingAs($this->makeScanner());

        $this->getJson('/api/scanner/events')->assertOk()->assertJsonPath('success', true);
    }

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/events')->assertUnauthorized();
        $this->getJson('/api/scanner/events')->assertUnauthorized();
    }
}
