{{-- Tabel Kelompok Mata Pelajaran (DATA DUMMY). Ganti $groups dengan data controller nanti. --}}
@php
$groups = $groups ?? collect([
['id' => 1, 'name' => 'Kelompok Umum', 'code' => 'UMUM', 'order' => 1, 'description' => 'Mata pelajaran wajib untuk seluruh peserta didik', 'subjects' => 6],
['id' => 2, 'name' => 'Kelompok Peminatan', 'code' => 'MINAT', 'order' => 2, 'description' => 'Mata pelajaran sesuai jurusan / peminatan', 'subjects' => 3],
['id' => 3, 'name' => 'Muatan Lokal', 'code' => 'MULOK', 'order' => 3, 'description' => 'Mata pelajaran berbasis kearifan daerah', 'subjects' => 1],
['id' => 4, 'name' => 'Pengembangan Diri', 'code' => 'PDIRI', 'order' => 4, 'description' => 'Kegiatan ekstrakurikuler dan pembiasaan', 'subjects' => 0],
]);
@endphp

<div id="groups-container">

    {{-- ============ 1. DESKTOP TABLE ============ --}}
    <x-ui.table>
        <thead>
            <tr class="border-b border-border">
                <x-ui.table.th width="w-[40%]" sub="Nama | Kode">Kelompok</x-ui.table.th>
                <x-ui.table.th width="w-[15%]" sub="Di rapor">Urutan</x-ui.table.th>
                <x-ui.table.th width="w-[35%]" sub="Jumlah">Pemakaian</x-ui.table.th>
                <x-ui.table.th width="w-[10%]" sub="Kelola">Aksi</x-ui.table.th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border border-b border-border">
            @forelse ($groups as $g)
            <tr id="row-group-{{ $g['id'] }}" data-search="{{ $g['name'] }} {{ $g['code'] }}" class="group transition-colors hover:bg-muted/40">
                <x-ui.table.td>
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <i data-lucide="layers" class="size-5 text-primary"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-foreground text-sm truncate">{{ $g['name'] }}</div>
                            <div class="flex items-center gap-1.5 text-xs text-secondary mt-1 min-w-0">
                                <span class="font-mono font-semibold text-foreground/80">{{ $g['code'] }}</span>
                                <span class="text-secondary/50">•</span>
                                <span class="truncate" title="{{ $g['description'] }}">{{ $g['description'] }}</span>
                            </div>
                        </div>
                    </div>
                </x-ui.table.td>

                <x-ui.table.td>
                    <span class="inline-flex items-center justify-center size-8 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 text-xs font-bold">{{ $g['order'] }}</span>
                </x-ui.table.td>

                <x-ui.table.td>
                    <div class="text-sm font-medium text-foreground">{{ $g['subjects'] }} mapel</div>
                    <div class="text-xs text-secondary mt-1 truncate">{{ $g['subjects'] > 0 ? 'terdaftar di kelompok ini' : 'belum ada mapel' }}</div>
                </x-ui.table.td>

                <x-ui.table.td>
                    <div class="flex items-center gap-1.5">
                        <button type="button" title="Edit" aria-label="Edit" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-primary hover:text-primary transition-colors cursor-pointer"><i data-lucide="pencil" class="size-4"></i></button>
                        <button type="button" title="Hapus" aria-label="Hapus" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-error hover:text-error transition-colors cursor-pointer"><i data-lucide="trash-2" class="size-4"></i></button>
                    </div>
                </x-ui.table.td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-16 text-center text-secondary">
                    <div class="flex flex-col items-center gap-3">
                        <i data-lucide="inbox" class="size-10 text-border"></i>
                        <p class="font-medium text-sm">Belum ada kelompok mata pelajaran.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- ============ 2. MOBILE CARDS ============ --}}
    <div class="lg:hidden divide-y divide-border border-y border-border -mx-5 mt-2 mb-4">
        @forelse ($groups as $g)
        <div id="card-group-{{ $g['id'] }}" data-search="{{ $g['name'] }} {{ $g['code'] }}" class="px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i data-lucide="layers" class="size-5 text-primary"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-foreground text-sm truncate">{{ $g['name'] }}</div>
                        <p class="text-xs text-secondary mt-1 truncate font-mono">{{ $g['code'] }}</p>
                    </div>
                </div>
                <span class="shrink-0 inline-flex items-center justify-center size-7 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 text-xs font-bold">{{ $g['order'] }}</span>
            </div>

            <div class="mt-3 border-y border-border divide-y divide-border text-xs">
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="info" class="size-3.5 text-secondary/50"></i>Deskripsi</p>
                    <p class="font-medium text-foreground text-right">{{ $g['description'] }}</p>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="book-open" class="size-3.5 text-secondary/50"></i>Mapel</p>
                    <p class="font-medium text-foreground">{{ $g['subjects'] }} mapel</p>
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
                <p class="font-medium text-sm">Belum ada kelompok mata pelajaran.</p>
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