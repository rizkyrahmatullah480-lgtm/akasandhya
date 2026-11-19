<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Template;
use App\Services\InvitationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Simple "Mode Santri" API.
 *
 * This controller is additive: it reuses the existing Invitaku tables and
 * keeps the full event/invitation admin untouched. One event with
 * event_type = "santri" is created automatically and every santri-mode
 * request is scoped to that single event.
 */
class SantriController extends Controller
{
    public function __construct(private InvitationService $invitations)
    {
    }

    public function overview()
    {
        return response()->json([
            'success' => true,
            'data' => $this->overviewData(),
        ]);
    }

    public function index(Request $request)
    {
        $event = $this->defaultEvent();
        $compact = $request->boolean('compact');

        $query = Guest::query()
            ->where('event_id', $event->id)
            ->with($compact ? ['attendance'] : ['invitation', 'attendance.officer:id,name']);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('santri_code', 'like', "%{$search}%")
                    ->orWhere('wali_angkatan', 'like', "%{$search}%")
                    ->orWhere('whatsapp_number', 'like', "%{$search}%");
            });
        }

        if (in_array($request->query('tipe'), ['Santri', 'Alumni'], true)) {
            $query->where('category', $request->query('tipe'));
        }

        if ($request->query('status') === 'hadir') {
            $query->whereHas('attendance');
        } elseif ($request->query('status') === 'belum') {
            $query->whereDoesntHave('attendance');
        }

        $guests = $query->orderBy('name')->get();

        foreach ($guests as $guest) {
            if (empty($guest->santri_code)) {
                $guest->santri_code = Guest::nextSantriCode($event->id, $guest->category);
                $guest->saveQuietly();
            }
        }

        if ($compact) {
            $guests = $guests->map(function (Guest $guest) {
                $attendance = $guest->attendance;

                return [
                    'id' => $guest->id,
                    'event_id' => $guest->event_id,
                    'name' => $guest->name,
                    'whatsapp_number' => $guest->whatsapp_number,
                    'category' => $guest->category,
                    'wali_angkatan' => $guest->wali_angkatan,
                    'santri_code' => $guest->santri_code,
                    'unique_token' => $guest->unique_token,
                    'created_at' => $guest->created_at,
                    'updated_at' => $guest->updated_at,
                    'attendance' => $attendance ? [
                        'id' => $attendance->id,
                        'guest_id' => $attendance->guest_id,
                        'event_id' => $attendance->event_id,
                        'status' => $attendance->status,
                        'checked_in_at' => $attendance->checked_in_at,
                        'checked_in_by' => $attendance->checked_in_by,
                    ] : null,
                ];
            })->values();
        }

        return response()->json([
            'success' => true,
            'data' => $guests,
            'event' => $event,
            'stats' => $this->stats($event),
        ]);
    }

    public function store(Request $request)
    {
        $event = $this->defaultEvent();
        $data = $this->validatedGuestData($request);
        $result = $this->createGuestRecord($event, $data);

        return response()->json([
            'success' => true,
            'message' => $result['created']
                ? 'Tamu santri berhasil ditambahkan dan link undangan dibuat.'
                : 'Tamu dengan permintaan ini sudah tersimpan.',
            'data' => $result['guest']->load(['invitation', 'attendance']),
        ], $result['created'] ? 201 : 200);
    }

    public function update(Request $request, Guest $guest)
    {
        $event = $this->defaultEvent();
        $this->ensureGuestBelongsToEvent($guest, $event);

        $data = $this->validatedGuestData($request);
        $typeChanged = strcasecmp((string) $guest->category, $data['tipe']) !== 0;

        $guest->fill([
            'name' => $data['name'],
            'whatsapp_number' => $this->invitations->normalizePhone($data['whatsapp_number']),
            'category' => $data['tipe'],
            'wali_angkatan' => $data['wali_angkatan'] ?? null,
        ]);

        if ($typeChanged || empty($guest->santri_code)) {
            $guest->santri_code = Guest::nextSantriCode($event->id, $data['tipe']);
        }

        $guest->save();
        $this->invitations->generateForGuest($guest);

        return response()->json([
            'success' => true,
            'message' => 'Data tamu santri berhasil diperbarui.',
            'data' => $guest->fresh()->load(['invitation', 'attendance']),
        ]);
    }

    public function destroy(Guest $guest)
    {
        $event = $this->defaultEvent();
        $this->ensureGuestBelongsToEvent($guest, $event);

        $guest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tamu santri berhasil dihapus.',
        ]);
    }

    public function toggleAttendance(Request $request, Guest $guest)
    {
        $event = $this->defaultEvent();
        $this->ensureGuestBelongsToEvent($guest, $event);

        try {
            $attendance = DB::transaction(function () use ($request, $guest, $event) {
                $existing = Attendance::where('guest_id', $guest->id)
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $existing->delete();

                    return null;
                }

                return Attendance::create([
                    'guest_id' => $guest->id,
                    'event_id' => $event->id,
                    'status' => 'present',
                    'checked_in_at' => now(),
                    'checked_in_by' => $request->user()->id,
                ]);
            });
        } catch (QueryException $e) {
            if (!$this->isUniqueViolation($e)) {
                throw $e;
            }

            $attendance = Attendance::where('guest_id', $guest->id)
                ->where('event_id', $event->id)
                ->first();
        }

        return response()->json([
            'success' => true,
            'message' => $attendance
                ? 'Tamu ditandai sudah hadir.'
                : 'Status kehadiran tamu dikembalikan menjadi belum hadir.',
            'data' => [
                'status' => $attendance ? 'Hadir' : 'Belum',
                'attendance' => $attendance?->load('officer:id,name'),
                'stats' => $this->stats($event),
            ],
        ]);
    }

    public function validateScan(Request $request)
    {
        $event = $this->defaultEvent();
        $raw = (string) ($request->input('code') ?? $request->input('token') ?? '');

        if (trim($raw) === '') {
            return response()->json([
                'success' => false,
                'valid' => false,
                'code' => 'invalid_token',
                'message' => 'Kode atau QR Code tamu tidak boleh kosong.',
            ], 422);
        }

        $guest = $this->findGuest($event, $raw);

        if (!$guest) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'code' => 'invalid_token',
                'message' => 'Kode tidak ditemukan. Periksa kembali kode atau QR Code tamu.',
            ], 404);
        }

        $guest->load(['event.template', 'invitation', 'attendance.officer:id,name']);

        if ((int) $guest->event_id !== (int) $event->id) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'code' => 'wrong_event',
                'message' => 'Undangan ini bukan untuk acara santri.',
                'data' => [
                    'selected_event' => $event->name,
                    'invitation_event' => $guest->event?->name,
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'checked_in' => $guest->attendance !== null,
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

    public function checkIn(Request $request)
    {
        $raw = (string) ($request->input('code') ?? $request->input('token') ?? '');

        if (trim($raw) === '') {
            return response()->json([
                'success' => false,
                'checked_in' => false,
                'code' => 'invalid_token',
                'message' => 'Kode atau QR Code tamu tidak boleh kosong.',
            ], 422);
        }

        $result = $this->performCheckIn($request, $raw);

        return $this->checkInResponse($result);
    }

    private function validatedGuestData(Request $request): array
    {
        return $this->validateGuestPayload($request->all());
    }

    private function validateGuestPayload(array $payload): array
    {
        if (isset($payload['whatsapp_number']) && is_string($payload['whatsapp_number'])) {
            $payload['whatsapp_number'] = preg_replace('/[\s\-().]+/', '', $payload['whatsapp_number']);
        }

        $data = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:Santri,Alumni'],
            'wali_angkatan' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['required', 'string', 'max:20', 'regex:/^(\+?62|0)8[1-9][0-9]{7,11}$/'],
        ], [
            'name.required' => 'Nama tamu wajib diisi.',
            'tipe.required' => 'Pilih tipe tamu Santri atau Alumni.',
            'tipe.in' => 'Tipe tamu harus Santri atau Alumni.',
            'whatsapp_number.required' => 'Nomor HP/WhatsApp wajib diisi.',
            'whatsapp_number.regex' => 'Format nomor HP tidak valid. Contoh: 081234567890.',
        ])->validate();

        $data['name'] = trim($data['name']);
        $data['wali_angkatan'] = isset($data['wali_angkatan'])
            ? trim((string) $data['wali_angkatan']) ?: null
            : null;

        return $data;
    }

    private function overviewData(): array
    {
        $event = $this->defaultEvent();
        $event->loadCount(['guests', 'attendance as attended_count']);

        $recent = Attendance::query()
            ->where('event_id', $event->id)
            ->with([
                'guest:id,event_id,name,category,wali_angkatan,santri_code,whatsapp_number',
                'officer:id,name',
            ])
            ->latest('checked_in_at')
            ->limit(8)
            ->get();

        return [
            'event' => $event,
            'stats' => $this->stats($event),
            'recent' => $recent,
        ];
    }

    private function createGuestRecord(Event $event, array $data): array
    {
        $guest = DB::transaction(function () use ($event, $data) {
            $guest = Guest::create([
                'event_id' => $event->id,
                'name' => $data['name'],
                'whatsapp_number' => $this->invitations->normalizePhone($data['whatsapp_number']),
                'category' => $data['tipe'],
                'wali_angkatan' => $data['wali_angkatan'] ?? null,
                'santri_code' => Guest::nextSantriCode($event->id, $data['tipe']),
            ]);

            $this->invitations->generateForGuest($guest);

            return $guest;
        });

        return ['guest' => $guest, 'created' => true];
    }

    private function performCheckIn(Request $request, string $raw): array
    {
        $event = $this->defaultEvent();

        try {
            $result = DB::transaction(function () use ($request, $event, $raw) {
                $guest = $this->findGuest($event, $raw);

                if (!$guest) {
                    return ['code' => 'invalid_token'];
                }

                $guest = Guest::whereKey($guest->id)->lockForUpdate()->first();

                if (!$guest) {
                    return ['code' => 'invalid_token'];
                }

                if ((int) $guest->event_id !== (int) $event->id) {
                    return ['code' => 'wrong_event', 'guest' => $guest];
                }

                $existing = Attendance::where('guest_id', $guest->id)
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return [
                        'code' => 'already_checked_in',
                        'guest' => $guest,
                        'attendance' => $existing,
                    ];
                }

                $attendance = Attendance::create([
                    'guest_id' => $guest->id,
                    'event_id' => $event->id,
                    'status' => 'present',
                    'checked_in_at' => now(),
                    'checked_in_by' => $request->user()->id,
                ]);

                return [
                    'code' => 'checked_in',
                    'guest' => $guest,
                    'attendance' => $attendance,
                ];
            });
        } catch (QueryException $e) {
            if (!$this->isUniqueViolation($e)) {
                throw $e;
            }

            $guest = $this->findGuest($event, $raw);
            $attendance = $guest
                ? Attendance::where('guest_id', $guest->id)->where('event_id', $event->id)->first()
                : null;

            return [
                'code' => 'already_checked_in',
                'guest' => $guest,
                'attendance' => $attendance,
            ];
        }

        $result['event'] = $event;

        return $result;
    }

    private function checkInResponse(array $result)
    {
        $event = $result['event'] ?? $this->defaultEvent();

        if ($result['code'] === 'invalid_token') {
            return response()->json([
                'success' => false,
                'checked_in' => false,
                'code' => 'invalid_token',
                'message' => 'Kode tidak ditemukan. Periksa kembali kode atau QR Code tamu.',
            ], 404);
        }

        if (empty($result['guest'])) {
            return response()->json([
                'success' => false,
                'checked_in' => false,
                'code' => 'invalid_token',
                'message' => 'Data tamu tidak ditemukan.',
            ], 404);
        }

        if ($result['code'] === 'wrong_event') {
            return response()->json([
                'success' => false,
                'checked_in' => false,
                'code' => 'wrong_event',
                'message' => 'Undangan ini bukan untuk acara santri.',
                'data' => [
                    'selected_event' => $event->name,
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
                'guest' => $result['guest']->load(['event', 'invitation', 'attendance.officer:id,name']),
                'event' => $event,
                'attendance' => $result['attendance']->load('officer:id,name'),
                'stats' => $this->stats($event),
            ],
        ]);
    }

    private function defaultEvent(): Event
    {
        $event = Event::where('event_type', 'santri')->orderBy('id')->first();

        if ($event) {
            return $event;
        }

        $template = Template::where('slug', 'wisuda-elegan')->first()
            ?? Template::where('is_active', true)->orderBy('id')->first();

        return Event::create([
            'name' => 'Wisuda Santri 2026',
            'event_type' => 'santri',
            'organizer' => 'Majelis Khidmah Darul Ulum',
            'event_date' => now()->addDays(30)->toDateString(),
            'event_time' => '09:00',
            'location' => 'Kantor Pusat Darul Ulum',
            'address' => 'Gedung Serbaguna Darul Ulum',
            'description' => 'Menyemai Ilmu, Menuai Cahaya. Mengharap kehadiran dan doa restu Bapak/Ibu/Saudara/i pada momentum wisuda santri.',
            'template_id' => $template?->id,
            'created_by' => auth()->id(),
        ]);
    }

    private function stats(Event $event): array
    {
        $total = Guest::where('event_id', $event->id)->count();
        $santri = Guest::where('event_id', $event->id)->where('category', 'Santri')->count();
        $alumni = Guest::where('event_id', $event->id)->where('category', 'Alumni')->count();
        $present = Attendance::where('event_id', $event->id)->count();
        $absent = max(0, $total - $present);

        return [
            'total' => $total,
            'santri' => $santri,
            'alumni' => $alumni,
            'present' => $present,
            'absent' => $absent,
            'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 1) : 0.0,
        ];
    }

    private function ensureGuestBelongsToEvent(Guest $guest, Event $event): void
    {
        abort_if((int) $guest->event_id !== (int) $event->id, 404, 'Tamu santri tidak ditemukan.');
    }

    private function findGuest(Event $event, string $raw): ?Guest
    {
        $value = $this->extractLookupValue($raw);

        $guest = Guest::where('unique_token', $value)->first();

        if (!$guest) {
            $guest = Guest::where('event_id', $event->id)
                ->where('santri_code', strtoupper($value))
                ->first();
        }

        return $guest;
    }

    private function extractLookupValue(string $raw): string
    {
        $raw = trim($raw);

        if (preg_match('~/undangan/([^/?#\s]+)~i', $raw, $matches)) {
            return urldecode($matches[1]);
        }

        if (preg_match('/[?&]kode=([^&#\s]+)/i', $raw, $matches)) {
            return urldecode($matches[1]);
        }

        return $raw;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23505'], true)
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
