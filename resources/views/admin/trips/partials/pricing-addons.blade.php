<section data-pricing-addons hidden class="overflow-hidden rounded-xl border border-slate-200">
        <div class="border-b border-slate-100 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Optional booking extras</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">Add-ons</h2>
            <p class="mt-1 text-sm text-slate-500">Offer optional extras alongside the vehicle fare. They are added to the customer’s total only when selected.</p>
        </div>
        <div class="p-6">
            <input data-addon-input type="hidden" name="configure_addons" value="1">
            <div data-addon-list class="space-y-3">
                @foreach($trip->addons as $index => $addon)
                    <div data-addon-row class="grid gap-3 rounded-xl border border-slate-200 p-4 lg:grid-cols-[minmax(0,1.2fr)_180px_140px_minmax(0,1fr)_auto] lg:items-end">
                        <label><span class="admin-label">Name</span><input data-addon-input class="admin-input !mt-1" required name="addons[{{ $index }}][name]" value="{{ $addon->name }}" placeholder="e.g. Child seat"></label>
                        <label><span class="admin-label">Price (rappen)</span><input data-addon-input class="admin-input !mt-1" required type="number" min="0" name="addons[{{ $index }}][price_minor]" value="{{ $addon->price_minor }}" placeholder="2500"><span class="mt-1 block text-xs text-slate-500">2500 = CHF 25.00</span></label>
                        <label><span class="admin-label">Max per booking</span><input data-addon-input class="admin-input !mt-1" required type="number" min="1" max="20" name="addons[{{ $index }}][max_quantity]" value="{{ $addon->max_quantity }}"></label>
                        <label><span class="admin-label">Customer description <span class="normal-case text-slate-400">optional</span></span><input data-addon-input class="admin-input !mt-1" name="addons[{{ $index }}][description]" value="{{ $addon->description }}" placeholder="Short explanation"></label>
                        <div class="flex items-center gap-3 pb-1"><label class="inline-flex items-center gap-2 whitespace-nowrap text-sm font-semibold text-slate-700"><input data-addon-input type="hidden" name="addons[{{ $index }}][is_active]" value="0"><input data-addon-input type="checkbox" name="addons[{{ $index }}][is_active]" value="1" @checked($addon->is_active)> Offer</label><button type="button" data-remove-addon class="text-sm font-bold text-rose-600 hover:text-rose-700">Remove</button></div>
                    </div>
                @endforeach
            </div>
            <div data-addon-empty @class(['py-2 text-sm text-slate-500' => $trip->addons->isEmpty(), 'hidden' => $trip->addons->isNotEmpty()])>No extras yet. Add one only if customers can choose it during booking.</div>
            <template data-addon-template>
                <div data-addon-row class="grid gap-3 rounded-xl border border-slate-200 p-4 lg:grid-cols-[minmax(0,1.2fr)_180px_140px_minmax(0,1fr)_auto] lg:items-end">
                    <label><span class="admin-label">Name</span><input data-addon-input data-addon-field="name" class="admin-input !mt-1" required placeholder="e.g. Child seat"></label>
                    <label><span class="admin-label">Price (rappen)</span><input data-addon-input data-addon-field="price_minor" class="admin-input !mt-1" required type="number" min="0" placeholder="2500"><span class="mt-1 block text-xs text-slate-500">2500 = CHF 25.00</span></label>
                    <label><span class="admin-label">Max per booking</span><input data-addon-input data-addon-field="max_quantity" class="admin-input !mt-1" required type="number" min="1" max="20" value="1"></label>
                    <label><span class="admin-label">Customer description <span class="normal-case text-slate-400">optional</span></span><input data-addon-input data-addon-field="description" class="admin-input !mt-1" placeholder="Short explanation"></label>
                    <div class="flex items-center gap-3 pb-1"><label class="inline-flex items-center gap-2 whitespace-nowrap text-sm font-semibold text-slate-700"><input data-addon-input data-addon-field="is_active" type="hidden" value="0"><input data-addon-input data-addon-field="is_active" type="checkbox" value="1" checked> Offer</label><button type="button" data-remove-addon class="text-sm font-bold text-rose-600 hover:text-rose-700">Remove</button></div>
                </div>
            </template>
            <div class="mt-5"><button data-add-addon type="button" class="admin-button-soft">+ Add extra</button></div>
        </div>
</section>
