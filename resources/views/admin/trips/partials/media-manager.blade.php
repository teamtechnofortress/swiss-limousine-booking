@php
    $cover = $trip->getFirstMedia('cover');
    $gallery = $trip->getMedia('gallery')->sortBy('order_column')->values();
@endphp

<details id="trip-media-manager" data-trip-step="content" data-trip-media data-order-url="{{ route('admin.trips.gallery.order', $trip) }}" data-delete-url-template="{{ route('admin.trips.gallery.destroy', [$trip, '__MEDIA_ID__']) }}" class="admin-card overflow-hidden">
    <summary class="cursor-pointer border-b border-slate-100 px-6 py-5 font-bold text-slate-900">Media &amp; gallery</summary>
    <div class="space-y-7 p-6">
        <section class="rounded-2xl border border-slate-200 p-4 sm:p-5">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center">
                <div class="flex aspect-[16/9] w-full max-w-sm items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-center text-sm font-medium text-slate-400">
                    <img data-trip-cover-preview class="size-full object-cover {{ $cover ? '' : 'hidden' }}" src="{{ $cover?->getUrl() }}" alt="Current trip cover image">
                    <span data-trip-cover-empty class="{{ $cover ? 'hidden' : '' }}">No cover image uploaded</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Trip cover</p>
                    <h3 class="mt-1 font-bold text-slate-900">Cover image</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500">This image appears on the trip listing and is required before publishing. JPG, PNG or WebP up to 10 MB.</p>
                    <form data-trip-cover-form method="POST" enctype="multipart/form-data" action="{{ route('admin.trips.cover', $trip) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">@csrf
                        <label class="sr-only" for="trip-cover">Choose cover image</label>
                        <input id="trip-cover" data-trip-cover-input class="admin-input !mt-0 min-w-0 flex-1" type="file" required accept="image/jpeg,image/png,image/webp" name="cover">
                        <button data-trip-cover-button class="admin-button whitespace-nowrap">Upload cover</button>
                    </form>
                    <p data-trip-cover-status class="mt-3 text-sm font-semibold {{ $cover ? 'text-emerald-700' : 'text-slate-500' }}" aria-live="polite">{{ $cover ? 'Cover image uploaded.' : 'Choose an image to upload.' }}</p>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-100 pt-7">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div><p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Trip gallery</p><h3 class="mt-1 font-bold text-slate-900">Gallery images</h3><p class="mt-1 text-sm text-slate-500">Select up to 20 images at once, then drag images or use the arrow buttons to set their display order.</p></div>
                <span data-trip-gallery-count class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">{{ $gallery->count() }} {{ str('image')->plural($gallery->count()) }}</span>
            </div>
            <form data-trip-gallery-form method="POST" enctype="multipart/form-data" action="{{ route('admin.trips.gallery', $trip) }}" class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4">@csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center"><label class="sr-only" for="trip-gallery">Choose gallery images</label><input id="trip-gallery" data-trip-gallery-input class="admin-input !mt-0 min-w-0 flex-1 bg-white" type="file" required multiple accept="image/jpeg,image/png,image/webp" name="images[]"><button data-trip-gallery-button class="admin-button whitespace-nowrap">Upload images</button></div>
                <p data-trip-gallery-selection class="mt-3 text-sm text-slate-500">No images selected.</p>
            </form>
            <p data-trip-gallery-status class="mt-4 text-sm font-semibold text-slate-500" aria-live="polite"></p>
            <div data-trip-gallery-grid class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                @forelse($gallery as $media)
                    <article data-trip-gallery-item data-media-id="{{ $media->id }}" draggable="true" class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <button type="button" data-trip-drag-handle class="absolute left-2 top-2 cursor-grab rounded-lg bg-white/95 px-2 py-1 text-xs font-bold text-slate-600 shadow active:cursor-grabbing" aria-label="Drag to reorder image">↕ Move</button>
                        <img class="aspect-[4/3] w-full object-cover" src="{{ $media->getUrl() }}" alt="Gallery image {{ $loop->iteration }}">
                        <div class="flex items-center justify-between gap-2 p-2"><span data-trip-gallery-position class="text-xs font-bold text-slate-500">Image {{ $loop->iteration }}</span><div class="flex items-center gap-1"><button type="button" data-trip-move="-1" class="rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100" aria-label="Move image earlier">←</button><button type="button" data-trip-move="1" class="rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100" aria-label="Move image later">→</button><form data-trip-gallery-remove method="POST" action="{{ route('admin.trips.gallery.destroy', [$trip, $media->id]) }}">@csrf @method('DELETE')<button class="rounded-lg px-2 py-1 text-xs font-bold text-rose-700 hover:bg-rose-50">Remove</button></form></div></div>
                    </article>
                @empty
                    <div data-trip-gallery-empty class="col-span-full rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No gallery images yet. Choose one or more images above to start your gallery.</div>
                @endforelse
            </div>
        </section>
    </div>
</details>
