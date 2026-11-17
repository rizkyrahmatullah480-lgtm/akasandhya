<?php

namespace App\Services;

use App\Models\Invitation;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppService
{
    public function isConfigured(): bool
    {
        return (bool) config('whatsapp.api_enabled')
            && !empty(config('whatsapp.phone_number_id'))
            && !empty(config('whatsapp.access_token'));
    }

    /**
     * Send a message through the official WhatsApp Business Cloud API.
     *
     * @return array{message_id: ?string}
     * @throws RuntimeException when the provider is not configured or rejects
     *                          the message. We never report a fake success.
     */
    public function send(Invitation $invitation, string $message): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'WhatsApp Business API belum dikonfigurasi. Gunakan mode Click to Chat atau isi WHATSAPP_API_ENABLED, WHATSAPP_PHONE_NUMBER_ID, dan WHATSAPP_ACCESS_TOKEN pada .env backend.'
            );
        }

        $invitation->loadMissing(['guest', 'event']);
        $guest = $invitation->guest;
        $event = $invitation->event;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($guest->whatsapp_number),
        ];

        $templateName = config('whatsapp.template_name');

        if ($templateName) {
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $templateName,
                'language' => ['code' => config('whatsapp.template_language', 'id')],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $guest->whatsappRecipientName()],
                        ['type' => 'text', 'text' => $event->name],
                        ['type' => 'text', 'text' => (string) $event->event_date],
                        ['type' => 'text', 'text' => substr((string) $event->event_time, 0, 5)],
                        ['type' => 'text', 'text' => $event->location],
                        ['type' => 'text', 'text' => $invitation->invitation_url],
                    ],
                ]],
            ];
        } else {
            $payload['type'] = 'text';
            $payload['text'] = ['preview_url' => true, 'body' => $message];
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('whatsapp.api_version'),
            config('whatsapp.phone_number_id')
        );

        $response = Http::withToken(config('whatsapp.access_token'))
            ->acceptJson()
            ->timeout(20)
            ->post($url, $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message')
                ?? $response->json('error.error_user_msg')
                ?? ('HTTP ' . $response->status());

            throw new RuntimeException('WhatsApp API menolak pesan: ' . $error);
        }

        return [
            'message_id' => $response->json('messages.0.id'),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }
}
