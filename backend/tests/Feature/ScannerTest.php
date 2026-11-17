<?php

namespace Tests\Feature;

use App\Models\Attendance;
use Laravel\Sanctum\Sanctum;

class ScannerTest extends ApiTestCase
{
    public function test_valid_token_can_be_validated_for_the_selected_event(): void
    {
        $scanner = $this->makeScanner();
        Sanctum::actingAs($scanner);

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $this->postJson('/api/scanner/validate', [
            'event_id' => $event->id,
            'token' => $guest->unique_token,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('already_checked_in', false)
            ->assertJsonPath('code', 'valid')
            ->assertJsonPath('data.guest.id', $guest->id);
    }

    public function test_unknown_token_is_rejected(): void
    {
        Sanctum::actingAs($this->makeScanner());
        $event = $this->makeEvent();

        $this->postJson('/api/scanner/validate', [
            'event_id' => $event->id,
            'token' => 'token-palsu',
        ])->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('code', 'invalid_token');
    }

    public function test_token_from_another_event_is_rejected(): void
    {
        Sanctum::actingAs($this->makeScanner());

        $eventA = $this->makeEvent(['name' => 'Acara A']);
        $eventB = $this->makeEvent(['name' => 'Acara B']);
        $guest = $this->makeGuest($eventA);

        $this->postJson('/api/scanner/validate', [
            'event_id' => $eventB->id,
            'token' => $guest->unique_token,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'wrong_event')
            ->assertJsonPath('data.selected_event', 'Acara B');
    }

    public function test_scanner_can_check_in_a_guest_and_the_attendance_is_recorded(): void
    {
        $scanner = $this->makeScanner();
        Sanctum::actingAs($scanner);

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $response = $this->postJson('/api/scanner/check-in', [
            'event_id' => $event->id,
            'token' => $guest->unique_token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('checked_in', true)
            ->assertJsonPath('already_checked_in', false)
            ->assertJsonPath('code', 'checked_in')
            ->assertJsonPath('data.attendance.checked_in_by', $scanner->id);

        $this->assertDatabaseHas('attendance', [
            'guest_id' => $guest->id,
            'event_id' => $event->id,
            'status' => 'present',
            'checked_in_by' => $scanner->id,
        ]);
    }

    public function test_duplicate_check_in_is_impossible_and_keeps_one_attendance_row(): void
    {
        Sanctum::actingAs($this->makeScanner());

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $payload = [
            'event_id' => $event->id,
            'token' => $guest->unique_token,
        ];

        $this->postJson('/api/scanner/check-in', $payload)
            ->assertOk()
            ->assertJsonPath('code', 'checked_in');

        $second = $this->postJson('/api/scanner/check-in', $payload);

        $second->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('checked_in', true)
            ->assertJsonPath('already_checked_in', true)
            ->assertJsonPath('code', 'already_checked_in');

        $this->assertSame(1, Attendance::where('guest_id', $guest->id)->count());
    }

    public function test_validate_reports_already_checked_in_guests(): void
    {
        Sanctum::actingAs($this->makeScanner());

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $payload = [
            'event_id' => $event->id,
            'token' => $guest->unique_token,
        ];

        $this->postJson('/api/scanner/check-in', $payload)->assertOk();

        $this->postJson('/api/scanner/validate', $payload)
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('already_checked_in', true)
            ->assertJsonPath('code', 'already_checked_in');
    }

    public function test_public_invitation_shows_checked_in_state_after_scan(): void
    {
        $scanner = $this->makeScanner(['name' => 'Petugas Pintu']);
        Sanctum::actingAs($scanner);

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $this->postJson('/api/scanner/check-in', [
            'event_id' => $event->id,
            'token' => $guest->unique_token,
        ])->assertOk();

        $this->getJson('/api/public/invitations/' . $guest->unique_token)
            ->assertOk()
            ->assertJsonPath('data.checked_in', true)
            ->assertJsonPath('data.attendance.checked_in_by', 'Petugas Pintu');
    }

    public function test_check_in_requires_a_valid_event_and_token(): void
    {
        Sanctum::actingAs($this->makeScanner());

        $this->postJson('/api/scanner/check-in', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_id', 'token']);

        $this->postJson('/api/scanner/check-in', [
            'event_id' => 9999,
            'token' => 'apa-saja',
        ])->assertStatus(422)->assertJsonValidationErrors('event_id');
    }

    public function test_scanner_event_list_includes_guest_and_attendance_counts(): void
    {
        Sanctum::actingAs($this->makeScanner());

        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $this->postJson('/api/scanner/check-in', [
            'event_id' => $event->id,
            'token' => $guest->unique_token,
        ])->assertOk();

        $this->getJson('/api/scanner/events')
            ->assertOk()
            ->assertJsonPath('data.0.guests_count', fn ($value) => (int) $value === 1)
            ->assertJsonPath('data.0.attended_count', fn ($value) => (int) $value === 1);
    }
}
