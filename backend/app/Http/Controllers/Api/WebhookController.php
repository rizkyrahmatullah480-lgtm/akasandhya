<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Meta webhook verification handshake.
     * GET /api/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        if ($mode === 'subscribe' && $token && hash_equals((string) config('whatsapp.webhook_verify_token'), (string) $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['success' => false, 'message' => 'Verifikasi webhook gagal.'], 403);
    }

    /**
     * Delivery status updates from WhatsApp Business Cloud API.
     */
    public function receive(Request $request)
    {
        $statuses = data_get($request->all(), 'entry.0.changes.0.value.statuses', []);

        foreach ($statuses as $status) {
            $messageId = $status['id'] ?? null;
            $state = $status['status'] ?? null;

            if (!$messageId || !$state) {
                continue;
            }

            $invitation = Invitation::where('provider_message_id', $messageId)->first();

            if (!$invitation) {
                continue;
            }

            if (in_array($state, ['sent', 'delivered', 'read'], true)) {
                $invitation->update([
                    'delivery_status' => Invitation::STATUS_SENT,
                    'sent_at' => $invitation->sent_at ?? now(),
                    'delivery_error' => null,
                ]);
            } elseif ($state === 'failed') {
                $invitation->update([
                    'delivery_status' => Invitation::STATUS_FAILED,
                    'delivery_error' => data_get($status, 'errors.0.title', 'Pengiriman gagal menurut WhatsApp.'),
                ]);
            }
        }

        return response()->json(['success' => true]);
    }
}
