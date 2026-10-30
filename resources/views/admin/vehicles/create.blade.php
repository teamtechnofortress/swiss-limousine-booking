<x-layouts.admin title="Add vehicle">
    <div class="mb-7 flex items-center justify-between gap-4"><div><a href="{{ route('admin.vehicles.index') }}" class="text-sm font-semibold text-slate-500">← Vehicles</a><h1 class="mt-2 text-2xl font-bold text-slate-900">Add vehicle</h1><p class="mt-2 text-sm text-slate-500">Create the vehicle as a draft. You can upload images and publish it from its management page.</p></div></div>
    @include('admin.vehicles.partials.form', ['action' => route('admin.vehicles.store'), 'method' => 'POST'])
</x-layouts.admin>
