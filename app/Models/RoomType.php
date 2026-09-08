<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'short_description', 'full_description',
        'adult_capacity', 'child_capacity', 'max_guests',
        'bed_type', 'room_size', 'base_price', 'policies',
        'default_inventory', 'is_published', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'adult_capacity' => 'integer',
            'child_capacity' => 'integer',
            'max_guests' => 'integer',
            'room_size' => 'integer',
            'base_price' => 'integer',
            'default_inventory' => 'integer',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(RoomTypeInventory::class);
    }

    /**
     * Booking lines that sold this type. Protected by a restricting foreign key,
     * so this is also what decides whether the type may still be deleted.
     */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    public function primaryImage(): HasMany
    {
        return $this->images()->where('is_primary', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Number of physical rooms that can actually be sold for this type
     * (active and not under maintenance). Used as a sane inventory default.
     */
    public function sellableRoomCount(): int
    {
        return $this->rooms()
            ->where('is_active', true)
            ->where('under_maintenance', false)
            ->count();
    }

    public static function generateSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
