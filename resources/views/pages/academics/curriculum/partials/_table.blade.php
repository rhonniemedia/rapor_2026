<div id="curricula-container"
    hx-get="{{ $listUrl ?? request()->fullUrl() }}"
    hx-trigger="refreshCurriculaList from:body"
    hx-select="#curricula-container"
    hx-swap="outerHTML"
    hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>

    {{-- ============ 1. DESKTOP TABLE (lg ke atas) ============ --}}
    <x-ui.table>
        <thead>
            <tr class="border-b border-border">
                <x-ui.table.th width="w-[30%]" sub="Nama | Kode">Kurikulum</x-ui.table.th>
                <x-ui.table.th width="w-[30%]" sub="Aktif / Arsip">Status</x-ui.table.th>
                <x-ui.table.th width="w-[30%]" sub="Mapel | Tahun Ajaran">Pemakaian</x-ui.table.th>
                <x-ui.table.th width="w-[10%]" sub="Kelola">Aksi</x-ui.table.th>
            </tr>
        </thead>
        <tbody class="divide-y divide-border border-b border-border">
            @forelse ($curricula as $c)
            <tr id="row-curriculum-{{ $c->id }}" class="group transition-colors hover:bg-muted/40">
                {{-- Kolom 1: Kurikulum --}}
                <x-ui.table.td>
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <i data-lucide="library-big" class="size-5 text-primary"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <x-ui.table.text :text="$c->name" class="font-semibold text-foreground text-sm" />
                            <div class="flex items-center gap-1.5 text-xs text-secondary mt-1 min-w-0">
                                <span class="font-mono font-semibold text-foreground/80">{{ $c->code }}</span>
                                @if ($c->description)
                                <span class="text-secondary/50">•</span>
                                <span class="truncate" title="{{ $c->description }}">{{ $c->description }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </x-ui.table.td>

                {{-- Kolom 2: Status --}}
                <x-ui.table.td>
                    @if ($c->isActive())
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 text-xs font-semibold">
                        <span class="size-1.5 rounded-full bg-emerald-500"></span>Aktif
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-1 text-xs font-semibold">
                        <span class="size-1.5 rounded-full bg-slate-400"></span>Diarsipkan
                    </span>
                    @endif
                </x-ui.table.td>

                {{-- Kolom 3: Pemakaian --}}
                <x-ui.table.td>
                    <div class="text-sm font-medium text-foreground">{{ $c->curriculum_subjects_count }} mapel</div>
                    <div class="text-xs text-secondary mt-1 truncate">dipakai di {{ $c->academic_year_curricula_count }} tahun ajaran</div>
                </x-ui.table.td>

                {{-- Kolom 4: Aksi --}}
                <x-ui.table.td>
                    @include('pages.academics.curriculum.partials._actions', ['c' => $c])
                </x-ui.table.td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-16 text-center text-secondary">
                    <div class="flex flex-col items-center gap-3">
                        <i data-lucide="inbox" class="size-10 text-border"></i>
                        <p class="font-medium text-sm">Tidak ada kurikulum yang cocok.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    {{-- ============ 2. MOBILE CARDS (di bawah lg) ============ --}}
    <div class="lg:hidden divide-y divide-border border-y border-border -mx-5 mt-2 mb-4">
        @forelse ($curricula as $c)
        <div id="card-curriculum-{{ $c->id }}" class="px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i data-lucide="library-big" class="size-5 text-primary"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="font-semibold text-foreground text-sm truncate">{{ $c->name }}</div>
                        <p class="text-xs text-secondary mt-1 truncate font-mono">{{ $c->code }}</p>
                    </div>
                </div>
                @if ($c->isActive())
                <span class="shrink-0 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 text-xs font-semibold">Aktif</span>
                @else
                <span class="shrink-0 rounded-full bg-slate-100 text-slate-600 border border-slate-200 px-2.5 py-1 text-xs font-semibold">Diarsipkan</span>
                @endif
            </div>

            <div class="mt-3 border-y border-border divide-y divide-border text-xs">
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="book-open" class="size-3.5 text-secondary/50"></i>Mapel</p>
                    <p class="font-medium text-foreground">{{ $c->curriculum_subjects_count }} mapel</p>
                </div>
                <div class="flex items-center justify-between gap-3 py-2.5">
                    <p class="text-secondary flex items-center gap-1.5"><i data-lucide="calendar-range" class="size-3.5 text-secondary/50"></i>Tahun ajaran</p>
                    <p class="font-medium text-foreground">{{ $c->academic_year_curricula_count }} tahun ajaran</p>
                </div>
            </div>

            <div class="mt-3 flex items-center justify-end">
                @include('pages.academics.curriculum.partials._actions', ['c' => $c])
            </div>
        </div>
        @empty
        <div class="px-4 py-16 text-center text-secondary">
            <div class="flex flex-col items-center gap-3">
                <i data-lucide="inbox" class="size-10 text-border"></i>
                <p class="font-medium text-sm">Tidak ada kurikulum yang cocok.</p>
            </div>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <x-ui.pagination :paginator="$curricula" hxTarget="#curricula-container" />

    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</div>