{{-- File: resources/views/pages/academics/relations/curriculum/_modal-delete.blade.php --}}
@php
$inUse = $curriculum->academic_year_curricula_count > 0;
@endphp

<div x-data="{ open: false, deleting: false }"
    x-init="setTimeout(() => open = true, 10)"
    @close-modal.window="open = false; setTimeout(() => $el.closest('#modal-container').innerHTML = '', 150)">

    <x-ui.modal show="open" maxWidth="md">
        <div class="px-4 sm:px-6 py-6 text-center">
            <div class="mx-auto size-14 rounded-full {{ $inUse ? 'bg-amber-100 text-amber-600' : 'bg-red-100 text-red-600' }} flex items-center justify-center">
                <i data-lucide="{{ $inUse ? 'shield-alert' : 'trash-2' }}" class="size-7"></i>
            </div>

            <h3 class="mt-4 font-bold text-foreground text-lg">
                {{ $inUse ? 'Kurikulum Tidak Dapat Dihapus' : 'Hapus Kurikulum?' }}
            </h3>
            <p class="mt-1 text-sm font-semibold text-foreground">{{ $curriculum->name }}
                <span class="font-mono text-secondary">({{ $curriculum->code }})</span>
            </p>

            @if ($inUse)
            <p class="mt-3 text-sm text-secondary leading-relaxed">
                Kurikulum ini sudah dipakai di <b>{{ $curriculum->academic_year_curricula_count }} tahun ajaran</b>.
                Menghapusnya akan ikut menghapus penerapannya di tahun ajaran dan kelas.
                Gunakan <b>Arsipkan</b> agar data lama tetap aman.
            </p>
            @else
            <p class="mt-3 text-sm text-secondary leading-relaxed">
                Tindakan ini tidak dapat dibatalkan.
                @if ($curriculum->curriculum_subjects_count > 0)
                <b>{{ $curriculum->curriculum_subjects_count }} mapel</b> yang terdaftar pada kurikulum ini ikut terhapus.
                @endif
            </p>
            @endif
        </div>

        <div class="px-4 sm:px-6 py-4 border-t border-border bg-slate-50/50 flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5 sm:gap-2 shrink-0">
            <button type="button" @click="open = false; setTimeout(() => $el.closest('#modal-container').innerHTML = '', 150)"
                class="w-full sm:w-auto flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl border border-border bg-white text-secondary text-sm font-semibold hover:bg-muted transition-all cursor-pointer">
                <i data-lucide="undo-2" class="size-4"></i>
                <span>Batal</span>
            </button>

            @unless ($inUse)
            <button type="button"
                hx-delete="{{ route('rapor.curricula.destroy', $curriculum->id) }}"
                hx-target="#curricula-container"
                hx-swap="outerHTML"
                hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'
                :disabled="deleting"
                @htmx:before-request="deleting = true"
                @htmx:after-request="deleting = false"
                class="w-full sm:w-auto flex items-center justify-center gap-1.5 min-w-[140px] px-5 py-2.5 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 shadow-sm transition-all cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed">
                <i data-lucide="trash-2" class="size-4" x-show="!deleting"></i>
                <i data-lucide="loader-2" stroke-width="3" class="size-4 animate-spin" x-show="deleting" x-cloak></i>
                <span>Ya, Hapus</span>
            </button>
            @endunless
        </div>
    </x-ui.modal>

    <script>
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</div>