<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::query()
            ->with('template:id,name,preview_image,configuration')
            ->withCount(['guests', 'attendance as attended_count'])
            ->orderByDesc('event_date');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('organizer', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('event_type')) {
            $query->where('event_type', $type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(StoreEventRequest $request)
    {
        $event = Event::create(array_merge(
            $request->validated(),
            ['created_by' => $request->user()->id]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Acara berhasil dibuat.',
            'data' => $event->load('template'),
        ], 201);
    }

    public function show(Event $event)
    {
        $event->load([
            'template',
            'guests' => fn ($q) => $q->with(['invitation', 'attendance'])->orderBy('name'),
        ]);

        $event->loadCount(['guests', 'attendance as attended_count']);

        $sent = $event->invitations()->whereIn('delivery_status', ['sent', 'queued'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event,
                'stats' => [
                    'total_guests' => $event->guests->count(),
                    'attended' => $event->attended_count,
                    'not_attended' => $event->guests->count() - $event->attended_count,
                    'invitations_sent' => $sent,
                    'invitations_not_sent' => $event->guests->count() - $sent,
                    'attendance_percentage' => $event->guests->count() > 0
                        ? round(($event->attended_count / $event->guests->count()) * 100, 1)
                        : 0,
                ],
            ],
        ]);
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $event->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Acara berhasil diperbarui.',
            'data' => $event->fresh()->load('template'),
        ]);
    }

    public function destroy(Event $event)
    {
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Acara beserta seluruh tamu dan undangannya berhasil dihapus.',
        ]);
    }

    public function uploadBanner(Request $request, Event $event)
    {
        $request->validate([
            'banner' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'banner.required' => 'Pilih foto banner terlebih dahulu.',
            'banner.image' => 'Banner harus berupa gambar.',
            'banner.max' => 'Ukuran banner maksimal 4MB.',
        ]);

        if ($event->banner && !str_starts_with($event->banner, 'http')) {
            Storage::disk('public')->delete($event->banner);
        }

        $path = $request->file('banner')->store('events', 'public');
        $event->update(['banner' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Banner acara berhasil diunggah.',
            'data' => [
                'banner' => $path,
                'banner_url' => $event->banner_url,
            ],
        ]);
    }
}
