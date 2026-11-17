<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class UserManagementTest extends ApiTestCase
{
    public function test_admin_can_create_update_and_delete_an_officer_account(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/users', [
            'name' => 'Petugas Baru',
            'email' => 'petugas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_SCANNER,
            'phone' => '081200000000',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'petugas@example.com');

        $userId = $created->json('data.id');

        $this->putJson('/api/users/' . $userId, [
            'name' => 'Petugas Diperbarui',
            'email' => 'petugas@example.com',
            'role' => User::ROLE_SCANNER,
            'phone' => '081211111111',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Petugas Diperbarui');

        $this->assertTrue(Hash::check('passwordbaru123', User::find($userId)->password));

        $this->deleteJson('/api/users/' . $userId)->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/users/' . $admin->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_scanner_cannot_manage_users(): void
    {
        Sanctum::actingAs($this->makeScanner());

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [])->assertForbidden();
    }
}
