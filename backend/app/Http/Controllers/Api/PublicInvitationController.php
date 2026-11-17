<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class PublicInvitationController extends Controller
{
    public function show(string $token)
    {
        $guest = Guest::where('unique_token', $token)
            ->with(['event.template', 'invitation', 'attendance.officer:id,name'])
            ->first();

        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'Undangan tidak ditemukan. Periksa kembali tautan yang Anda terima.',
            ], 404);
        }

        $event = $guest->event;
        $attendance = $guest->attendance;

        return response()->json([
            'success' => true,
            'data' => [
                'guest' => [
                    'id' => $guest->id,
                    'name' => $guest->name,
                    'category' => $guest->category,
                    'santri_code' => $guest->santri_code,
                    'wali_angkatan' => $guest->wali_angkatan,
                    'unique_token' => $guest->unique_token,
                ],
                'event' => $event,
                'template' => $event?->template,
                'invitation' => $guest->invitation,
                'attendance' => $attendance ? [
                    'status' => $attendance->status,
                    'checked_in_at' => $attendance->checked_in_at,
                    'checked_in_by' => $attendance->officer?->name,
                ] : null,
                'checked_in' => $attendance !== null,
            ],
        ]);
    }

    /**
     * Server generated QR Code (SVG). The token is the only payload, so no
     * guest personal data is embedded in the QR image.
     */
    public function qr(string $token)
    {
        $guest = Guest::where('unique_token', $token)->with('event')->first();

        if (!$guest) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak ditemukan.',
            ], 404);
        }

        $path = $guest->event?->event_type === 'santri' ? '/santri/undangan/' : '/undangan/';
        $url = config('whatsapp.frontend_url') . $path . $guest->unique_token;

        $renderer = new ImageRenderer(
            new RendererStyle(420, 2),
            new SvgImageBackEnd()
        );

        $svg = (new Writer($renderer))->writeString($url);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-' . substr($guest->unique_token, 0, 8) . '.svg"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
