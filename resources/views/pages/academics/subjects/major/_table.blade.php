{{-- Tabel Relasi Jurusan - Mata Pelajaran (DATA DUMMY). Ganti $majorSubjects dengan data controller nanti. --}}
@php
$majorSubjects = $majorSubjects ?? collect([
['id' => 1, 'major' => 'IPA', 'code' => 'IPA', 'subject' => 'Fisika', 'level' => 'Kelas XI', 'type' => 'wajib'],
['id' => 2, 'major' => 'IPA', 'code' => 'IPA', 'subject' => 'Kimia', 'level' => 'Kelas XI', 'type' => 'wajib'],
['id' => 3, 'major' => 'IPA', 'code' => 'IPA', 'subject' => 'Biologi', 'level' => 'Kelas XI', 'type' => 'pilihan'],
['id' => 4, 'major' => 'IPS', 'code' => 'IPS', 'subject' => 'Ekonomi', 'level' => 'Kelas XI', 'type' => 'wajib'],
['id' => 5, 'major' => 'IPS', 'code' => 'IPS', 'subject' => 'Sosiologi', 'level' => 'Kelas XI', 'type' => 'wajib'],
['id' => 6, 'major' => 'IPS', 'code' => 'IPS', 'subject' => 'Geografi', 'level' => 'Kelas XII', 'type' => 'pilihan'],
['id' => 7, 'major' => 'Bahasa', 'code' => 'BHS', 'subject' => 'Bahasa Inggris Lanjut', 'level' => 'Kelas XI', 'type' => 'wajib'],
['id' => 8, 'major' => 'Bahasa', 'code' => 'BHS', 'subject' => 'Antropologi', 'level' => 'Kelas XII', 'type' => 'pilihan'],
]);

$typeMap = [
'wajib' => ['label' => 'Wajib', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
'pilihan' => ['label' => 'Pilihan', 'badge' => 'bg-sky-50 text-sky-700 border-sky-200', 'dot' => 'bg-sky-500'],
];
@endphp

<div id="major-subjects-container">

    {{-- ============ 1. DESKTOP TABLE ============ --}}
    <x-ui.table>
        <thead>
            <tr class="border-b border-border">
                <x-ui.table.th width="w-[25%]" sub="Nama | Kode">Jurusan</x-ui.table.th>
                <x-ui.table.th width="w-[30%]" sub="Berlaku">Mata Pelajaran</x-ui.table.th>
                <x-ui.table.th width="w-[15%]" sub="Kelas">Tingkat</x-ui.table.th>
                <x-ui.table.th width="w-[20%]" sub="Wajib | Pilihan">Sifat</x-ui.table.th>
                <x-ui.table.th width="w-[10%]" sub="Kelola">Aksi</x-ui.table.th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border border-b border-border">
            @forelse ($majorSubjects as $r)
            @php $t = $typeMap[$r['type']]; @endphp
            <tr id="row-major-subject-{{ $r['id'] }}" data-search="{{ $r['major'] }} {{ $r['code'] }} {{ $r['subject'] }} {{ $r['level'] }}" class="group transition-colors hover:bg-muted/40">
                <x-ui.table.td>
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <i data-lucide="graduation-cap" class="size-5 text-primary"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-foreground text-sm truncate">{{ $r['major'] }}</div>
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
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $t['badge'] }}">
                        <span class="size-1.5 rounded-full {{ $t['dot'] }}"></span>{{ $t['label'] }}
                    </span>
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
                        <p class="font-medium text-sm">Belum ada relasi jurusan.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- ============ 2. MOBILE CARDS ============ --}}
    <div class="lg:hidden divide-y divide-border border-y border-border -mx-5 mt-2 mb-4">
        @forelse ($majorSubjects as $r)
        @php $t = $typeMap[$r['type']]; @endphp
        <div id="card-major-subject-{{ $r['id'] }}" data-search="{{ $r['major'] }} {{ $r['code'] }} {{ $r['subject'] }} {{ $r['level'] }}" class="px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i data-lucide="graduation-cap" class="size-5 text-primary"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-foreground text-sm truncate">{{ $r['major'] }}</div>
                        <p class="text-xs text-secondary mt-1 truncate font-mono">{{ $r['code'] }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $t['badge'] }}">{{ $t['label'] }}</span>
            </div>

            <div class="mt-3 border-y border-border divide-y divide-border text-xs">
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="book-open" class="size-3.5 text-secondary/50"></i>Mapel</p>
                    <p class="font-medium text-foreground">{{ $r['subject'] }}</p>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="school" class="size-3.5 text-secondary/50"></i>Tingkat</p>
                    <p class="font-medium text-foreground">{{ $r['level'] }}</p>
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
                <p class="font-medium text-sm">Belum ada relasi jurusan.</p>
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