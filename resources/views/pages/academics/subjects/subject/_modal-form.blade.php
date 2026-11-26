@php
$subject = $subject ?? new \App\Models\Rapor\Subject();
$isEdit = $subject->exists;
$modalTitle = $isEdit ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran';
$actionUrl = $isEdit
? route('academics.subjects.update', $subject->id)
: route('academics.subjects.store');

$nameValue = old('name', $subject->name ?? '');
$codeValue = old('code', $subject->code ?? '');
$isReligionValue = (bool) old('is_religion_subject', $subject->is_religion_subject ?? false);
$religionValue = old('religion', $subject->religion ?? '');
$statusValue = old('status', $subject->status ?? \App\Models\Rapor\Subject::STATUS_ACTIVE);

$categoryOptions = [
['value' => '0', 'label' => 'Umum'],
['value' => '1', 'label' => 'Agama (Terkait)'],
];
@endphp

<div x-data="{
        open: false,
        isReligion: {{ $isReligionValue ? 'true' : 'false' }}
    }"
    x-init="setTimeout(() => open = true, 10)"
    @close-modal.window="open = false; setTimeout(() => $el.closest('#modal-container').innerHTML = '', 150)">

    <x-ui.modal show="open" maxWidth="md">
        {{-- Header --}}
        <div class="flex items-start sm:items-center justify-between gap-3 px-4 sm:px-6 py-4 border-b border-border bg-slate-50/50 shrink-0">
            <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                <div class="size-10 sm:size-12 rounded-full {{ $isEdit ? 'bg-blue-100 text-blue-700' : 'bg-emerald-100 text-emerald-700' }} flex items-center justify-center shrink-0 shadow-sm">
                    <i data-lucide="{{ $isEdit ? 'file-pen-line' : 'book-open' }}" class="size-5 sm:size-6"></i>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-foreground text-base sm:text-lg leading-tight truncate">{{ $modalTitle }}</h3>
                    <p class="text-xs sm:text-sm text-secondary mt-0.5 truncate">
                        {{ $isEdit ? $subject->getOriginal('name') : 'Master mata pelajaran rapor' }}
                    </p>
                </div>
            </div>

            <button type="button" @click="open = false; setTimeout(() => $el.closest('#modal-container').innerHTML = '', 150)"
                class="size-8 sm:size-9 flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:bg-error/10 hover:text-error hover:border-error/30 transition-colors cursor-pointer shrink-0">
                <i data-lucide="x" class="size-4 pointer-events-none"></i>
            </button>
        </div>

        {{-- Form --}}
        <form id="subject-form"
            hx-post="{{ $actionUrl }}"
            hx-target="#subjects-container"
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

                    {{-- Nama Mata Pelajaran --}}
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Nama Mata Pelajaran <span class="text-error">*</span></label>
                        <input type="text" name="name" value="{{ $nameValue }}" maxlength="255"
                            placeholder="mis. Matematika atau Pendidikan Agama Islam"
                            class="w-full rounded-xl border {{ $errors->has('name') ? 'border-error' : 'border-border' }} px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        @error('name')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Kode --}}
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Kode <span class="text-error">*</span></label>
                        <input type="text" name="code" value="{{ $codeValue }}" maxlength="50"
                            placeholder="mis. MTK atau PAI"
                            class="w-full rounded-xl border {{ $errors->has('code') ? 'border-error' : 'border-border' }} px-3.5 py-2.5 text-sm font-mono uppercase focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        @error('code')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Kategori: Umum | Agama (Terkait) --}}
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Kategori <span class="text-error">*</span></label>
                        <x-ui.select
                            name="is_religion_subject"
                            :options="$categoryOptions"
                            :value="$isReligionValue ? '1' : '0'"
                            placeholder="-- Pilih Kategori --"
                            @change="isReligion = ($event.target.value === '1')" />
                        @error('is_religion_subject')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Agama Terkait (wajib bila Kategori = Agama (Terkait)) --}}
                    <div x-show="isReligion" x-cloak>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Agama Terkait <span class="text-error">*</span></label>
                        <input type="text" name="religion" value="{{ $religionValue }}" maxlength="100"
                            placeholder="mis. Islam, Kristen, Katolik, Hindu, Buddha, Konghucu"
                            class="w-full rounded-xl border {{ $errors->has('religion') ? 'border-error' : 'border-border' }} px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        @error('religion')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Status --}}
                    <div>
                        <label class="block text-sm font-medium text-foreground mb-1.5">Status <span class="text-error">*</span></label>
                        <x-ui.select name="status" :options="$statusOptions" :value="$statusValue" placeholder="-- Pilih Status --" />
                        <p class="text-[11px] text-secondary mt-1">Mata pelajaran diarsipkan tetap tersimpan, hanya tidak ditawarkan untuk penggunaan baru.</p>
                        @error('status')
                        <p class="text-xs text-error mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Footer --}}
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