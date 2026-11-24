<div class="relative flex items-center justify-start"
    x-data="{
        open: false,
        style: '',
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => {
                    this.updatePosition();
                });
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

            // Buka ke bawah; jika mentok bawah layar/pagination, buka ke atas
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

    {{-- Tampilan Normal: Layar Lebar (2xl ke atas / Full HD) --}}
    <div class="hidden 2xl:flex items-center gap-2">
        {{-- Edit --}}
        <button type="button" title="Edit"
            hx-get="{{ route('rapor.curricula.edit', $c->id) }}"
            hx-target="#modal-container" hx-swap="innerHTML"
            class="size-8 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:bg-blue-50 hover:text-blue-600 hover:border-blue-200 transition-colors cursor-pointer">
            <i data-lucide="pencil" class="size-4 pointer-events-none"></i>
        </button>

        {{-- Aktifkan / Arsipkan --}}
        <button type="button" title="{{ $c->isActive() ? 'Arsipkan' : 'Aktifkan' }}"
            hx-patch="{{ route('rapor.curricula.toggle-status', $c->id) }}"
            hx-target="#curricula-container" hx-swap="outerHTML"
            class="size-8 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200 transition-colors cursor-pointer">
            <i data-lucide="{{ $c->isActive() ? 'archive' : 'archive-restore' }}" class="size-4 pointer-events-none"></i>
        </button>

        {{-- Hapus (membuka modal konfirmasi) --}}
        <button type="button" title="Hapus"
            hx-get="{{ route('rapor.curricula.confirm-delete', $c->id) }}"
            hx-target="#modal-container" hx-swap="innerHTML"
            class="size-8 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:bg-error/10 hover:text-error hover:border-error/30 transition-colors cursor-pointer">
            <i data-lucide="trash-2" class="size-4 pointer-events-none"></i>
        </button>
    </div>

    {{-- Tampilan Dropdown: Layar HD ke bawah (di bawah 2xl) --}}
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
                {{-- Edit --}}
                <button type="button"
                    @click="open = false"
                    hx-get="{{ route('rapor.curricula.edit', $c->id) }}"
                    hx-target="#modal-container" hx-swap="innerHTML"
                    class="w-full px-3 py-2 rounded-lg text-left text-xs font-medium text-secondary hover:bg-slate-100 hover:text-foreground flex items-center gap-2.5 transition-colors cursor-pointer">
                    <i data-lucide="pencil" class="size-3.5 text-secondary pointer-events-none"></i>
                    <span>Edit</span>
                </button>

                {{-- Aktifkan / Arsipkan --}}
                <button type="button"
                    @click="open = false"
                    hx-patch="{{ route('rapor.curricula.toggle-status', $c->id) }}"
                    hx-target="#curricula-container" hx-swap="outerHTML"
                    class="w-full px-3 py-2 rounded-lg text-left text-xs font-medium text-secondary hover:bg-slate-100 hover:text-foreground flex items-center gap-2.5 transition-colors cursor-pointer">
                    <i data-lucide="{{ $c->isActive() ? 'archive' : 'archive-restore' }}" class="size-3.5 text-secondary pointer-events-none"></i>
                    <span>{{ $c->isActive() ? 'Arsipkan' : 'Aktifkan' }}</span>
                </button>

                <div class="my-1 border-t border-border/70"></div>

                {{-- Hapus --}}
                <button type="button"
                    @click="open = false"
                    hx-get="{{ route('rapor.curricula.confirm-delete', $c->id) }}"
                    hx-target="#modal-container" hx-swap="innerHTML"
                    class="w-full px-3 py-2 rounded-lg text-left text-xs font-medium text-error hover:bg-error/10 flex items-center gap-2.5 transition-colors cursor-pointer">
                    <i data-lucide="trash-2" class="size-3.5 pointer-events-none"></i>
                    <span>Hapus</span>
                </button>
            </div>
        </template>
    </div>
</div>