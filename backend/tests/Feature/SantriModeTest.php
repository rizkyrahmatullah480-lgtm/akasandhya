<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Guest;
use Laravel\Sanctum\Sanctum;

class SantriModeTest extends ApiTestCase
{
    public function test_admin_gets_one_default_santri_event_and_can_add_a_guest_with_a_code(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/santri/overview')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.event_type', 'santri')
            ->assertJsonPath('data.stats.total', 0);

        $this->assertSame(1, Event::where('event_type', 'santri')->count());

        $response = $this->postJson('/api/santri/guests', [
            'name' => 'Ahmad Fauzi',
            'tipe' => 'Santri',
            'wali_angkatan' => 'Wali Ahmad Subki',
            'whatsapp_number' => '081298761001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Ahmad Fauzi')
            ->assertJsonPath('data.category', 'Santri')
            ->assertJsonPath('data.santri_code', 'S001')
            ->assertJsonPath('data.invitation.invitation_url', fn ($value) => str_contains($value, '/santri/undangan/'));

        $this->assertDatabaseHas('guests', [
            'name' => 'Ahmad Fauzi',
            'event_id' => Event::where('event_type', 'santri')->first()->id,
            'santri_code' => 'S001',
            'wali_angkatan' => 'Wali Ahmad Subki',
        ]);

        $this->getJson('/api/santri/overview')
            ->assertOk()
            ->assertJsonPath('data.stats.santri', 1)
            ->assertJsonPath('data.stats.total', 1);
    }

    public function test_compact_guest_list_omits_unused_relations(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/santri/guests', [
            'name' => 'Compact Guest',
            'tipe' => 'Alumni',
            'wali_angkatan' => '2024',
            'whatsapp_number' => '081298761099',
        ])->assertCreated();

        $json = $this->getJson('/api/santri/guests?compact=1')
            ->assertOk()
            ->json();

        $guest = $json['data'][0];

        $this->assertArrayHasKey('unique_token', $guest);
        $this->assertArrayHasKey('santri_code', $guest);
        $this->assertArrayNotHasKey('invitation', $guest);
        $this->assertArrayNotHasKey('event', $guest);
        $this->assertArrayNotHasKey('officer', $guest['attendance'] ?? []);
    }

    public function test_scanner_can_validate_and_check_in_using_the_short_santri_code(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/santri/guests', [
            'name' => 'Siti Aisyah',
            'tipe' => 'Santri',
            'wali_angkatan' => 'Wali Abdul Rahman',
            'whatsapp_number' => '081298761003',
        ])->assertCreated()->json('data');

        $scanner = $this->makeScanner();
        Sanctum::actingAs($scanner);

        $this->getJson('/api/santri/guests')
            ->assertOk()
            ->assertJsonPath('data.0.santri_code', $created['santri_code']);

        $this->postJson('/api/santri/scanner/validate', ['code' => $created['santri_code']])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('already_checked_in', false)
            ->assertJsonPath('data.guest.id', $created['id']);

        $this->postJson('/api/santri/scanner/check-in', ['code' => $created['santri_code']])
            ->assertOk()
            ->assertJsonPath('checked_in', true)
            ->assertJsonPath('code', 'checked_in')
            ->assertJsonPath('data.attendance.checked_in_by', $scanner->id);

        $this->postJson('/api/santri/scanner/check-in', ['code' => $created['santri_code']])
            ->assertOk()
            ->assertJsonPath('already_checked_in', true)
            ->assertJsonPath('code', 'already_checked_in');

        $this->assertSame(1, Attendance::where('guest_id', $created['id'])->count());
    }

    public function test_public_santri_invitation_exposes_the_code_and_santri_link(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $created = $this->postJson('/api/santri/guests', [
            'name' => 'Fikri Maulana',
            'tipe' => 'Alumni',
            'wali_angkatan' => 'Angkatan 2024',
            'whatsapp_number' => '081298761006',
        ])->assertCreated()->json('data');

        $this->assertSame('A001', $created['santri_code']);

        $this->getJson('/api/public/invitations/' . $created['unique_token'])
            ->assertOk()
            ->assertJsonPath('data.guest.name', 'Fikri Maulana')
            ->assertJsonPath('data.guest.santri_code', 'A001')
            ->assertJsonPath('data.guest.wali_angkatan', 'Angkatan 2024')
            ->assertJsonPath('data.event.event_type', 'santri');
    }

    public function test_santri_whatsapp_greets_parent_while_invitation_keeps_santri_name(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $created = $this->postJson('/api/santri/guests', [
            'name' => 'Ahmad Fauzi',
            'tipe' => 'Santri',
            'wali_angkatan' => 'Bapak Ahmad Subki',
            'whatsapp_number' => '081298761001',
        ])->assertCreated()->json('data');

        $response = $this->postJson('/api/guests/' . $created['id'] . '/send-whatsapp', [
            'mode' => 'click',
        ])->assertOk();

        parse_str(parse_url($response->json('data.wa_url'), PHP_URL_QUERY), $query);

        $this->assertStringContainsString('Kepada Yth. *Bapak Ahmad Subki*', $query['text']);
        $this->assertStringNotContainsString('Kepada Yth. *Ahmad Fauzi*', $query['text']);

        $this->getJson('/api/public/invitations/' . $created['unique_token'])
            ->assertOk()
            ->assertJsonPath('data.guest.name', 'Ahmad Fauzi');
    }

    public function test_scanner_cannot_create_or_delete_santri_guests(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $created = $this->postJson('/api/santri/guests', [
            'name' => 'Nur Hidayah',
            'tipe' => 'Santri',
            'whatsapp_number' => '081298761004',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($this->makeScanner());

        $this->postJson('/api/santri/guests', [
            'name' => 'Tidak Boleh',
            'tipe' => 'Santri',
            'whatsapp_number' => '081298761099',
        ])->assertForbidden();

        $this->deleteJson('/api/santri/guests/' . $created['id'])->assertForbidden();

        $this->assertDatabaseHas('guests', ['id' => $created['id']]);
    }
}
