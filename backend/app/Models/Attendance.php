<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'guest_id',
        'event_id',
        'status',
        'checked_in_at',
        'checked_in_by',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function officer()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }
}
