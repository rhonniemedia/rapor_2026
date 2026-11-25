{{-- Tabel Relasi Kurikulum - Mata Pelajaran (DATA DUMMY). Ganti $curriculumSubjects dengan data controller nanti. --}}
@php
$curriculumSubjects = $curriculumSubjects ?? collect([
['id' => 1, 'curriculum' => 'Kurikulum Merdeka', 'code' => 'KM', 'subject' => 'Matematika', 'level' => 'Kelas X', 'hours' => 4],
['id' => 2, 'curriculum' => 'Kurikulum Merdeka', 'code' => 'KM', 'subject' => 'Bahasa Indonesia', 'level' => 'Kelas X', 'hours' => 4],
['id' => 3, 'curriculum' => 'Kurikulum Merdeka', 'code' => 'KM', 'subject' => 'Bahasa Inggris', 'level' => 'Kelas X', 'hours' => 3],
['id' => 4, 'curriculum' => 'Kurikulum Merdeka', 'code' => 'KM', 'subject' => 'Fisika', 'level' => 'Kelas XI', 'hours' => 4],
['id' => 5, 'curriculum' => 'Kurikulum Merdeka', 'code' => 'KM', 'subject' => 'Kimia', 'level' => 'Kelas XI', 'hours' => 4],
['id' => 6, 'curriculum' => 'Kurikulum Merdeka', 'code' => 'KM', 'subject' => 'Bahasa Daerah', 'level' => 'Kelas XII', 'hours' => 2],
['id' => 7, 'curriculum' => 'Kurikulum 2013', 'code' => 'K13', 'subject' => 'Matematika', 'level' => 'Kelas X', 'hours' => 4],
['id' => 8, 'curriculum' => 'Kurikulum 2013', 'code' => 'K13', 'subject' => 'Pendidikan Pancasila','level' => 'Kelas XI', 'hours' => 2],
]);
@endphp

<div id="curriculum-subjects-container">

    {{-- ============ 1. DESKTOP TABLE ============ --}}
    <x-ui.table>
        <thead>
            <tr class="border-b border-border">
                <x-ui.table.th width="w-[30%]" sub="Nama | Kode">Kurikulum</x-ui.table.th>
                <x-ui.table.th width="w-[30%]" sub="Dipasang">Mata Pelajaran</x-ui.table.th>
                <x-ui.table.th width="w-[15%]" sub="Kelas">Tingkat</x-ui.table.th>
                <x-ui.table.th width="w-[15%]" sub="Per minggu">Alokasi</x-ui.table.th>
                <x-ui.table.th width="w-[10%]" sub="Kelola">Aksi</x-ui.table.th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border border-b border-border">
            @forelse ($curriculumSubjects as $r)
            <tr id="row-curriculum-subject-{{ $r['id'] }}" data-search="{{ $r['curriculum'] }} {{ $r['code'] }} {{ $r['subject'] }} {{ $r['level'] }}" class="group transition-colors hover:bg-muted/40">
                <x-ui.table.td>
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <i data-lucide="swatch-book" class="size-5 text-primary"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-foreground text-sm truncate">{{ $r['curriculum'] }}</div>
                            <div class="text-xs text-secondary mt-1 font-mono font-semibold text-foreground/80">{{ $r['code'] }}</div>
                        </div>
                    </div>
                </x-ui.table.td>

                <x-ui.table.td>
                    <div class="flex items-center gap-2 min-w-0">
                        <i data-lucide="link-2" class="size-4 text-secondary/50 shrink-0"></i>
                        <span class="text-sm font-medium text-foreground truncate">{{ $r['subject'] }}</span>
                    </div>
                </x-ui.table.td>

                <x-ui.table.td>
                    <span class="inline-flex items-center rounded-md bg-primary/10 text-primary px-2 py-0.5 text-xs font-semibold">{{ $r['level'] }}</span>
                </x-ui.table.td>

                <x-ui.table.td>
                    <div class="text-sm font-medium text-foreground">{{ $r['hours'] }} JP</div>
                    <div class="text-xs text-secondary mt-1 truncate">jam pelajaran</div>
                </x-ui.table.td>

                <x-ui.table.td>
                    <div class="flex items-center gap-1.5">
                        <button type="button" title="Edit" aria-label="Edit" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-primary hover:text-primary transition-colors cursor-pointer"><i data-lucide="pencil" class="size-4"></i></button>
                        <button type="button" title="Lepas relasi" aria-label="Lepas relasi" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-error hover:text-error transition-colors cursor-pointer"><i data-lucide="unlink" class="size-4"></i></button>
                    </div>
                </x-ui.table.td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-4 py-16 text-center text-secondary">
                    <div class="flex flex-col items-center gap-3">
                        <i data-lucide="inbox" class="size-10 text-border"></i>
                        <p class="font-medium text-sm">Belum ada relasi kurikulum.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- ============ 2. MOBILE CARDS ============ --}}
    <div class="lg:hidden divide-y divide-border border-y border-border -mx-5 mt-2 mb-4">
        @forelse ($curriculumSubjects as $r)
        <div id="card-curriculum-subject-{{ $r['id'] }}" data-search="{{ $r['curriculum'] }} {{ $r['code'] }} {{ $r['subject'] }} {{ $r['level'] }}" class="px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i data-lucide="swatch-book" class="size-5 text-primary"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-foreground text-sm truncate">{{ $r['curriculum'] }}</div>
                        <p class="text-xs text-secondary mt-1 truncate font-mono">{{ $r['code'] }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-md bg-primary/10 text-primary px-2 py-0.5 text-xs font-semibold">{{ $r['level'] }}</span>
            </div>

            <div class="mt-3 border-y border-border divide-y divide-border text-xs">
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="book-open" class="size-3.5 text-secondary/50"></i>Mapel</p>
                    <p class="font-medium text-foreground">{{ $r['subject'] }}</p>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="clock" class="size-3.5 text-secondary/50"></i>Alokasi</p>
                    <p class="font-medium text-foreground">{{ $r['hours'] }} JP / minggu</p>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-end gap-1.5">
                <button type="button" title="Edit" aria-label="Edit" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-primary hover:text-primary transition-colors cursor-pointer"><i data-lucide="pencil" class="size-4"></i></button>
                <button type="button" title="Lepas relasi" aria-label="Lepas relasi" class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-error hover:text-error transition-colors cursor-pointer"><i data-lucide="unlink" class="size-4"></i></button>
            </div>
        </div>
        @empty
        <div class="px-4 py-16 text-center text-secondary">
            <div class="flex flex-col items-center gap-3">
                <i data-lucide="inbox" class="size-10 text-border"></i>
                <p class="font-medium text-sm">Belum ada relasi kurikulum.</p>
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