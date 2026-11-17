<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanRequest;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ScannerController extends Controller
{
    public function events()
    {
        $events = Event::query()
            ->withCount(['guests', 'attendance as attended_count'])
            ->orderByDesc('event_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Validate a scanned token for the selected event without writing data.
     */
    public function validateToken(ScanRequest $request)
    {
        $event = Event::findOrFail($request->validated('event_id'));

        $guest = Guest::where('unique_token', $request->validated('token'))
            ->with(['event.template', 'invitation', 'attendance.officer:id,name'])
            ->first();

        if (!$guest) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'code' => 'invalid_token',
                'message' => 'QR Code tidak valid. Token undangan tidak ditemukan.',
            ], 404);
        }

        if ((int) $guest->event_id !== (int) $event->id) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'code' => 'wrong_event',
                'message' => 'Undangan ini bukan untuk acara yang dipilih.',
                'data' => [
                    'selected_event' => $event->name,
                    'invitation_event' => $guest->event?->name,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'already_checked_in' => $guest->attendance !== null,
            'code' => $guest->attendance ? 'already_checked_in' : 'valid',
            'message' => $guest->attendance
                ? 'Tamu sudah tercatat hadir.'
                : 'Undangan valid dan siap dikonfirmasi kehadirannya.',
            'data' => [
                'guest' => $guest,
                'event' => $event,
                'invitation' => $guest->invitation,
                'attendance' => $guest->attendance,
            ],
        ]);
    }

    /**
     * Confirm attendance. The guest row is locked inside a transaction and a
     * unique constraint on (guest_id, event_id) guarantees that two officers
     * scanning the same QR at the same time cannot create a duplicate.
     */
    public function checkIn(ScanRequest $request)
    {
        $eventId = (int) $request->validated('event_id');
        $token = $request->validated('token');

        try {
            $result = DB::transaction(function () use ($eventId, $token, $request) {
                $guest = Guest::where('unique_token', $token)->lockForUpdate()->first();

                if (!$guest) {
                    return ['code' => 'invalid_token'];
                }

                $event = Event::find($eventId);

                if (!$event) {
                    return ['code' => 'event_not_found'];
                }

                if ((int) $guest->event_id !== $eventId) {
                    return ['code' => 'wrong_event', 'guest' => $guest, 'event' => $event];
                }

                $existing = Attendance::where('guest_id', $guest->id)
                    ->where('event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return ['code' => 'already_checked_in', 'guest' => $guest, 'event' => $event, 'attendance' => $existing];
                }

                $attendance = Attendance::create([
                    'guest_id' => $guest->id,
                    'event_id' => $eventId,
                    'status' => 'present',
                    'checked_in_at' => now(),
                    'checked_in_by' => $request->user()->id,
                ]);

                return ['code' => 'checked_in', 'guest' => $guest, 'event' => $event, 'attendance' => $attendance];
            });
        } catch (QueryException $e) {
            // Unique constraint raced by another officer: treat as already in.
            if ($this->isUniqueViolation($e)) {
                $guest = Guest::where('unique_token', $token)->first();
                $attendance = $guest
                    ? Attendance::where('guest_id', $guest->id)->where('event_id', $eventId)->first()
                    : null;

                return response()->json([
                    'success' => true,
                    'checked_in' => true,
                    'already_checked_in' => true,
                    'code' => 'already_checked_in',
                    'message' => 'Tamu sudah tercatat hadir (check-in paralel terdeteksi).',
                    'data' => [
                        'guest' => $guest,
                        'attendance' => $attendance?->load('officer:id,name'),
                    ],
                ]);
            }

            throw $e;
        }

        if ($result['code'] === 'invalid_token') {
            return response()->json([
                'success' => false,
                'checked_in' => false,
                'code' => 'invalid_token',
                'message' => 'QR Code tidak valid. Token undangan tidak ditemukan.',
            ], 404);
        }

        if ($result['code'] === 'event_not_found') {
            return response()->json([
                'success' => false,
                'checked_in' => false,
                'code' => 'event_not_found',
                'message' => 'Acara tidak ditemukan.',
            ], 404);
        }

        if ($result['code'] === 'wrong_event') {
            return response()->json([
                'success' => false,
                'checked_in' => false,
                'code' => 'wrong_event',
                'message' => 'Undangan ini bukan untuk acara yang dipilih.',
                'data' => [
                    'selected_event' => $result['event']->name,
                    'invitation_event' => $result['guest']->event?->name,
                ],
            ], 422);
        }

        $already = $result['code'] === 'already_checked_in';

        return response()->json([
            'success' => true,
            'checked_in' => true,
            'already_checked_in' => $already,
            'code' => $result['code'],
            'message' => $already
                ? 'Tamu sudah tercatat hadir sebelumnya.'
                : 'Kehadiran tamu berhasil dicatat.',
            'data' => [
                'guest' => $result['guest']->load(['event', 'invitation']),
                'event' => $result['event'],
                'attendance' => $result['attendance']->load('officer:id,name'),
            ],
        ]);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
