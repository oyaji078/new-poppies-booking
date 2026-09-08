<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves uploaded room and gallery photos from the `public` disk.
 *
 * Laravel's normal answer is the public/storage symlink, but plenty of shared
 * hosts either forbid symlinks or lose them on redeploy, and the failure mode is
 * silent: uploads succeed, rows exist, and every <img> on the site 404s. Staff
 * read that as "the gallery is broken".
 *
 * So the URL is backed by a real route as well. When the symlink exists the web
 * server answers first and this never runs; when it does not, the photos still
 * appear. The route is declared explicitly rather than relying on the disk's
 * `serve` option because that one is skipped entirely once routes are cached,
 * which production does.
 */
class UploadedImageController extends Controller
{
    private const DISK = 'public';

    /** Photos are stored under UUID names, so a hit can be cached hard. */
    private const CACHE_SECONDS = 31536000;

    public function __invoke(Request $request, string $path): Response
    {
        $disk = Storage::disk(self::DISK);

        // The path comes straight off the URL. Anything trying to climb out of
        // the disk root is a probe, not a photo.
        if (! $this->isSafe($path) || ! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS.', immutable',
        ]);
    }

    private function isSafe(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }

        // Backslashes are not path separators in a URL, but they are on the
        // host filesystem, so refuse them outright rather than normalising.
        if (str_contains($path, '\\')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..' || $segment === '') {
                return false;
            }
        }

        return true;
    }
}
