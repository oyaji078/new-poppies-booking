@props(['roomType'])

@php
    $image = $roomType->primaryImage->first() ?? $roomType->images->first();
@endphp

<div class="card group flex flex-col overflow-hidden transition hover:shadow-md">
    <a href="{{ route('rooms.show', $roomType) }}" class="block aspect-[4/3] overflow-hidden bg-slate-100">
        @if ($image)
            <img src="{{ $image->url }}" alt="{{ $image->alt }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy">
        @else
            <div class="grid h-full w-full place-items-center bg-gradient-to-br from-brand-100 to-brand-50 text-brand-300">
                <svg class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 6H20.25a1.5 1.5 0 011.5 1.5v10.5a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5V7.5A1.5 1.5 0 013.75 6z"/></svg>
            </div>
        @endif
    </a>
    <div class="flex flex-1 flex-col p-5">
        <div class="flex items-start justify-between gap-3">
            <h3 class="font-display text-lg font-semibold text-slate-900">
                <a href="{{ route('rooms.show', $roomType) }}" class="hover:text-brand-700">{{ $roomType->name }}</a>
            </h3>
            <span class="badge bg-brand-50 text-brand-700">{{ $roomType->max_guests }} tamu</span>
        </div>

        @if ($roomType->short_description)
            <p class="mt-1.5 line-clamp-2 text-sm text-slate-600">{{ $roomType->short_description }}</p>
        @endif

        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach ($roomType->amenities->take(3) as $amenity)
                <span class="badge bg-slate-100 text-slate-600">{{ $amenity->name }}</span>
            @endforeach
            @if ($roomType->bed_type)
                <span class="badge bg-slate-100 text-slate-600">{{ $roomType->bed_type }}</span>
            @endif
        </div>

        <div class="mt-4 flex items-end justify-between border-t border-slate-100 pt-4">
            <div>
                <p class="text-xs text-slate-500">Mulai dari</p>
                <p class="font-display text-xl font-semibold text-slate-900">{{ rupiah($roomType->base_price) }}
                    <span class="text-xs font-normal text-slate-500">/ malam</span>
                </p>
            </div>
            <a href="{{ route('rooms.show', $roomType) }}" class="btn-outline text-sm">Lihat Detail</a>
        </div>
    </div>
</div>
