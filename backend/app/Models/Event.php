<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'event_type',
        'organizer',
        'event_date',
        'event_time',
        'location',
        'address',
        'description',
        'banner',
        'template_id',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date:Y-m-d',
    ];

    protected $appends = ['banner_url', 'maps_url'];

    public function guests()
    {
        return $this->hasMany(Guest::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getBannerUrlAttribute(): ?string
    {
        if (!$this->banner) {
            return null;
        }

        return str_starts_with($this->banner, 'http')
            ? $this->banner
            : asset('storage/' . ltrim($this->banner, '/'));
    }

    public function getMapsUrlAttribute(): ?string
    {
        if ($this->address) {
            return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($this->address);
        }

        if ($this->location) {
            return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($this->location);
        }

        return null;
    }

}
