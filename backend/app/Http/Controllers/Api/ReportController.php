<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Guest;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function attendance(Request $request, Event $event)
    {
        $guests = $event->guests()
            ->with(['invitation', 'attendance.officer:id,name'])
            ->orderBy('name')
            ->get();

        if ($date = $request->query('date')) {
            $guests = $guests->filter(function (Guest $guest) use ($date) {
                return $guest->attendance
                    && $guest->attendance->checked_in_at?->toDateString() === $date;
            })->values();
        }

        $present = $guests->filter(fn (Guest $g) => $g->attendance !== null)->values();
        $absent = $guests->filter(fn (Guest $g) => $g->attendance === null)->values();
        $total = $guests->count();

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event,
                'stats' => [
                    'total_guests' => $total,
                    'total_present' => $present->count(),
                    'total_absent' => $absent->count(),
                    'attendance_percentage' => $total > 0 ? round(($present->count() / $total) * 100, 1) : 0,
                ],
                'present' => $present,
                'absent' => $absent,
            ],
        ]);
    }

    public function export(Request $request, Event $event)
    {
        $event->load('guests.invitation', 'guests.attendance.officer');

        $filename = 'kehadiran-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($event->name)) . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($event) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens Indonesian characters correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'No',
                'Nama Tamu',
                'Nomor WhatsApp',
                'Kategori',
                'Status Kehadiran',
                'Waktu Check-in',
                'Petugas',
                'Status Pengiriman',
            ]);

            foreach ($event->guests->sortBy('name')->values() as $index => $guest) {
                fputcsv($handle, [
                    $index + 1,
                    $guest->name,
                    $guest->whatsapp_number,
                    $guest->category ?? '-',
                    $guest->attendance ? 'Hadir' : 'Belum Hadir',
                    $guest->attendance?->checked_in_at?->format('Y-m-d H:i:s') ?? '-',
                    $guest->attendance?->officer?->name ?? '-',
                    $guest->invitation?->delivery_status ?? 'not_sent',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function summary()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_attendance' => Attendance::count(),
                'today_attendance' => Attendance::whereDate('checked_in_at', today())->count(),
            ],
        ]);
    }
}
