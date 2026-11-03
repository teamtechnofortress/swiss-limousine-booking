<style>
    #trip-content-manager ~ details[data-trip-step="overview"],
    #trip-content-manager ~ details[data-trip-step="content"] { display: none; }
</style>

<section id="trip-content-manager" data-trip-step="content" class="space-y-6">
    <form data-trip-rich-content-form method="POST" action="{{ route('admin.trips.content', $trip) }}" class="admin-card overflow-hidden">@csrf @method('PUT')
        <div class="border-b border-slate-100 px-6 py-5"><p class="text-xs font-bold uppercase tracking-[.14em] text-indigo-600">Website copy</p><h2 class="mt-1 text-lg font-bold text-slate-900">Trip description</h2><p class="mt-1 text-sm text-slate-500">Paste formatted content or write it here. Headings, lists, links and emphasis are saved safely as HTML.</p></div>
        <div class="p-6">
            <div data-trip-editor-toolbar class="flex flex-wrap gap-2 rounded-t-xl border border-slate-200 bg-slate-50 p-2">
                <div data-editor-menu class="relative"><button type="button" data-editor-command="bold" class="admin-button-soft !rounded-lg !px-3 !py-2" title="Bold"><strong>B</strong></button><div data-editor-menu-options><button type="button" data-editor-command="bold">Apply bold</button><button type="button" data-editor-remove="bold">Remove bold</button></div></div>
                <div data-editor-menu class="relative"><button type="button" data-editor-command="italic" class="admin-button-soft !rounded-lg !px-3 !py-2" title="Italic"><em>I</em></button><div data-editor-menu-options><button type="button" data-editor-command="italic">Apply italic</button><button type="button" data-editor-remove="italic">Remove italic</button></div></div>
                <div data-editor-menu class="relative"><button type="button" data-editor-block="h2" class="admin-button-soft !rounded-lg !px-3 !py-2" title="Heading">H</button><div data-editor-menu-options><button type="button" data-editor-block="h1">Heading 1</button><button type="button" data-editor-block="h2">Heading 2</button><button type="button" data-editor-block="h3">Heading 3</button><button type="button" data-editor-remove="heading">Remove heading</button></div></div>
                <div data-editor-menu class="relative"><button type="button" data-editor-command="insertUnorderedList" class="admin-button-soft !rounded-lg !px-3 !py-2" title="List">• List</button><div data-editor-menu-options><button type="button" data-editor-command="insertUnorderedList">Bullet list</button><button type="button" data-editor-command="insertOrderedList">Numbered list</button><button type="button" data-editor-remove="list">Remove list</button></div></div>
                <div data-editor-menu class="relative"><button type="button" data-editor-block="blockquote" class="admin-button-soft !rounded-lg !px-3 !py-2" title="Quote">Quote</button><div data-editor-menu-options><button type="button" data-editor-block="blockquote">Apply quote</button><button type="button" data-editor-remove="quote">Remove quote</button></div></div>
                <div data-editor-menu class="relative"><button type="button" data-editor-link class="admin-button-soft !rounded-lg !px-3 !py-2">Link</button><div data-editor-menu-options><button type="button" data-editor-link>Add link</button><button type="button" data-editor-remove="link">Remove link</button></div></div>
                <div data-editor-menu class="relative"><button type="button" data-editor-command="removeFormat" class="admin-button-soft !rounded-lg !px-3 !py-2">Clear</button><div data-editor-menu-options><button type="button" data-editor-command="removeFormat">Clear inline style</button><button type="button" data-editor-remove="all">Clear all formatting</button></div></div>
            </div>
            <div data-trip-rich-editor contenteditable="true" role="textbox" aria-multiline="true" aria-label="Trip description" class="min-h-80 rounded-b-xl border border-t-0 border-slate-200 bg-white p-5 text-sm leading-7 text-slate-800 outline-none focus:border-slate-900 focus:ring-3 focus:ring-slate-900/10">{!! old('description', $trip->description) !!}</div>
            <textarea data-trip-rich-html name="description" class="hidden"></textarea>
            <p data-trip-rich-status class="mt-3 text-sm text-slate-500" aria-live="polite">Formatting is preserved when you paste. Use the Media &amp; gallery section below for trip images.</p>
        </div>
        <div class="flex justify-end border-t border-slate-100 p-5"><button class="admin-button">Save trip content</button></div>
    </form>

    @include('admin.trips.partials.media-manager', ['trip' => $trip])
</section>
