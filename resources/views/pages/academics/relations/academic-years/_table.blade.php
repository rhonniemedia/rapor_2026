{{--
    File: resources/views/pages/academics/relations/academic-years/_table.blade.php
    Tabel Tahun Ajaran (DATA ASLI, hanya baca).

    Variabel: $academicYears (LengthAwarePaginator<App\Models\Core\AcademicYear>) dari
    AcademicYear::forList()->paginate(..., ['*'], 'ay_page'). Tombol Edit/Hapus masih placeholder (belum ada route).
--}}
@php
$academicYears = $academicYears ?? collect();
$emptyText = request('ay_search') ? 'Tidak ada tahun ajaran yang cocok.' : 'Belum ada tahun ajaran.';

// Kunci = AcademicYear::display_status
$statusMap = [
'active' => ['label' => 'Aktif', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
'planned' => ['label' => 'Direncanakan', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
'inactive' => ['label' => 'Nonaktif', 'badge' => 'bg-slate-100 text-slate-600 border-slate-200', 'dot' => 'bg-slate-400'],
'archived' => ['label' => 'Diarsipkan', 'badge' => 'bg-slate-50 text-slate-500 border-slate-200', 'dot' => 'bg-slate-300'],
];
@endphp

<div id="academic-years-container">

    {{-- ============ 1. DESKTOP TABLE (lg ke atas) ============ --}}
    <x-ui.table>
        <thead>
            <tr class="border-b border-border">
                <x-ui.table.th width="w-[25%]" sub="Nama | Periode">Tahun Ajaran</x-ui.table.th>
                <x-ui.table.th width="w-[15%]" sub="Status | Semester">Status</x-ui.table.th>
                <x-ui.table.th width="w-[25%]" sub="Terpasang">Kurikulum</x-ui.table.th>
                <x-ui.table.th width="w-[25%]" sub="Kelas | Siswa">Pemakaian</x-ui.table.th>
                <x-ui.table.th width="w-[10%]" sub="Kelola">Aksi</x-ui.table.th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border border-b border-border">
            @forelse ($academicYears as $ay)
            @php
            $st = $statusMap[$ay->display_status];
            $semester = $ay->active_semester;
            $period = $ay->start_date->translatedFormat('d M Y') . ' – ' . $ay->end_date->translatedFormat('d M Y');
            $yearCurricula = $ay->academicYearCurricula
            ->filter(fn ($row) => $row->curriculum)
            ->sortBy(fn ($row) => $row->curriculum->name);
            @endphp
            <tr id="row-academic-year-{{ $ay->id }}" data-ay-name="{{ $ay->name }}" class="group transition-colors hover:bg-muted/40">
                {{-- Kolom 1: Tahun Ajaran --}}
                <x-ui.table.td>
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <i data-lucide="calendar-range" class="size-5 text-primary"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-foreground text-sm truncate">{{ $ay->name }}</div>
                            <div class="text-xs text-secondary mt-1 truncate" title="{{ $period }}">{{ $period }}</div>
                        </div>
                    </div>
                </x-ui.table.td>

                {{-- Kolom 2: Status + semester aktif --}}
                <x-ui.table.td>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $st['badge'] }}">
                        <span class="size-1.5 rounded-full {{ $st['dot'] }}"></span>{{ $st['label'] }}
                    </span>
                    @if ($semester)
                    <div class="text-xs text-secondary mt-1.5 truncate">Semester {{ $semester->label }}</div>
                    @endif
                </x-ui.table.td>

                {{-- Kolom 3: Kurikulum --}}
                <x-ui.table.td>
                    @if ($yearCurricula->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($yearCurricula as $row)
                        @if ($row->status === 'active')
                        <span class="inline-flex items-center rounded-md bg-primary/10 text-primary px-2 py-0.5 text-xs font-semibold">{{ $row->curriculum->name }}</span>
                        @else
                        <span class="inline-flex items-center rounded-md bg-slate-100 text-slate-500 px-2 py-0.5 text-xs font-semibold" title="Tidak aktif di tahun ajaran ini">{{ $row->curriculum->name }}</span>
                        @endif
                        @endforeach
                    </div>
                    @else
                    <span class="text-xs text-secondary">Belum ada kurikulum</span>
                    @endif
                </x-ui.table.td>

                {{-- Kolom 4: Pemakaian --}}
                <x-ui.table.td>
                    <div class="text-sm font-medium text-foreground">{{ (int) $ay->classes_count }} kelas</div>
                    <div class="text-xs text-secondary mt-1 truncate">{{ number_format((int) $ay->students_count) }} siswa</div>
                </x-ui.table.td>

                {{-- Kolom 5: Aksi (placeholder, belum ada route) --}}
                <x-ui.table.td>
                    <div class="flex items-center gap-1.5">
                        <button type="button" title="Edit" aria-label="Edit"
                            class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-primary hover:text-primary transition-colors cursor-pointer">
                            <i data-lucide="pencil" class="size-4"></i>
                        </button>
                        <button type="button" title="Hapus" aria-label="Hapus"
                            class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-error hover:text-error transition-colors cursor-pointer">
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
                        <p class="font-medium text-sm">{{ $emptyText }}</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- ============ 2. MOBILE CARDS (di bawah lg) ============ --}}
    <div class="lg:hidden divide-y divide-border border-y border-border -mx-5 mt-2 mb-4">
        @forelse ($academicYears as $ay)
        @php
        $st = $statusMap[$ay->display_status];
        $semester = $ay->active_semester;
        $period = $ay->start_date->translatedFormat('d M Y') . ' – ' . $ay->end_date->translatedFormat('d M Y');
        $yearCurricula = $ay->academicYearCurricula
        ->filter(fn ($row) => $row->curriculum)
        ->sortBy(fn ($row) => $row->curriculum->name);
        @endphp
        <div id="card-academic-year-{{ $ay->id }}" data-ay-name="{{ $ay->name }}" class="px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i data-lucide="calendar-range" class="size-5 text-primary"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-foreground text-sm truncate">{{ $ay->name }}</div>
                        <p class="text-xs text-secondary mt-1 truncate">{{ $period }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $st['badge'] }}">{{ $st['label'] }}</span>
            </div>

            <div class="mt-3 border-y border-border divide-y divide-border text-xs">
                @if ($semester)
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="calendar-check" class="size-3.5 text-secondary/50"></i>Semester aktif</p>
                    <p class="font-medium text-foreground">{{ $semester->label }}</p>
                </div>
                @endif
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="library-big" class="size-3.5 text-secondary/50"></i>Kurikulum</p>
                    <p class="font-medium text-foreground text-right">{{ $yearCurricula->isNotEmpty() ? $yearCurricula->map(fn ($row) => $row->curriculum->name)->implode(', ') : '-' }}</p>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="school" class="size-3.5 text-secondary/50"></i>Kelas</p>
                    <p class="font-medium text-foreground">{{ (int) $ay->classes_count }} kelas</p>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="users" class="size-3.5 text-secondary/50"></i>Siswa</p>
                    <p class="font-medium text-foreground">{{ number_format((int) $ay->students_count) }} siswa</p>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-end gap-1.5">
                <button type="button" title="Edit" aria-label="Edit"
                    class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-primary hover:text-primary transition-colors cursor-pointer">
                    <i data-lucide="pencil" class="size-4"></i>
                </button>
                <button type="button" title="Hapus" aria-label="Hapus"
                    class="size-9 inline-flex items-center justify-center rounded-lg border border-border bg-white text-secondary hover:border-error hover:text-error transition-colors cursor-pointer">
                    <i data-lucide="trash-2" class="size-4"></i>
                </button>
            </div>
        </div>
        @empty
        <div class="px-4 py-16 text-center text-secondary">
            <div class="flex flex-col items-center gap-3">
                <i data-lucide="inbox" class="size-10 text-border"></i>
                <p class="font-medium text-sm">{{ $emptyText }}</p>
            </div>
        </div>
        @endforelse
    </div>

    {{-- Pagination (param halaman: ay_page, terpisah dari halaman kurikulum) --}}
    @if ($academicYears instanceof \Illuminate\Contracts\Pagination\Paginator)
    <x-ui.pagination :paginator="$academicYears" hxTarget="#academic-years-container" />
    @endif

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</div>