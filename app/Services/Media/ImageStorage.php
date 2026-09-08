<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Where uploaded images physically live.
 *
 * Two backends, chosen by configuration alone: the local `public` disk during
 * development, and a Supabase Storage bucket on serverless hosts (Vercel), whose
 * filesystem is wiped on every deploy. Callers never learn which one is active.
 *
 * File names are always random UUIDs — an uploader must never get to choose a
 * path or an executable-looking name.
 */
class ImageStorage
{
    private const DISK = 'public';

    /**
     * Store the upload under $directory and return the stored path.
     */
    public function put(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extension;

        if (! $this->usesSupabase()) {
            // Gunakan public disk - filenya langsung di public/uploads/...
            return Storage::disk(self::DISK)->putFileAs($directory, $file, basename($path));
        }

        $response = Http::withToken($this->serviceKey())
            ->withHeaders([
                'apikey' => $this->serviceKey(),
                'x-upsert' => 'false',
            ])
            ->withBody($file->getContent(), $file->getMimeType() ?: 'application/octet-stream')
            ->post($this->objectUrl($path));

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gagal menyimpan gambar ke penyimpanan persisten (HTTP '.$response->status().').'
            );
        }

        return $path;
    }

    public function delete(string $path): void
    {
        if (! $this->usesSupabase()) {
            Storage::disk(self::DISK)->delete($path);

            return;
        }

        $response = Http::withToken($this->serviceKey())
            ->withHeaders(['apikey' => $this->serviceKey()])
            ->delete($this->objectUrl($path));

        // 404 means the object is already gone — the desired end state either way.
        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException(
                'Gagal menghapus gambar dari penyimpanan persisten (HTTP '.$response->status().').'
            );
        }
    }

    /**
     * Public URL for a stored path.
     */
    public function url(string $path): string
    {
        if (! $this->usesSupabase()) {
            return Storage::disk(self::DISK)->url($path);
        }

        return $this->storageUrl().'/storage/v1/object/public/'
            .rawurlencode($this->bucket()).'/'.$this->encodePath($path);
    }

    private function usesSupabase(): bool
    {
        return $this->storageUrl() !== '' && $this->serviceKey() !== '';
    }

    private function storageUrl(): string
    {
        return (string) config('services.supabase_storage.url', '');
    }

    private function serviceKey(): string
    {
        return (string) config('services.supabase_storage.service_key', '');
    }

    private function bucket(): string
    {
        return (string) config('services.supabase_storage.bucket', 'room-images');
    }

    private function objectUrl(string $path): string
    {
        return $this->storageUrl().'/storage/v1/object/'
            .rawurlencode($this->bucket()).'/'.$this->encodePath($path);
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
