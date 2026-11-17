<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'whatsapp_number',
        'category',
        'wali_angkatan',
        'santri_code',
        'unique_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Guest $guest) {
            if (empty($guest->unique_token)) {
                $guest->unique_token = self::generateUniqueToken();
            }
        });
    }

    /**
     * Cryptographically random token. Never derived from the database id so
     * public invitation links cannot be enumerated.
     */
    public static function generateUniqueToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('unique_token', $token)->exists());

        return $token;
    }

    /**
     * Short human-readable code for the simple "Mode Santri" experience.
     * Examples: S001 for santri, A001 for alumni. The public invitation
     * token remains the long random token and is never replaced by this code.
     */
    public static function nextSantriCode(int $eventId, ?string $type = 'Santri'): string
    {
        $prefix = strtolower((string) $type) === 'alumni' ? 'A' : 'S';
        $number = 1;

        do {
            $code = $prefix . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $number++;
        } while (self::where('event_id', $eventId)->where('santri_code', $code)->exists());

        return $code;
    }

    /**
     * Recipient name used when sending WhatsApp messages.
     *
     * In Mode Santri the WhatsApp recipient is the parent/guardian, while the
     * public invitation keeps the santri's own name. Alumni are addressed by
     * their own name because their wali_angkatan field stores the batch year.
     */
    public function whatsappRecipientName(): string
    {
        $this->loadMissing('event');

        if (
            $this->event?->event_type === 'santri'
            && $this->category === 'Santri'
            && filled($this->wali_angkatan)
        ) {
            return (string) $this->wali_angkatan;
        }

        return $this->name;
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function invitation()
    {
        return $this->hasOne(Invitation::class);
    }

    public function attendance()
    {
        return $this->hasOne(Attendance::class);
    }
}
