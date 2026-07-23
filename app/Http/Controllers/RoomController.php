<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(): View
    {
        $roomTypes = RoomType::query()
            ->published()
            ->with(['primaryImage', 'amenities'])
            ->ordered()
            ->paginate(9);

        return view('public.rooms.index', compact('roomTypes'));
    }

    public function show(RoomType $roomType): View
    {
        abort_unless($roomType->is_published, 404);

        $roomType->load(['images', 'amenities', 'rooms']);

        return view('public.rooms.show', compact('roomType'));
    }
}
