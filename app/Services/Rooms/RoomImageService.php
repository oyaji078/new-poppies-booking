<?php

namespace App\Services\Rooms;

use App\Models\RoomImage;
use App\Models\RoomType;
use App\Services\Media\ImageStorage;
use Illuminate\Http\UploadedFile;

/**
 * Handles room image storage safely: validated MIME/size (validation happens in
 * the Form Request / Livewire rules), random file names to defeat path/exec
 * tricks, primary-image bookkeeping, and orphan cleanup on delete.
 *
 * Where the bytes actually land is ImageStorage's concern.
 */
class RoomImageService
{
    private const DIR = 'room-images';

    public function __construct(private readonly ImageStorage $storage) {}

    public function store(RoomType $roomType, UploadedFile $file, ?string $alt = null): RoomImage
    {
        $path = $this->storage->put($file, self::DIR);

        $isFirst = ! $roomType->images()->exists();
        $nextSort = (int) $roomType->images()->max('sort_order') + 1;

        return $roomType->images()->create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'alt' => $alt ?: $roomType->name,
            'is_primary' => $isFirst, // first image uploaded becomes primary
            'sort_order' => $nextSort,
        ]);
    }

    public function makePrimary(RoomImage $image): void
    {
        $image->roomType->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
    }

    public function delete(RoomImage $image): void
    {
        $wasPrimary = $image->is_primary;
        $roomType = $image->roomType;

        $this->storage->delete($image->path);
        $image->delete();

        // Promote another image to primary so a type is never left without one.
        if ($wasPrimary && $roomType) {
            $next = $roomType->images()->orderBy('sort_order')->first();
            $next?->update(['is_primary' => true]);
        }
    }

    /**
     * @param  array<int, int>  $orderedIds  image ids in the desired order
     */
    public function reorder(RoomType $roomType, array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $index => $id) {
            $roomType->images()->whereKey($id)->update(['sort_order' => $index + 1]);
        }
    }
}
