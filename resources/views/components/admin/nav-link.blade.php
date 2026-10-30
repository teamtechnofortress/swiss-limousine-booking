@props(['active' => false, 'icon' => 'dot'])
<a {{ $attributes->class([$active ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-100', 'group flex items-center gap-3 rounded-lg px-3 py-2.5 font-medium transition']) }}>
    <span class="grid size-5 place-items-center text-xs {{ $active ? 'text-amber-300' : 'text-slate-500 group-hover:text-slate-300' }}">{{ match($icon) { 'grid' => '▦', 'calendar' => '▣', 'card' => '▤', 'car' => '▱', 'pin' => '◉', 'sliders' => '≡', 'mail' => '✉', 'users' => '♙', 'shield' => '◇', 'chart' => '↗', 'history' => '◷', default => '•' } }}</span>
    {{ $slot }}
</a>
