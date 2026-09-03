<?php

namespace App\Models;

use App\Services\Media\ImageStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A photo in the public homepage gallery. Unlike RoomImage it belongs to no
 * room type — it exists purely to show what the hotel looks like.
 */
class GalleryImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'path', 'original_name', 'title', 'alt', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * What the public site is allowed to show.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getUrlAttribute(): string
    {
        return app(ImageStorage::class)->url($this->path);
    }

    /**
     * Alt text always falls back to something meaningful — an empty alt on a
     * content image is an accessibility hole.
     */
    public function getAltTextAttribute(): string
    {
        return $this->alt ?: ($this->title ?: 'Galeri New Poppies Senggigi');
    }
}
