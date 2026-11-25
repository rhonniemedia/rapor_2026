{{-- Tabel Mata Pelajaran --}}
@php
$typeMap = [
'wajib' => [
'label' => 'Wajib',
'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
'dot' => 'bg-emerald-500',
],
'peminatan' => [
'label' => 'Peminatan',
'badge' => 'bg-sky-50 text-sky-700 border-sky-200',
'dot' => 'bg-sky-500',
],
'mulok' => [
'label' => 'Muatan Lokal',
'badge' => 'bg-amber-50 text-amber-700 border-amber-200',
'dot' => 'bg-amber-500',
],
'default' => [
'label' => 'Lainnya',
'badge' => 'bg-slate-50 text-slate-700 border-slate-200',
'dot' => 'bg-slate-400',
],
];
@endphp

<div id="subjects-container">

    {{-- ============ 1. DESKTOP TABLE ============ --}}
    <x-ui.table>
        <thead>
            <tr class="border-b border-border">
                <x-ui.table.th width="w-[35%]" sub="Nama | Kode">Mata Pelajaran</x-ui.table.th>
                <x-ui.table.th width="w-[20%]" sub="Pengelompokan">Kelompok</x-ui.table.th>
                <x-ui.table.th width="w-[15%]" sub="Wajib | Peminatan">Jenis</x-ui.table.th>
                <x-ui.table.th width="w-[20%]" sub="Kurikulum | Jurusan">Pemakaian</x-ui.table.th>
                <x-ui.table.th width="w-[10%]" sub="Kelola">Aksi</x-ui.table.th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border border-b border-border">
            @forelse ($subjects as $s)
            @php
            $typeKey = is_string($s['type'] ?? null) && $s['type'] !== '' ? $s['type'] : 'default';
            $t = $typeMap[$typeKey] ?? $typeMap['default'];
            @endphp
            <tr id="row-subject-{{ $s['id'] }}" data-search="{{ $s['name'] }} {{ $s['code'] }} {{ $s['group'] }}" class="group transition-colors hover:bg-muted/40">
                <x-ui.table.td>
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <i data-lucide="book-open" class="size-5 text-primary"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-foreground text-sm truncate">{{ $s['name'] }}</div>
                            <div class="text-xs text-secondary mt-1 font-mono font-semibold text-foreground/80">{{ $s['code'] }}</div>
                        </div>
                    </div>
                </x-ui.table.td>

                <x-ui.table.td>
                    <span class="text-sm font-medium text-foreground">{{ $s['group'] }}</span>
                </x-ui.table.td>

                <x-ui.table.td>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $t['badge'] }}">
                        <span class="size-1.5 rounded-full {{ $t['dot'] }}"></span>{{ $t['label'] }}
                    </span>
                </x-ui.table.td>

                <x-ui.table.td>
                    <div class="text-sm font-medium text-foreground">{{ $s['curricula'] }} kurikulum</div>
                    <div class="text-xs text-secondary mt-1 truncate">{{ $s['majors'] }} jurusan</div>
                </x-ui.table.td>

                <x-ui.table.td>
                    <div class="flex items-center gap-1.5">
                        <button type="button" title="Edit" aria-label="Edit" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-primary hover:text-primary transition-colors cursor-pointer">
                            <i data-lucide="pencil" class="size-4"></i>
                        </button>
                        <button type="button" title="Hapus" aria-label="Hapus" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-error hover:text-error transition-colors cursor-pointer">
                            <i data-lucide="trash-2" class="size-4"></i>
                        </button>
                    </div>
                </x-ui.table.td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-16 text-center text-secondary">
                    <div class="flex flex-col items-center gap-3">
                        <i data-lucide="inbox" class="size-10 text-border"></i>
                        <p class="font-medium text-sm">Belum ada mata pelajaran.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- ============ 2. MOBILE CARDS ============ --}}
    <div class="lg:hidden divide-y divide-border border-y border-border -mx-5 mt-2 mb-4">
        @forelse ($subjects as $s)
        @php
        $typeKey = is_string($s['type'] ?? null) && $s['type'] !== '' ? $s['type'] : 'default';
        $t = $typeMap[$typeKey] ?? $typeMap['default'];
        @endphp
        <div id="card-subject-{{ $s['id'] }}" data-search="{{ $s['name'] }} {{ $s['code'] }} {{ $s['group'] }}" class="px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i data-lucide="book-open" class="size-5 text-primary"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-foreground text-sm truncate">{{ $s['name'] }}</div>
                        <p class="text-xs text-secondary mt-1 truncate font-mono">{{ $s['code'] }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $t['badge'] }}">{{ $t['label'] }}</span>
            </div>

            <div class="mt-3 border-y border-border divide-y divide-border text-xs">
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="layers" class="size-3.5 text-secondary/50"></i>Kelompok</p>
                    <p class="font-medium text-foreground">{{ $s['group'] }}</p>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="library-big" class="size-3.5 text-secondary/50"></i>Kurikulum</p>
                    <p class="font-medium text-foreground">{{ $s['curricula'] }} kurikulum</p>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="graduation-cap" class="size-3.5 text-secondary/50"></i>Jurusan</p>
                    <p class="font-medium text-foreground">{{ $s['majors'] }} jurusan</p>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-end gap-1.5">
                <button type="button" title="Edit" aria-label="Edit" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-primary hover:text-primary transition-colors cursor-pointer"><i data-lucide="pencil" class="size-4"></i></button>
                <button type="button" title="Hapus" aria-label="Hapus" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-error hover:text-error transition-colors cursor-pointer"><i data-lucide="trash-2" class="size-4"></i></button>
            </div>
        </div>
        @empty
        <div class="px-4 py-16 text-center text-secondary">
            <div class="flex flex-col items-center gap-3">
                <i data-lucide="inbox" class="size-10 text-border"></i>
                <p class="font-medium text-sm">Belum ada mata pelajaran.</p>
            </div>
        </div>
        @endforelse
    </div>

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</div>