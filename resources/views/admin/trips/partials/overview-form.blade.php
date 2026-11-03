<form method="POST" action="{{ $action }}" class="admin-card overflow-hidden">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <div class="grid gap-5 p-6 md:grid-cols-2">
        <div><label class="admin-label">Title</label><input class="admin-input" required name="title" value="{{ old('title', $trip?->title) }}"></div>
        <div><label class="admin-label">URL slug</label><input class="admin-input" name="slug" value="{{ old('slug', $trip?->slug) }}" placeholder="Generated from title"></div>
        <div><label class="admin-label">Subtitle</label><input class="admin-input" name="subtitle" value="{{ old('subtitle', $trip?->subtitle) }}"></div>
        <div><label class="admin-label">Region</label><input class="admin-input" required name="region" value="{{ old('region', $trip?->region) }}" placeholder="e.g. Central Switzerland"></div>
        <div class="md:col-span-2"><label class="admin-label">Card summary</label><textarea class="admin-input min-h-20" required maxlength="300" name="summary">{{ old('summary', $trip?->summary) }}</textarea></div>
        <div><label class="admin-label">SEO title</label><input class="admin-input" name="meta_title" value="{{ old('meta_title',$trip?->meta_title) }}"></div>
        <div><label class="admin-label">SEO description</label><input class="admin-input" maxlength="160" name="meta_description" value="{{ old('meta_description',$trip?->meta_description) }}"></div>
        <label class="flex items-center gap-3 self-end pb-3 text-sm font-semibold"><input type="hidden" name="is_featured" value="0"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured',$trip?->is_featured))> Feature on website</label>

    </div>
    <div class="flex justify-end border-t border-slate-100 p-5"><button class="admin-button">{{ $trip ? 'Save overview' : 'Create trip draft' }}</button></div>
</form>
