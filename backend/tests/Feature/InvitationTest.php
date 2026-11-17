<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class InvitationTest extends ApiTestCase
{
    public function test_guest_invitation_is_generated_with_a_public_url_and_qr_path(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $guest = $this->makeGuest($this->makeEvent());

        $response = $this->getJson('/api/guests/' . $guest->id . '/invitation')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guest.id', $guest->id);

        $invitation = Invitation::firstOrFail();

        $this->assertEquals($guest->id, $invitation->guest_id);
        $this->assertSame(48, strlen($guest->fresh()->unique_token));
        $this->assertStringContainsString($guest->unique_token, $invitation->invitation_url);
        $this->assertSame('/api/public/invitations/' . $guest->unique_token . '/qr', $invitation->qr_code_path);
        $response->assertJsonPath('data.invitation.id', $invitation->id);
    }

    public function test_public_invitation_can_be_opened_without_login(): void
    {
        $guest = $this->makeGuest($this->makeEvent(['name' => 'Acara Publik']));

        $response = $this->getJson('/api/public/invitations/' . $guest->unique_token);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guest.name', 'Tamu Contoh')
            ->assertJsonPath('data.event.name', 'Acara Publik')
            ->assertJsonPath('data.checked_in', false);
    }

    public function test_unknown_public_token_returns_404(): void
    {
        $this->getJson('/api/public/invitations/token-tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_public_qr_code_is_returned_as_svg(): void
    {
        $guest = $this->makeGuest($this->makeEvent());

        $response = $this->get('/api/public/invitations/' . $guest->unique_token . '/qr');

        $response->assertOk();
        $this->assertStringContainsString('image/svg+xml', $response->headers->get('content-type'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_click_to_chat_marks_invitation_queued_and_normalizes_phone(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $guest = $this->makeGuest($this->makeEvent(), [
            'name' => 'Ibu Sinta',
            'whatsapp_number' => '081234567890',
        ]);

        $response = $this->postJson('/api/guests/' . $guest->id . '/send-whatsapp', [
            'mode' => 'click',
        ])->assertOk()
            ->assertJsonPath('data.mode', 'click')
            ->assertJsonPath('data.invitation.delivery_status', Invitation::STATUS_QUEUED);

        $waUrl = $response->json('data.wa_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $waUrl);

        parse_str(parse_url($waUrl, PHP_URL_QUERY), $query);
        $this->assertStringContainsString('Ibu Sinta', $query['text']);
        $this->assertStringContainsString($guest->unique_token, $query['text']);
        $this->assertNull(Invitation::firstOrFail()->sent_at);
    }

    public function test_admin_can_manually_mark_a_click_to_chat_invitation_as_sent(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $guest = $this->makeGuest($this->makeEvent());

        $this->postJson('/api/guests/' . $guest->id . '/send-whatsapp', ['mode' => 'click'])
            ->assertOk();

        $invitation = Invitation::firstOrFail();

        $this->postJson('/api/invitations/' . $invitation->id . '/mark-sent')
            ->assertOk()
            ->assertJsonPath('data.delivery_status', Invitation::STATUS_SENT);

        $this->assertNotNull($invitation->fresh()->sent_at);
    }

    public function test_api_mode_fails_honestly_when_whatsapp_is_not_configured(): void
    {
        config([
            'whatsapp.api_enabled' => false,
            'whatsapp.phone_number_id' => null,
            'whatsapp.access_token' => null,
        ]);

        Sanctum::actingAs($this->makeAdmin());
        $guest = $this->makeGuest($this->makeEvent());

        $response = $this->postJson('/api/guests/' . $guest->id . '/send-whatsapp', [
            'mode' => 'api',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.invitation.delivery_status', Invitation::STATUS_FAILED)
            ->assertJsonPath('data.mode', 'api');

        $this->assertNotEmpty($response->json('data.invitation.delivery_error'));
        $this->assertNull($response->json('data.invitation.sent_at'));
    }

    public function test_api_mode_marks_sent_only_after_provider_accepts_the_message(): void
    {
        config([
            'whatsapp.api_enabled' => true,
            'whatsapp.phone_number_id' => '123456789',
            'whatsapp.access_token' => 'test-token',
            'whatsapp.template_name' => null,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test-123']],
            ], 200),
        ]);

        Sanctum::actingAs($this->makeAdmin());
        $guest = $this->makeGuest($this->makeEvent(), ['whatsapp_number' => '081234567890']);

        $this->postJson('/api/guests/' . $guest->id . '/send-whatsapp', ['mode' => 'api'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invitation.delivery_status', Invitation::STATUS_SENT)
            ->assertJsonPath('data.invitation.provider_message_id', 'wamid.test-123');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v20.0/123456789/messages'
                && $request['to'] === '6281234567890'
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    public function test_failed_provider_response_marks_invitation_failed(): void
    {
        config([
            'whatsapp.api_enabled' => true,
            'whatsapp.phone_number_id' => '123456789',
            'whatsapp.access_token' => 'test-token',
            'whatsapp.template_name' => null,
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid recipient'],
            ], 400),
        ]);

        Sanctum::actingAs($this->makeAdmin());
        $guest = $this->makeGuest($this->makeEvent());

        $this->postJson('/api/guests/' . $guest->id . '/send-whatsapp', ['mode' => 'api'])
            ->assertStatus(422)
            ->assertJsonPath('data.invitation.delivery_status', Invitation::STATUS_FAILED);

        $this->assertStringContainsString('Invalid recipient', Invitation::firstOrFail()->delivery_error);
    }

    public function test_whatsapp_webhook_verification_and_delivery_status_update(): void
    {
        config(['whatsapp.webhook_verify_token' => 'rahasia-webhook']);

        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=rahasia-webhook&hub.challenge=abc123')
            ->assertOk()
            ->assertSee('abc123');

        $this->get('/api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=salah&hub.challenge=abc123')
            ->assertForbidden();

        $guest = $this->makeGuest($this->makeEvent());
        $invitation = Invitation::create([
            'guest_id' => $guest->id,
            'event_id' => $guest->event_id,
            'invitation_url' => 'http://localhost:5173/undangan/' . $guest->unique_token,
            'delivery_status' => Invitation::STATUS_QUEUED,
            'provider_message_id' => 'wamid.webhook-1',
        ]);

        $this->postJson('/api/webhooks/whatsapp', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.webhook-1',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertSame(Invitation::STATUS_SENT, $invitation->fresh()->delivery_status);
        $this->assertNotNull($invitation->fresh()->sent_at);
    }
}
