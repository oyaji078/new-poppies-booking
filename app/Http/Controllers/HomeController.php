<?php

namespace App\Http\Controllers;

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

        return view('public.home', compact('featuredRoomTypes'));
    }
}
