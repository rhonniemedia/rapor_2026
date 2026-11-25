{{--
    Lokasi: resources/views/components/ui/action-menu.blade.php
    Pemakaian: <x-ui.action-menu :actions="[...]" />

    Key setiap action:
      label    (string)  teks tombol / tooltip
      icon     (string)  nama ikon lucide
      url      (string)  URL htmx
      method   (string)  get|post|patch|put|delete  (default: get)
      target   (string)  selector hx-target          (default: #modal-container)
      swap     (string)  hx-swap                     (default: innerHTML)
      variant  (string)  default|primary|warning|danger (default: default)
      divider  (bool)    garis pemisah SEBELUM item di mode dropdown
      show     (bool)    false = action disembunyikan (default: true)
--}}
@props(['actions' => []])

@php
use Illuminate\View\ComponentAttributeBag;

// Class harus ditulis utuh agar terdeteksi oleh Tailwind JIT.
$inlineVariants = [
'default' => 'hover:bg-gray-50 hover:text-gray-900',
'primary' => 'hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200',
'warning' => 'hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200',
'danger' => 'hover:bg-error/10 hover:text-error hover:border-error/30',
];

$menuVariants = [
'default' => 'text-secondary hover:bg-slate-100 hover:text-foreground',
'primary' => 'text-secondary hover:bg-slate-100 hover:text-foreground',
'warning' => 'text-secondary hover:bg-slate-100 hover:text-foreground',
'danger' => 'text-error hover:bg-error/10',
];

$items = collect($actions)
->filter(fn ($a) => $a['show'] ?? true)
->map(function ($a) {
$method = strtolower($a['method'] ?? 'get');
$a['variant'] = $a['variant'] ?? 'default';
$a['hx'] = new ComponentAttributeBag([
"hx-{$method}" => $a['url'],
'hx-target' => $a['target'] ?? '#modal-container',
'hx-swap' => $a['swap'] ?? 'innerHTML',
]);
return $a;
})
->values();
@endphp

@if ($items->isNotEmpty())
<div class="relative flex items-center justify-start"
    x-data="{
        open: false,
        style: '',
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.updatePosition());
            }
        },
        updatePosition() {
            const btn = this.$refs.btn;
            const menu = this.$refs.menu;
            if (!btn || !menu) return;

            const rect = btn.getBoundingClientRect();
            const menuWidth = menu.offsetWidth || 176;
            const menuHeight = menu.offsetHeight || 136;

            // Rata kanan sejajar tombol
            let left = rect.right - menuWidth;
            if (left < 8) left = 8;
            if (left + menuWidth > window.innerWidth - 8) {
                left = window.innerWidth - menuWidth - 8;
            }

            // Buka ke bawah; jika mentok bawah layar, buka ke atas
            let top = rect.bottom + 6;
            if (top + menuHeight > window.innerHeight - 8) {
                top = rect.top - menuHeight - 6;
            }

            this.style = `left: ${left}px; top: ${top}px;`;
        }
    }"
    @close-action-menus.window="open = false"
    @scroll.window="open = false"
    @resize.window="if (open) updatePosition()">

    {{-- Layar lebar (2xl ke atas): tombol ikon sejajar --}}
    <div class="hidden 2xl:flex items-center gap-2">
        @foreach ($items as $item)
        <button type="button" title="{{ $item['label'] }}"
            {{ $item['hx'] }}
            class="size-8 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary transition-colors cursor-pointer {{ $inlineVariants[$item['variant']] ?? $inlineVariants['default'] }}">
            <i data-lucide="{{ $item['icon'] }}" class="size-4 pointer-events-none"></i>
        </button>
        @endforeach
    </div>

    {{-- Di bawah 2xl: dropdown --}}
    <div class="2xl:hidden">
        <button type="button" title="Opsi Lainnya"
            x-ref="btn"
            @click="toggle()"
            class="size-8 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:bg-gray-50 hover:text-gray-900 transition-colors cursor-pointer">
            <i data-lucide="more-vertical" class="size-4 pointer-events-none"></i>
        </button>

        <template x-teleport="body">
            <div x-ref="menu"
                x-show="open"
                x-cloak
                @click.outside="if (!$refs.btn.contains($event.target)) open = false"
                :style="style"
                class="fixed w-44 rounded-xl border border-border bg-white p-1.5 shadow-xl z-[9999]">
                @foreach ($items as $item)
                @if (!empty($item['divider']) && !$loop->first)
                <div class="my-1 border-t border-border/70"></div>
                @endif

                <button type="button"
                    @click="open = false"
                    {{ $item['hx'] }}
                    class="w-full px-3 py-2 rounded-lg text-left text-xs font-medium flex items-center gap-2.5 transition-colors cursor-pointer {{ $menuVariants[$item['variant']] ?? $menuVariants['default'] }}">
                    <i data-lucide="{{ $item['icon'] }}" class="size-3.5 pointer-events-none"></i>
                    <span>{{ $item['label'] }}</span>
                </button>
                @endforeach
            </div>
        </template>
    </div>
</div>
@endif