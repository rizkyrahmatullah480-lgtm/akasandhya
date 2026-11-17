<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'preview_image',
        'configuration',
        'is_premium',
        'is_active',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_premium' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = ['thumbnail', 'config'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /** Backwards-compatible alias used by the frontend gallery. */
    public function getThumbnailAttribute(): ?string
    {
        return $this->preview_image;
    }

    /** Backwards-compatible alias used by the template renderer. */
    public function getConfigAttribute(): array
    {
        return $this->configuration ?? [];
    }
}
