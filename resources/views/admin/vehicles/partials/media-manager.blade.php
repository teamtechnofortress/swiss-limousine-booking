@php
    $images = $vehicle->getMedia('images')->sortBy('order_column')->values();
    $mediaPayload = $images->map(fn ($media) => [
        'id' => $media->id,
        'url' => $media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl(),
        'thumbnail_url' => $media->hasGeneratedConversion('thumbnail') ? $media->getUrl('thumbnail') : ($media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl()),
        'name' => $media->name,
        'order' => $media->order_column,
    ])->values();
@endphp

<section data-media-panel data-vehicle-media data-upload-url="{{ route('admin.vehicles.images.store', $vehicle) }}" data-order-url="{{ route('admin.vehicles.images.order', $vehicle) }}" data-primary-url-template="{{ route('admin.vehicles.images.primary', [$vehicle, '__MEDIA_ID__']) }}" data-delete-url-template="{{ route('admin.vehicles.images.destroy', [$vehicle, '__MEDIA_ID__']) }}" class="admin-card mt-5 hidden max-w-6xl overflow-hidden">
    <div class="flex flex-col justify-between gap-4 border-b border-slate-100 p-6 sm:flex-row sm:items-start">
        <div><p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Website media</p><h2 class="mt-1 text-lg font-bold text-slate-900">Vehicle cover &amp; gallery</h2><p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">The cover is the first image shown in the website catalogue. Gallery images stay connected to the same vehicle and can be reordered without creating duplicate files.</p></div>
        <span data-vehicle-image-count class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">{{ $images->count() }} {{ str('image')->plural($images->count()) }}</span>
    </div>

    <div class="space-y-8 p-6">
        <section class="rounded-2xl border border-slate-200 p-4 sm:p-5">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,360px)_1fr] lg:items-center">
                <div class="flex aspect-[3/2] w-full items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-center text-sm font-medium text-slate-400">
                    <img data-vehicle-cover-preview class="size-full object-cover {{ $images->isNotEmpty() ? '' : 'hidden' }}" src="{{ $images->first()?->hasGeneratedConversion('web') ? $images->first()->getUrl('web') : $images->first()?->getUrl() }}" alt="Current vehicle cover image">
                    <span data-vehicle-cover-empty class="{{ $images->isNotEmpty() ? 'hidden' : '' }}">No cover image uploaded</span>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Vehicle cover</p>
                    <h3 class="mt-1 font-bold text-slate-900">Catalogue image</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-500">Drop one image here or browse your files. It uploads immediately and becomes the first website image. JPG, PNG or WebP up to 10 MB.</p>
                    <div data-vehicle-cover-dropzone tabindex="0" role="button" aria-controls="vehicle-cover" class="mt-4 cursor-pointer rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-5 text-center transition hover:border-indigo-400 hover:bg-indigo-50/50 focus:outline-none focus:ring-3 focus:ring-indigo-200">
                        <input id="vehicle-cover" data-vehicle-cover-input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp">
                        <span class="block text-sm font-bold text-slate-700">Drop a cover image here</span>
                        <span class="mt-1 block text-sm text-slate-500">or <span class="font-semibold text-indigo-700">browse your files</span> · uploads immediately</span>
                    </div>
                    <p data-vehicle-cover-status class="mt-3 text-sm font-semibold {{ $images->isNotEmpty() ? 'text-emerald-700' : 'text-slate-500' }}" aria-live="polite">{{ $images->isNotEmpty() ? 'Cover image connected to this vehicle.' : 'Drop or choose an image to set the cover.' }}</p>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-100 pt-7">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div><p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Vehicle gallery</p><h3 class="mt-1 font-bold text-slate-900">Additional images</h3><p class="mt-1 text-sm text-slate-500">Drop up to 12 images at once or browse your files. Images upload automatically and stay attached to this vehicle.</p></div>
                <span class="text-xs font-semibold text-slate-500">Drag to reorder gallery images</span>
            </div>
            <div data-vehicle-gallery-dropzone tabindex="0" role="button" aria-controls="vehicle-gallery" class="mt-5 cursor-pointer rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-7 text-center transition hover:border-indigo-400 hover:bg-indigo-50/50 focus:outline-none focus:ring-3 focus:ring-indigo-200">
                <input id="vehicle-gallery" data-vehicle-gallery-input class="sr-only" type="file" multiple accept="image/jpeg,image/png,image/webp">
                <span class="block text-sm font-bold text-slate-700">Drop gallery images here</span>
                <span class="mt-1 block text-sm text-slate-500">or <span class="font-semibold text-indigo-700">browse your files</span> · JPG, PNG or WebP, up to 10 MB each</span>
            </div>
            <p data-vehicle-gallery-selection class="mt-3 text-sm text-slate-500">Images start uploading as soon as you add them.</p>
            <p data-vehicle-gallery-status class="mt-4 text-sm font-semibold text-slate-500" aria-live="polite"></p>
            <p data-vehicle-uploading-message tabindex="-1" class="hidden rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-800" aria-live="polite"></p>

            <div data-vehicle-gallery-grid class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                @forelse($images->slice(1) as $media)
                    <article data-vehicle-gallery-item data-media-id="{{ $media->id }}" draggable="true" class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <button type="button" data-vehicle-drag-handle class="absolute left-2 top-2 cursor-grab rounded-lg bg-white/95 px-2 py-1 text-xs font-bold text-slate-600 shadow active:cursor-grabbing" aria-label="Drag to reorder image">↕ Move</button>
                        <img class="aspect-[4/3] w-full object-cover" src="{{ $media->hasGeneratedConversion('thumbnail') ? $media->getUrl('thumbnail') : $media->getUrl() }}" alt="{{ $vehicle->name }} gallery image {{ $loop->iteration }}">
                        <div class="flex items-center justify-between gap-2 p-2"><span data-vehicle-gallery-position class="text-xs font-bold text-slate-500">Gallery {{ $loop->iteration }}</span><div class="flex items-center gap-1"><button type="button" data-vehicle-make-cover class="rounded-lg px-2 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-50">Make cover</button><form data-vehicle-gallery-remove method="POST" action="{{ route('admin.vehicles.images.destroy', [$vehicle, $media->id]) }}">@csrf @method('DELETE')<button class="rounded-lg px-2 py-1 text-xs font-bold text-rose-700 hover:bg-rose-50">Remove</button></form></div></div>
                    </article>
                @empty
                    <div data-vehicle-gallery-empty class="col-span-full rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No gallery images yet. Add more images after setting the cover.</div>
                @endforelse
            </div>
        </section>
    </div>
</section>

<script>
    (() => {
        const root = document.querySelector('[data-vehicle-media]');
        if (!root) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const coverInput = root.querySelector('[data-vehicle-cover-input]');
        const coverDropzone = root.querySelector('[data-vehicle-cover-dropzone]');
        const coverPreview = root.querySelector('[data-vehicle-cover-preview]');
        const coverEmpty = root.querySelector('[data-vehicle-cover-empty]');
        const coverStatus = root.querySelector('[data-vehicle-cover-status]');
        const galleryInput = root.querySelector('[data-vehicle-gallery-input]');
        const galleryDropzone = root.querySelector('[data-vehicle-gallery-dropzone]');
        const gallerySelection = root.querySelector('[data-vehicle-gallery-selection]');
        const galleryStatus = root.querySelector('[data-vehicle-gallery-status]');
        const galleryGrid = root.querySelector('[data-vehicle-gallery-grid]');
        const imageCount = root.querySelector('[data-vehicle-image-count]');
        const uploadingMessage = root.querySelector('[data-vehicle-uploading-message]');
        let images = @json($mediaPayload);
        let pendingUploads = 0;
        let dragItem = null;

        const setStatus = (element, message, tone = 'slate') => { element.textContent = message; element.className = `mt-4 text-sm font-semibold text-${tone}-700`; };
        const mediaRequest = async (url, options = {}) => {
            const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) } });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'The vehicle image change could not be saved.');
            return payload;
        };
        const upload = (data, onProgress) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', root.dataset.uploadUrl);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.upload.addEventListener('progress', event => { if (event.lengthComputable) onProgress(Math.round(event.loaded / event.total * 100)); });
            xhr.addEventListener('load', () => { let payload = {}; try { payload = JSON.parse(xhr.responseText || '{}'); } catch {} if (xhr.status >= 200 && xhr.status < 300) resolve(payload); else reject(new Error(Object.values(payload.errors || {}).flat().join(' ') || payload.message || 'The vehicle images could not be uploaded.')); });
            xhr.addEventListener('error', () => reject(new Error('The image upload could not be completed. Please check your connection and try again.')));
            xhr.send(data);
        });
        const syncSaveLock = () => {
            const locked = pendingUploads > 0;
            document.querySelectorAll('[data-vehicle-form] button').forEach(button => {
                if (button.type === 'button') return;
                if (locked) { button.dataset.vehicleWasDisabled = button.disabled ? 'true' : 'false'; button.disabled = true; button.classList.add('cursor-not-allowed', 'opacity-50'); }
                else if (button.dataset.vehicleWasDisabled !== undefined) { button.disabled = button.dataset.vehicleWasDisabled === 'true'; delete button.dataset.vehicleWasDisabled; button.classList.toggle('cursor-not-allowed', button.disabled); button.classList.toggle('opacity-50', button.disabled); }
            });
            root.setAttribute('aria-busy', locked ? 'true' : 'false');
            uploadingMessage.classList.toggle('hidden', !locked);
            uploadingMessage.textContent = locked ? `Uploading ${pendingUploads === 1 ? 'image' : 'images'}… Save vehicle changes is disabled until every upload finishes.` : '';
        };
        const beginUpload = () => { pendingUploads += 1; syncSaveLock(); };
        const finishUpload = () => { pendingUploads = Math.max(0, pendingUploads - 1); syncSaveLock(); };
        const validFiles = files => {
            const selected = [...files];
            const invalid = selected.find(file => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024);
            if (invalid) throw new Error('Use JPG, PNG or WebP images that are 10 MB or smaller.');
            return selected;
        };
        const galleryItems = () => [...galleryGrid.querySelectorAll('[data-vehicle-gallery-item]')];
        const syncCount = () => { imageCount.textContent = `${images.length} ${images.length === 1 ? 'image' : 'images'}`; };
        const makeGalleryItem = image => {
            const card = document.createElement('article');
            card.dataset.vehicleGalleryItem = '';
            card.dataset.mediaId = image.id;
            card.draggable = true;
            card.className = 'group relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm';
            card.innerHTML = `<button type="button" data-vehicle-drag-handle class="absolute left-2 top-2 cursor-grab rounded-lg bg-white/95 px-2 py-1 text-xs font-bold text-slate-600 shadow active:cursor-grabbing" aria-label="Drag to reorder image">↕ Move</button><img class="aspect-[4/3] w-full object-cover" src="${image.thumbnail_url}" alt="Vehicle gallery image"><div class="flex items-center justify-between gap-2 p-2"><span data-vehicle-gallery-position class="text-xs font-bold text-slate-500"></span><div class="flex items-center gap-1"><button type="button" data-vehicle-make-cover class="rounded-lg px-2 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-50">Make cover</button><button type="button" data-vehicle-remove class="rounded-lg px-2 py-1 text-xs font-bold text-rose-700 hover:bg-rose-50">Remove</button></div></div>`;
            return card;
        };
        const render = nextImages => {
            images = nextImages;
            const cover = images[0];
            coverPreview.classList.toggle('hidden', !cover);
            coverEmpty.classList.toggle('hidden', !!cover);
            if (cover) coverPreview.src = cover.url;
            galleryGrid.replaceChildren();
            const gallery = images.slice(1);
            if (!gallery.length) { const empty = document.createElement('div'); empty.dataset.vehicleGalleryEmpty = ''; empty.className = 'col-span-full rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500'; empty.textContent = cover ? 'No gallery images yet. Add more images to build the vehicle gallery.' : 'No images uploaded yet. Set a cover image to begin.'; galleryGrid.append(empty); }
            else gallery.forEach(image => galleryGrid.append(makeGalleryItem(image)));
            galleryItems().forEach((item, index) => { item.querySelector('[data-vehicle-gallery-position]').textContent = `Gallery ${index + 1}`; });
            syncCount();
        };
        const saveOrder = async nextOrder => {
            const payload = await mediaRequest(root.dataset.orderUrl, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ media_ids: nextOrder }) });
            render(payload.data.images);
        };
        const uploadFiles = async (files, asCover = false) => {
            let selected;
            try { selected = validFiles(files); if (!selected.length) return; if (asCover && selected.length > 1) selected = [selected[0]]; if (!asCover && selected.length > 12) throw new Error('Please add no more than 12 gallery images at a time.'); }
            catch (error) { setStatus(asCover ? coverStatus : galleryStatus, error.message, 'rose'); return; }
            const input = asCover ? coverInput : galleryInput;
            const dropzone = asCover ? coverDropzone : galleryDropzone;
            input.disabled = true;
            dropzone.classList.add('pointer-events-none', 'opacity-60');
            if (asCover) { const previewUrl = URL.createObjectURL(selected[0]); const previousUrl = coverPreview.src; const hadCover = !coverPreview.classList.contains('hidden'); coverPreview.src = previewUrl; coverPreview.classList.remove('hidden'); coverEmpty.classList.add('hidden'); coverStatus.textContent = 'Uploading cover image…'; beginUpload(); try { const data = new FormData(); data.append('images[]', selected[0]); data.append('make_primary', '1'); const payload = await upload(data, progress => setStatus(coverStatus, `Uploading cover image… ${progress}%`, 'indigo')); render(payload.data.images); setStatus(coverStatus, 'Cover image uploaded and connected to this vehicle.', 'emerald'); } catch (error) { if (hadCover) coverPreview.src = previousUrl; else { coverPreview.classList.add('hidden'); coverEmpty.classList.remove('hidden'); } setStatus(coverStatus, error.message, 'rose'); } finally { URL.revokeObjectURL(previewUrl); finishUpload(); input.disabled = false; dropzone.classList.remove('pointer-events-none', 'opacity-60'); } return; }
            gallerySelection.textContent = `Uploading ${selected.length} ${selected.length === 1 ? 'image' : 'images'}…`;
            beginUpload();
            try { const data = new FormData(); selected.forEach(file => data.append('images[]', file)); const payload = await upload(data, progress => { gallerySelection.textContent = `Uploading ${selected.length} ${selected.length === 1 ? 'image' : 'images'}… ${progress}%`; }); render(payload.data.images); gallerySelection.textContent = 'Images are uploaded and connected to this vehicle.'; setStatus(galleryStatus, `${selected.length} ${selected.length === 1 ? 'gallery image was' : 'gallery images were'} uploaded.`, 'emerald'); }
            catch (error) { gallerySelection.textContent = 'Images could not be uploaded.'; setStatus(galleryStatus, error.message, 'rose'); }
            finally { finishUpload(); input.disabled = false; dropzone.classList.remove('pointer-events-none', 'opacity-60'); }
        };
        const bindDropzone = (dropzone, input, handler) => {
            dropzone.addEventListener('click', event => { if (event.target !== input && !input.disabled) input.click(); });
            dropzone.addEventListener('keydown', event => { if ((event.key === 'Enter' || event.key === ' ') && !input.disabled) { event.preventDefault(); input.click(); } });
            ['dragenter', 'dragover'].forEach(name => dropzone.addEventListener(name, event => { event.preventDefault(); dropzone.classList.add('border-indigo-500', 'bg-indigo-50'); }));
            ['dragleave', 'drop'].forEach(name => dropzone.addEventListener(name, event => { event.preventDefault(); dropzone.classList.remove('border-indigo-500', 'bg-indigo-50'); }));
            dropzone.addEventListener('drop', event => { if (!input.disabled) handler(event.dataTransfer.files); });
            input.addEventListener('change', () => { handler(input.files); input.value = ''; });
        };
        bindDropzone(coverDropzone, coverInput, files => uploadFiles(files, true));
        bindDropzone(galleryDropzone, galleryInput, files => uploadFiles(files));
        galleryGrid.addEventListener('click', async event => {
            const card = event.target.closest('[data-vehicle-gallery-item]');
            if (!card) return;
            const imageId = Number(card.dataset.mediaId);
            try {
                if (event.target.closest('[data-vehicle-make-cover]')) { const payload = await mediaRequest(root.dataset.primaryUrlTemplate.replace('__MEDIA_ID__', encodeURIComponent(imageId)), { method: 'POST' }); render(payload.data.images); setStatus(galleryStatus, 'Vehicle cover image updated.', 'emerald'); }
                if (event.target.closest('[data-vehicle-remove]')) { const payload = await mediaRequest(root.dataset.deleteUrlTemplate.replace('__MEDIA_ID__', encodeURIComponent(imageId)), { method: 'DELETE' }); render(payload.data.images); setStatus(galleryStatus, 'Vehicle image removed.', 'emerald'); }
            } catch (error) { setStatus(galleryStatus, error.message, 'rose'); }
        });
        galleryGrid.addEventListener('submit', async event => { const form = event.target.closest('[data-vehicle-gallery-remove]'); if (!form) return; event.preventDefault(); try { const payload = await mediaRequest(form.action, { method: 'DELETE' }); render(payload.data.images); setStatus(galleryStatus, 'Vehicle image removed.', 'emerald'); } catch (error) { setStatus(galleryStatus, error.message, 'rose'); } });
        galleryGrid.addEventListener('dragstart', event => { const card = event.target.closest('[data-vehicle-gallery-item]'); if (!card) return; dragItem = card; card.dataset.dragging = 'true'; event.dataTransfer.effectAllowed = 'move'; });
        galleryGrid.addEventListener('dragover', event => { const card = event.target.closest('[data-vehicle-gallery-item]'); if (!card || card === dragItem) return; event.preventDefault(); galleryItems().forEach(item => delete item.dataset.dragOver); card.dataset.dragOver = 'true'; });
        galleryGrid.addEventListener('drop', async event => { const card = event.target.closest('[data-vehicle-gallery-item]'); if (!card || !dragItem || card === dragItem) return; event.preventDefault(); const before = event.clientY < card.getBoundingClientRect().top + card.offsetHeight / 2; galleryGrid.insertBefore(dragItem, before ? card : card.nextSibling); try { await saveOrder([images[0]?.id, ...galleryItems().map(item => Number(item.dataset.mediaId))].filter(Boolean)); setStatus(galleryStatus, 'Gallery order saved.', 'emerald'); } catch (error) { render(images); setStatus(galleryStatus, error.message, 'rose'); } });
        galleryGrid.addEventListener('dragend', () => { galleryItems().forEach(item => { delete item.dataset.dragging; delete item.dataset.dragOver; }); dragItem = null; });
        document.addEventListener('submit', event => { if (pendingUploads > 0 && event.target.matches('[data-vehicle-form]')) { event.preventDefault(); uploadingMessage.focus(); } });
        render(images);
    })();
</script>
