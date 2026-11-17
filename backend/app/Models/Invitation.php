<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasFactory;

    public const STATUS_NOT_SENT = 'not_sent';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'guest_id',
        'event_id',
        'invitation_url',
        'qr_code_path',
        'delivery_status',
        'sent_at',
        'delivery_error',
        'provider_message_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function attendance()
    {
        return $this->hasOne(Attendance::class, 'guest_id', 'guest_id');
    }
}
