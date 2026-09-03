<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\RoomType;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $featuredRoomTypes = RoomType::query()
            ->published()
            ->with(['primaryImage', 'amenities'])
            ->ordered()
            ->limit(6)
            ->get();

        // Curated by staff at Admin → Galeri. An empty gallery is not an error:
        // the section falls back to placeholders until photos are uploaded.
        $galleryImages = GalleryImage::query()
            ->published()
            ->ordered()
            ->limit(12)
            ->get();

        return view('public.home', compact('featuredRoomTypes', 'galleryImages'));
    }
}
