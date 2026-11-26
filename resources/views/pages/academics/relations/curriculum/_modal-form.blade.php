{{-- File: resources/views/pages/academics/relations/curriculum/_modal-form.blade.php --}}
@php
$curriculum = $curriculum ?? new \App\Models\Rapor\Curriculum();
$isEdit = $curriculum->exists;
$modalTitle = $isEdit ? 'Edit Kurikulum' : 'Tambah Kurikulum';
$actionUrl = $isEdit
? route('rapor.curricula.update', $curriculum->id)
: route('rapor.curricula.store');

$nameValue = old('name', $curriculum->name ?? '');
$codeValue = old('code', $curriculum->code ?? '');
$descriptionValue = old('description', $curriculum->description ?? '');
$statusValue = old('status', $curriculum->status ?? 'active');
@endphp

<div x-data="{ open: false }"
    x-init="setTimeout(() => open = true, 10)"
    @close-modal.window="open = false; setTimeout(() => $el.closest('#modal-container').innerHTML = '', 150)">

    <x-ui.modal show="open" maxWidth="md">
        {{-- Modal Header --}}
        <div class="flex items-start sm:items-center justify-between gap-3 px-4 sm:px-6 py-4 border-b border-border bg-slate-50/50 shrink-0">
            <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                <div class="size-10 sm:size-12 rounded-full {{ $isEdit ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }} flex items-center justify-center shrink-0 shadow-sm">
                    <i data-lucide="{{ $isEdit ? 'file-pen-line' : 'library-big' }}" class="size-5 sm:size-6"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-foreground text-base sm:text-lg leading-tight truncate">{{ $modalTitle }}</h3>
                    <p class="text-xs sm:text-sm text-secondary mt-0.5 truncate">
                        {{ $isEdit ? $curriculum->getOriginal('name') : 'Master kurikulum rapor' }}
                    </p>
                </div>
            </div>

            <button type="button" @click="open = false; setTimeout(() => $el.closest('#modal-container').innerHTML = '', 150)"
                class="size-8 sm:size-9 flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:bg-error/10 hover:text-error hover:border-error/30 transition-colors cursor-pointer shrink-0">
                <i data-lucide="x" class="size-4 pointer-events-none"></i>
            </button>
        </div>

        {{-- Form HTMX: sukses → fragmen tabel menggantikan #curricula-container;
             gagal validasi → controller me-retarget ke #modal-container --}}
        <form id="curriculum-form"
            hx-post="{{ $actionUrl }}"
            hx-target="#curricula-container"
            hx-swap="outerHTML"
            novalidate
            x-data="{ saving: false }"
            @htmx:before-request="saving = true"
            @htmx:after-request="saving = false"
            class="flex flex-col flex-1 min-h-0">
            @csrf
            @if ($isEdit)
            @method('PUT')
            @endif

            <div class="block p-4 sm:p-7 overflow-y-auto max-h-[calc(100vh-10rem)] sm:max-h-[70vh]">
                <div class="grid grid-cols-1 gap-4 sm:gap-5">

                    {{-- Nama --}}
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Nama Kurikulum <span class="text-error">*</span></label>
                        <input type="text" name="name" value="{{ $nameValue }}" maxlength="255"
                            placeholder="mis. Kurikulum Merdeka"
                            class="w-full rounded-xl border {{ $errors->has('name') ? 'border-error' : 'border-border' }} px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        @error('name')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Kode --}}
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Kode <span class="text-error">*</span></label>
                        <input type="text" name="code" value="{{ $codeValue }}" maxlength="50"
                            placeholder="mis. MERDEKA"
                            class="w-full rounded-xl border {{ $errors->has('code') ? 'border-error' : 'border-border' }} px-3.5 py-2.5 text-sm font-mono uppercase focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        @error('code')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Status <span class="text-error">*</span></label>
                        <x-ui.select name="status" :options="$statusOptions" :value="$statusValue" placeholder="-- Pilih Status --" />
                        <p class="text-[11px] text-secondary mt-1">Kurikulum diarsipkan tetap tersimpan, hanya tidak ditawarkan untuk penggunaan baru.</p>
                        @error('status')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Deskripsi --}}
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Deskripsi <span class="text-secondary font-normal">(opsional)</span></label>
                        <textarea name="description" rows="3" maxlength="1000"
                            placeholder="Keterangan singkat kurikulum"
                            class="w-full rounded-xl border {{ $errors->has('description') ? 'border-error' : 'border-border' }} px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary">{{ $descriptionValue }}</textarea>
                        @error('description')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Footer Modal --}}
            <div class="px-4 sm:px-6 py-4 border-t border-border bg-slate-50/50 flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5 sm:gap-2 shrink-0">
                <button type="button" @click="open = false; setTimeout(() => $el.closest('#modal-container').innerHTML = '', 150)"
                    class="w-full sm:w-auto flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl border border-border bg-white text-secondary text-sm font-semibold hover:bg-muted hover:border-gray-300 transition-all cursor-pointer">
                    <i data-lucide="undo-2" class="size-4"></i>
                    <span>Batal</span>
                </button>
                <button type="submit" :disabled="saving"
                    class="w-full sm:w-auto flex items-center justify-center min-w-[140px] px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-primary/90 shadow-sm shadow-primary/30 transition-all cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed">
                    <div x-show="!saving" class="flex items-center gap-1.5">
                        <i data-lucide="save" class="size-4"></i>
                        <span>Simpan Data</span>
                    </div>
                    <div x-show="saving" x-cloak class="flex items-center gap-1.5">
                        <i data-lucide="loader-2" stroke-width="3" class="size-4 animate-spin"></i>
                        <span>Memproses...</span>
                    </div>
                </button>
            </div>
        </form>
    </x-ui.modal>

    <script>
        if (typeof lucide !== 'undefined') lucide.createIcons();
    </script>
</div>