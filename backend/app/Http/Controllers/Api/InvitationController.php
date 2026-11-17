<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendWhatsAppRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use App\Services\InvitationService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use RuntimeException;

class InvitationController extends Controller
{
    public function __construct(
        private InvitationService $invitations,
        private WhatsAppService $whatsapp
    ) {
    }

    public function index(Request $request)
    {
        $query = Invitation::query()
            ->with(['guest', 'event', 'attendance'])
            ->latest();

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->query('delivery_status')) {
            $query->where('delivery_status', $status);
        }

        if ($attendance = $request->query('attendance_status')) {
            if ($attendance === 'present') {
                $query->whereHas('guest.attendance');
            } elseif ($attendance === 'absent') {
                $query->whereDoesntHave('guest.attendance');
            }
        }

        if ($search = $request->query('search')) {
            $query->whereHas('guest', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function forEvent(Event $event)
    {
        return response()->json([
            'success' => true,
            'data' => $event->invitations()
                ->with(['guest', 'attendance'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Guest $guest)
    {
        $guest->load(['event.template', 'attendance']);
        $invitation = $this->invitations->generateForGuest($guest);

        return response()->json([
            'success' => true,
            'data' => [
                'guest' => $guest,
                'event' => $guest->event,
                'invitation' => $invitation,
                'attendance' => $guest->attendance,
            ],
        ]);
    }

    public function generate(Guest $guest)
    {
        $invitation = $this->invitations->generateForGuest($guest);

        return response()->json([
            'success' => true,
            'message' => 'Link undangan dan QR Code berhasil dibuat.',
            'data' => [
                'invitation' => $invitation->load(['guest', 'event']),
                'qr_code_url' => url($invitation->qr_code_path),
            ],
        ]);
    }

    public function sendWhatsApp(SendWhatsAppRequest $request, Guest $guest)
    {
        $invitation = $this->invitations->generateForGuest($guest);
        $invitation->load(['guest', 'event']);

        $message = $this->invitations->buildMessage($invitation, $request->validated('message'));

        if ($request->validated('mode') === 'click') {
            $waUrl = $this->invitations->clickToChatUrl($guest->whatsapp_number, $message);

            // Click-to-chat cannot confirm delivery. Mark as "menunggu"
            // (queued) and let the admin confirm after sending.
            $invitation->update([
                'delivery_status' => Invitation::STATUS_QUEUED,
                'sent_at' => null,
                'delivery_error' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pesan WhatsApp siap dikirim. Tekan tombol Kirim di WhatsApp, lalu tandai terkirim.',
                'data' => [
                    'mode' => 'click',
                    'wa_url' => $waUrl,
                    'message' => $message,
                    'invitation' => $invitation->fresh()->load(['guest', 'event']),
                ],
            ]);
        }

        try {
            $result = $this->whatsapp->send($invitation, $message);

            $invitation->update([
                'delivery_status' => Invitation::STATUS_SENT,
                'sent_at' => now(),
                'delivery_error' => null,
                'provider_message_id' => $result['message_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Undangan berhasil dikirim melalui WhatsApp Business API.',
                'data' => [
                    'mode' => 'api',
                    'invitation' => $invitation->fresh()->load(['guest', 'event']),
                ],
            ]);
        } catch (RuntimeException $e) {
            $invitation->update([
                'delivery_status' => Invitation::STATUS_FAILED,
                'delivery_error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'mode' => 'api',
                    'invitation' => $invitation->fresh()->load(['guest', 'event']),
                ],
            ], 422);
        }
    }

    /**
     * Manual confirmation after the admin pressed send in WhatsApp
     * Click-to-Chat. Never called automatically.
     */
    public function markSent(Invitation $invitation)
    {
        $invitation->update([
            'delivery_status' => Invitation::STATUS_SENT,
            'sent_at' => now(),
            'delivery_error' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status pengiriman ditandai terkirim.',
            'data' => $invitation->fresh()->load(['guest', 'event']),
        ]);
    }
}
