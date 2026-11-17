<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Pengguna Tes',
            'email' => 'user' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => User::ROLE_ADMIN,
        ], $attributes));
    }

    protected function makeAdmin(array $attributes = []): User
    {
        return $this->makeUser(array_merge(['role' => User::ROLE_ADMIN], $attributes));
    }

    protected function makeScanner(array $attributes = []): User
    {
        return $this->makeUser(array_merge(['role' => User::ROLE_SCANNER], $attributes));
    }

    protected function makeEvent(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'name' => 'Pernikahan Contoh',
            'event_type' => 'pernikahan',
            'organizer' => 'Keluarga Contoh',
            'event_date' => '2026-12-01',
            'event_time' => '09:00',
            'location' => 'Gedung Contoh',
            'address' => 'Jl. Contoh No. 1, Jakarta',
            'description' => 'Acara pengujian.',
        ], $attributes));
    }

    protected function makeGuest(Event $event, array $attributes = []): Guest
    {
        return Guest::create(array_merge([
            'event_id' => $event->id,
            'name' => 'Tamu Contoh',
            'whatsapp_number' => '6281234567890',
            'category' => 'Keluarga',
        ], $attributes));
    }
}
