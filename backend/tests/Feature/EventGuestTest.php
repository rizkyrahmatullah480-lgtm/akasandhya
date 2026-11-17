<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use Laravel\Sanctum\Sanctum;

class EventGuestTest extends ApiTestCase
{
    public function test_admin_can_create_update_and_delete_an_event(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $payload = [
            'name' => 'Seminar Teknologi',
            'event_type' => 'seminar',
            'organizer' => 'Komunitas Digital',
            'event_date' => '2027-01-15',
            'event_time' => '13:30',
            'location' => 'Auditorium Kota',
            'address' => 'Jl. Merdeka No. 10, Bandung',
            'description' => 'Seminar tahunan.',
        ];

        $created = $this->postJson('/api/events', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Seminar Teknologi');

        $eventId = $created->json('data.id');

        $this->putJson('/api/events/' . $eventId, array_merge($payload, [
            'name' => 'Seminar Teknologi 2027',
        ]))->assertOk()->assertJsonPath('data.name', 'Seminar Teknologi 2027');

        $this->deleteJson('/api/events/' . $eventId)->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('events', ['id' => $eventId]);
    }

    public function test_event_validation_errors_are_returned(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/events', [
            'name' => '',
            'event_type' => 'tidak-valid',
            'event_date' => 'bukan-tanggal',
            'event_time' => '25:99',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'name',
            'event_type',
            'organizer',
            'event_date',
            'event_time',
            'location',
        ]);
    }

    public function test_adding_a_guest_creates_a_unique_invitation_token(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $event = $this->makeEvent();

        $response = $this->postJson('/api/events/' . $event->id . '/guests', [
            'name' => 'Budi Santoso',
            'whatsapp_number' => '081234567890',
            'category' => 'Kantor',
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $guest = Guest::firstOrFail();

        $this->assertSame('6281234567890', $guest->whatsapp_number);
        $this->assertSame(48, strlen($guest->unique_token));
        $this->assertNotSame((string) $guest->id, $guest->unique_token);
        $this->assertNotNull($guest->invitation);
        $this->assertStringContainsString($guest->unique_token, $guest->invitation->invitation_url);
        $this->assertEquals($event->id, $guest->invitation->event_id);
    }

    public function test_duplicate_guest_token_is_retried_and_stays_unique(): void
    {
        $event = $this->makeEvent();
        $first = $this->makeGuest($event, ['unique_token' => str_repeat('a', 48)]);

        $second = $this->makeGuest($event, ['name' => 'Tamu Kedua']);

        $this->assertNotSame($first->unique_token, $second->unique_token);
        $this->assertSame(2, Guest::query()->distinct()->count('unique_token'));
    }

    public function test_guest_requires_a_valid_whatsapp_number(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $event = $this->makeEvent();

        $this->postJson('/api/events/' . $event->id . '/guests', [
            'name' => 'Nomor Salah',
            'whatsapp_number' => '12345',
        ])->assertStatus(422)->assertJsonValidationErrors('whatsapp_number');
    }

    public function test_admin_can_update_and_delete_a_guest(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $this->putJson('/api/guests/' . $guest->id, [
            'name' => 'Tamu Diperbarui',
            'whatsapp_number' => '+62 812 3456 7891',
            'category' => 'VIP',
        ])->assertOk()->assertJsonPath('data.name', 'Tamu Diperbarui');

        $this->assertSame('6281234567891', $guest->fresh()->whatsapp_number);

        $this->deleteJson('/api/guests/' . $guest->id)->assertOk();

        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
    }

    public function test_admin_can_import_guests_from_an_array(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $event = $this->makeEvent();

        $response = $this->postJson('/api/events/' . $event->id . '/guests/import', [
            'guests' => [
                ['name' => 'Tamu Impor', 'whatsapp_number' => '081298765432', 'category' => 'Keluarga'],
                ['name' => 'Tamu Salah', 'whatsapp_number' => 'bukan-nomor'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(1, Guest::count());
        $this->assertSame(1, Invitation::count());
    }

    public function test_guest_counts_and_stats_are_returned_on_event_detail(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $event = $this->makeEvent();
        $this->makeGuest($event);
        $this->makeGuest($event, ['name' => 'Tamu Lain', 'whatsapp_number' => '6281234567891']);

        $this->getJson('/api/events/' . $event->id)
            ->assertOk()
            ->assertJsonPath('data.stats.total_guests', 2)
            ->assertJsonPath('data.stats.attended', fn ($value) => (int) $value === 0);
    }

    public function test_event_deletion_cascades_to_guests_and_invitations(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        Invitation::create([
            'guest_id' => $guest->id,
            'event_id' => $event->id,
            'invitation_url' => 'http://localhost:5173/undangan/' . $guest->unique_token,
        ]);

        Sanctum::actingAs($this->makeAdmin());

        $this->deleteJson('/api/events/' . $event->id)->assertOk();

        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
        $this->assertDatabaseMissing('invitations', ['guest_id' => $guest->id]);
    }
}
