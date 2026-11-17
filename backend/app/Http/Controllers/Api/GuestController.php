<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportGuestRequest;
use App\Http\Requests\StoreGuestRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GuestController extends Controller
{
    public function __construct(private InvitationService $invitations)
    {
    }

    public function index(Request $request, Event $event)
    {
        $query = Guest::query()
            ->where('event_id', $event->id)
            ->with(['invitation', 'attendance']);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->query('delivery_status')) {
            if ($status === 'not_sent') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('invitation')
                        ->orWhereHas('invitation', fn ($i) => $i->where('delivery_status', 'not_sent'));
                });
            } else {
                $query->whereHas('invitation', fn ($i) => $i->where('delivery_status', $status));
            }
        }

        if ($attendance = $request->query('attendance_status')) {
            if ($attendance === 'present') {
                $query->whereHas('attendance');
            } elseif ($attendance === 'absent') {
                $query->whereDoesntHave('attendance');
            }
        }

        $perPage = min((int) $request->query('per_page', 10), 100);
        $paginator = $query->orderBy('name')->paginate($perPage);

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

    public function store(StoreGuestRequest $request, Event $event)
    {
        $guest = DB::transaction(function () use ($request, $event) {
            $guest = $event->guests()->create([
                'name' => trim($request->validated('name')),
                'whatsapp_number' => $this->invitations->normalizePhone($request->validated('whatsapp_number')),
                'category' => $request->validated('category'),
            ]);

            $this->invitations->generateForGuest($guest);

            return $guest;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tamu berhasil ditambahkan dan link undangan dibuat.',
            'data' => $guest->load(['invitation', 'attendance']),
        ], 201);
    }

    public function update(UpdateGuestRequest $request, Guest $guest)
    {
        $guest->update([
            'name' => trim($request->validated('name')),
            'whatsapp_number' => $this->invitations->normalizePhone($request->validated('whatsapp_number')),
            'category' => $request->validated('category'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data tamu berhasil diperbarui.',
            'data' => $guest->fresh()->load(['invitation', 'attendance']),
        ]);
    }

    public function destroy(Guest $guest)
    {
        $guest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tamu berhasil dihapus.',
        ]);
    }

    public function import(ImportGuestRequest $request, Event $event)
    {
        $rows = [];

        if ($request->hasFile('file')) {
            $rows = $this->parseCsv($request->file('file')->getRealPath());
        } else {
            foreach ($request->validated('guests') as $row) {
                $rows[] = [
                    'name' => $row['name'] ?? null,
                    'whatsapp_number' => $row['whatsapp_number'] ?? null,
                    'category' => $row['category'] ?? null,
                ];
            }
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;

            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'whatsapp_number' => ['required', 'string', 'max:20', 'regex:/^(\+?62|0)8[1-9][0-9]{7,11}$/'],
                'category' => ['nullable', 'string', 'max:100'],
            ]);

            if ($validator->fails()) {
                $skipped++;
                if (count($errors) < 20) {
                    $errors[] = "Baris {$line}: " . $validator->errors()->first();
                }
                continue;
            }

            $data = $validator->validated();
            $phone = $this->invitations->normalizePhone($data['whatsapp_number']);
            $name = trim($data['name']);

            $exists = $event->guests()
                ->where('name', $name)
                ->where('whatsapp_number', $phone)
                ->exists();

            if ($exists) {
                $skipped++;
                if (count($errors) < 20) {
                    $errors[] = "Baris {$line}: tamu {$name} sudah terdaftar pada acara ini.";
                }
                continue;
            }

            DB::transaction(function () use ($event, $name, $phone, $data) {
                $guest = $event->guests()->create([
                    'name' => $name,
                    'whatsapp_number' => $phone,
                    'category' => $data['category'] ?? null,
                ]);

                $this->invitations->generateForGuest($guest);
            });

            $imported++;
        }

        return response()->json([
            'success' => true,
            'message' => "Import selesai: {$imported} tamu ditambahkan, {$skipped} baris dilewati.",
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
        ], $imported > 0 ? 200 : 422);
    }

    /**
     * Accept CSV with headers name/whatsapp_number/category (Indonesian
     * aliases are supported) or a headerless file with three columns.
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if (!$handle) {
            return [];
        }

        $rows = [];
        $headers = null;
        $line = 0;

        while (($columns = fgetcsv($handle, 0, ',')) !== false) {
            $line++;

            if ($columns === [null] || count(array_filter($columns, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            if ($line === 1) {
                $first = strtolower(trim((string) $columns[0]));
                $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);

                $known = ['name', 'nama', 'guest_name', 'whatsapp_number', 'whatsapp', 'phone', 'nomor_whatsapp', 'nomor', 'category', 'kategori', 'keterangan'];

                if (in_array($first, $known, true)) {
                    $headers = array_map(function ($h) {
                        $h = strtolower(trim((string) $h));
                        return preg_replace('/^\xEF\xBB\xBF/', '', $h);
                    }, $columns);
                    continue;
                }
            }

            if ($headers) {
                $row = [];
                foreach ($headers as $i => $header) {
                    $row[$header] = $columns[$i] ?? null;
                }
                $rows[] = [
                    'name' => $row['name'] ?? $row['nama'] ?? $row['guest_name'] ?? null,
                    'whatsapp_number' => $row['whatsapp_number'] ?? $row['whatsapp'] ?? $row['phone'] ?? $row['nomor_whatsapp'] ?? $row['nomor'] ?? null,
                    'category' => $row['category'] ?? $row['kategori'] ?? $row['keterangan'] ?? null,
                ];
            } else {
                $rows[] = [
                    'name' => $columns[0] ?? null,
                    'whatsapp_number' => $columns[1] ?? null,
                    'category' => $columns[2] ?? null,
                ];
            }
        }

        fclose($handle);

        return $rows;
    }
}
