<?php

namespace App\Services\Media;

use App\Models\GalleryImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * The homepage gallery (§KF-01). Photos are ordered explicitly rather than by
 * upload date, because staff arrange them to tell a story about the property.
 */
class GalleryService
{
    private const DIR = 'gallery-images';

    public function __construct(private readonly ImageStorage $storage) {}

    public function store(UploadedFile $file, ?string $title = null, ?string $alt = null): GalleryImage
    {
        $path = $this->storage->put($file, self::DIR);

        return GalleryImage::create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'title' => $title ?: null,
            'alt' => $alt ?: null,
            'is_published' => true,
            'sort_order' => (int) GalleryImage::max('sort_order') + 1,
        ]);
    }

    public function update(GalleryImage $image, ?string $title, ?string $alt): GalleryImage
    {
        $image->update(['title' => $title ?: null, 'alt' => $alt ?: null]);

        return $image;
    }

    public function togglePublish(GalleryImage $image): GalleryImage
    {
        $image->update(['is_published' => ! $image->is_published]);

        return $image;
    }

    public function delete(GalleryImage $image): void
    {
        // Remove the row first: a stored file with no row is invisible clutter,
        // but a row pointing at a missing file breaks the page for every visitor.
        $path = $image->path;
        $image->delete();

        $this->storage->delete($path);
    }

    /**
     * Move a photo one position earlier or later. Positions are rewritten as a
     * dense 1..n sequence afterwards so repeated moves can never drift or tie.
     */
    public function move(GalleryImage $image, string $direction): void
    {
        $ids = GalleryImage::query()->ordered()->pluck('id')->all();
        $index = array_search($image->id, $ids, true);

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= count($ids)) {
            return; // already at the edge
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $this->reorder($ids);
    }

    /**
     * @param  array<int, int>  $orderedIds  image ids in the desired order
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach (array_values($orderedIds) as $position => $id) {
                GalleryImage::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
