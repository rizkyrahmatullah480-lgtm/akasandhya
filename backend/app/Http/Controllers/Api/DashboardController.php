<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;

class DashboardController extends Controller
{
    public function index()
    {
        $totalEvents = Event::count();
        $totalGuests = Guest::count();
        $sent = Invitation::where('delivery_status', Invitation::STATUS_SENT)->count();
        $queued = Invitation::where('delivery_status', Invitation::STATUS_QUEUED)->count();
        $failed = Invitation::where('delivery_status', Invitation::STATUS_FAILED)->count();
        $notSent = Invitation::where('delivery_status', Invitation::STATUS_NOT_SENT)->count()
            + max(0, $totalGuests - Invitation::count());
        $attended = Attendance::count();

        $events = Event::query()
            ->withCount(['guests', 'attendance as attended_count'])
            ->orderByDesc('event_date')
            ->limit(8)
            ->get()
            ->map(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'event_type' => $event->event_type,
                'event_date' => $event->event_date,
                'guests_count' => $event->guests_count,
                'attended_count' => $event->attended_count,
                'attendance_percentage' => $event->guests_count > 0
                    ? round(($event->attended_count / $event->guests_count) * 100, 1)
                    : 0,
            ])
            ->reverse()
            ->values();

        $recentCheckins = Attendance::with(['guest:id,name,event_id', 'event:id,name', 'officer:id,name'])
            ->latest('checked_in_at')
            ->limit(6)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_events' => $totalEvents,
                    'total_guests' => $totalGuests,
                    'invitations_sent' => $sent,
                    'invitations_not_sent' => $notSent,
                    'invitations_queued' => $queued,
                    'invitations_failed' => $failed,
                    'attended' => $attended,
                    'not_attended' => max(0, $totalGuests - $attended),
                    'attendance_percentage' => $totalGuests > 0
                        ? round(($attended / $totalGuests) * 100, 1)
                        : 0,
                ],
                'delivery' => [
                    'not_sent' => $notSent,
                    'queued' => $queued,
                    'sent' => $sent,
                    'failed' => $failed,
                ],
                'events_chart' => $events,
                'recent_checkins' => $recentCheckins,
            ],
        ]);
    }
}
