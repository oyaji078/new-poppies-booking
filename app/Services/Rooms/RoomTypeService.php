<?php

namespace App\Services\Rooms;

use App\Enums\AuditAction;
use App\Models\RoomType;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class RoomTypeService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $amenityIds
     */
    public function create(array $data, array $amenityIds = []): RoomType
    {
        return DB::transaction(function () use ($data, $amenityIds) {
            $data['slug'] = RoomType::generateSlug($data['name']);
            $roomType = RoomType::create($data);
            $roomType->amenities()->sync($amenityIds);

            $this->audit->log(AuditAction::ROOM_CHANGE->value, $roomType, null, $roomType->only([
                'name', 'base_price', 'is_published',
            ]));

            return $roomType;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $amenityIds
     */
    public function update(RoomType $roomType, array $data, array $amenityIds = []): RoomType
    {
        return DB::transaction(function () use ($roomType, $data, $amenityIds) {
            $original = $roomType->only(['name', 'base_price', 'is_published']);

            // Regenerate slug only when the name actually changed.
            if (isset($data['name']) && $data['name'] !== $roomType->name) {
                $data['slug'] = RoomType::generateSlug($data['name']);
            }

            $roomType->update($data);
            $roomType->amenities()->sync($amenityIds);

            $this->audit->log(
                AuditAction::ROOM_CHANGE->value,
                $roomType,
                $original,
                $roomType->only(['name', 'base_price', 'is_published']),
            );

            return $roomType;
        });
    }

    public function togglePublish(RoomType $roomType): RoomType
    {
        $roomType->update(['is_published' => ! $roomType->is_published]);

        $this->audit->log(AuditAction::ROOM_CHANGE->value, $roomType, null, [
            'is_published' => $roomType->is_published,
        ]);

        return $roomType;
    }
}
