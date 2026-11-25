@extends('layouts.main.app')

@section('title', 'Relasi Akademik')
@section('page_title', 'Relasi Akademik')
@section('page_subtitle', 'Kelola master kurikulum dan tahun ajaran yang dipakai pada rapor')

@section('content')
<div class="px-4 py-6 sm:px-6 md:p-8"
    x-data="{
        activeTab: (['kurikulum', 'tahun-ajaran'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'kurikulum'),
        setTab(tab) {
            this.activeTab = tab;
            history.replaceState(null, '', location.pathname + location.search + '#' + tab);
            this.$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
        },
        filterModalOpen: false,
        isFilterActive: {{ !empty($filterStatus) ? 'true' : 'false' }},
        checkFilterStatus() {
            const status = document.querySelector('[name=filter_status]')?.value || '';
            this.isFilterActive = (status !== '');
        },
        closeOpenActionMenus() {
            window.dispatchEvent(new CustomEvent('close-action-menus'));
        }
    }"
    @htmx:after-request.document="checkFilterStatus()"
    @htmx:before-swap.document="closeOpenActionMenus()">

    {{-- 1. PAGE HEADER --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div class="min-w-0 max-w-2xl">
            <h1 class="text-2xl md:text-3xl font-bold text-foreground tracking-tight mb-1">Relasi Akademik</h1>
            <p class="text-xs sm:text-sm text-secondary leading-relaxed">
                Kelola master kurikulum dan tahun ajaran yang dipakai pada rapor.
            </p>
        </div>

        <div class="grid grid-cols-2 sm:flex sm:items-center gap-2.5 shrink-0">
            <a href="{{ route('rapor.curricula.index') }}"
                title="Segarkan halaman"
                onclick="document.getElementById('refresh-icon')?.classList.add('animate-spin');"
                class="inline-flex items-center justify-center gap-2 h-10 sm:h-11 px-4 rounded-full border border-border bg-white text-foreground font-semibold text-xs sm:text-sm hover:border-primary hover:text-primary transition-all cursor-pointer whitespace-nowrap">
                <i id="refresh-icon" data-lucide="refresh-cw" class="size-4 shrink-0"></i>
                <span>Segarkan</span>
            </a>

            {{-- Tombol tambah: Kurikulum --}}
            <button type="button"
                x-show="activeTab === 'kurikulum'"
                hx-get="{{ route('rapor.curricula.create') }}"
                hx-target="#modal-container"
                hx-swap="innerHTML"
                class="inline-flex items-center justify-center gap-2 h-10 sm:h-11 px-4 sm:px-5 rounded-full bg-primary text-white font-semibold text-xs sm:text-sm hover:bg-primary/90 shadow-sm shadow-primary/25 transition-all cursor-pointer whitespace-nowrap">
                <i data-lucide="plus" class="size-4 shrink-0"></i>
                <span>Tambah Kurikulum</span>
            </button>

            {{-- Tombol tambah: Tahun Ajaran (dummy, belum ada route) --}}
            <button type="button"
                x-show="activeTab === 'tahun-ajaran'"
                x-cloak
                class="inline-flex items-center justify-center gap-2 h-10 sm:h-11 px-4 sm:px-5 rounded-full bg-primary text-white font-semibold text-xs sm:text-sm hover:bg-primary/90 shadow-sm shadow-primary/25 transition-all cursor-pointer whitespace-nowrap">
                <i data-lucide="plus" class="size-4 shrink-0"></i>
                <span>Tambah Tahun Ajaran</span>
            </button>
        </div>
    </div>

    {{-- 2. TAB NAVIGASI --}}
    <div class="mb-5 border-b border-border overflow-x-auto" role="tablist">
        <div class="flex items-center gap-1 min-w-max">
            <button type="button" role="tab"
                @click="setTab('kurikulum')"
                :aria-selected="activeTab === 'kurikulum'"
                :class="activeTab === 'kurikulum' ? 'border-primary text-primary' : 'border-transparent text-secondary hover:text-foreground'"
                class="relative -mb-px inline-flex items-center gap-2 px-4 py-3 border-b-2 text-xs sm:text-sm font-semibold transition-colors cursor-pointer focus:outline-none whitespace-nowrap">
                <i data-lucide="swatch-book" class="size-4 shrink-0"></i>
                <span>Kurikulum</span>
            </button>

            <button type="button" role="tab"
                @click="setTab('tahun-ajaran')"
                :aria-selected="activeTab === 'tahun-ajaran'"
                :class="activeTab === 'tahun-ajaran' ? 'border-primary text-primary' : 'border-transparent text-secondary hover:text-foreground'"
                class="relative -mb-px inline-flex items-center gap-2 px-4 py-3 border-b-2 text-xs sm:text-sm font-semibold transition-colors cursor-pointer focus:outline-none whitespace-nowrap">
                <i data-lucide="calendar-range" class="size-4 shrink-0"></i>
                <span>Tahun Ajaran</span>
            </button>
        </div>
    </div>

    {{-- ============ TAB 1: KURIKULUM ============ --}}
    <div x-show="activeTab === 'kurikulum'" role="tabpanel">
        <div class="bg-white rounded-2xl border border-border p-4 sm:p-5 md:p-6 shadow-2xs">

            {{-- Header Tabel --}}
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
                <div class="flex items-start sm:items-center gap-3 sm:gap-3.5 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5 sm:mt-0">
                        <i data-lucide="swatch-book" class="size-5"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base sm:text-lg font-bold text-foreground leading-snug">Daftar Kurikulum</h2>
                        <p class="text-xs sm:text-sm text-secondary mt-0.5 leading-relaxed">
                            Cari berdasarkan nama atau kode, atau filter berdasarkan status.
                        </p>
                    </div>
                </div>

                {{-- Bagian Kanan: Pencarian & Filter --}}
                <div class="flex items-center gap-2 w-full sm:w-auto shrink-0" x-data="{ searchQuery: @js($search ?? '') }">
                    <div class="relative flex-1 sm:w-60 xl:w-72 flex items-center">
                        <i data-lucide="search"
                            class="absolute left-3.5 size-4 transition-colors pointer-events-none"
                            :class="searchQuery.length > 0 ? 'text-primary' : 'text-secondary'"></i>

                        <input
                            x-ref="searchInput"
                            type="text"
                            name="search"
                            x-model="searchQuery"
                            placeholder="Cari kurikulum..."
                            autocomplete="off"
                            hx-get="{{ route('rapor.curricula.index') }}"
                            hx-trigger="keyup changed delay:400ms, search"
                            hx-target="#curricula-container"
                            hx-select="#curricula-container"
                            hx-swap="outerHTML"
                            hx-include="#curricula-filter-form"
                            hx-push-url="true"
                            class="h-10 sm:h-11 w-full bg-white border rounded-xl pl-10 pr-9 text-xs sm:text-sm focus:outline-none focus:border-primary transition-all"
                            :class="searchQuery.length > 0 ? 'border-primary/60 text-foreground font-medium' : 'border-border text-foreground'">

                        <button
                            type="button"
                            x-show="searchQuery.length > 0"
                            x-cloak
                            title="Hapus pencarian"
                            aria-label="Hapus pencarian"
                            @click="searchQuery = ''; $nextTick(() => $refs.searchInput.dispatchEvent(new Event('search')))"
                            class="absolute right-2.5 flex items-center justify-center size-5 rounded-full bg-slate-100 hover:bg-error/10 text-secondary hover:text-error transition-colors cursor-pointer focus:outline-none">
                            <i data-lucide="x" class="size-3"></i>
                        </button>
                    </div>

                    {{-- Tombol Filter --}}
                    <button
                        type="button"
                        @click="filterModalOpen = true"
                        title="Filter kurikulum"
                        aria-label="Filter kurikulum"
                        class="relative flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-xl border border-border bg-white hover:bg-muted/60 hover:border-primary/40 transition-colors cursor-pointer focus:outline-none">
                        <i data-lucide="filter" class="size-4 text-secondary"></i>
                        <span x-show="isFilterActive" x-cloak class="absolute -top-1 -right-1 flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-primary border-2 border-white"></span>
                        </span>
                    </button>
                </div>
            </div>

            @include('pages.academics.relations.curriculum._table', compact('curricula'))
        </div>
    </div>

    {{-- ============ TAB 2: TAHUN AJARAN (DUMMY) ============ --}}
    <div x-show="activeTab === 'tahun-ajaran'" x-cloak role="tabpanel">
        <div class="bg-white rounded-2xl border border-border p-4 sm:p-5 md:p-6 shadow-2xs">

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
                <div class="flex items-start sm:items-center gap-3 sm:gap-3.5 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5 sm:mt-0">
                        <i data-lucide="calendar-range" class="size-5"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base sm:text-lg font-bold text-foreground leading-snug">Daftar Tahun Ajaran</h2>
                        <p class="text-xs sm:text-sm text-secondary mt-0.5 leading-relaxed">
                            Tahun ajaran beserta kurikulum yang diaktifkan pada masing-masing periode.
                        </p>
                    </div>
                </div>

                {{-- Pencarian server-side. Nama param ay_search agar tidak bentrok dengan "search" milik kurikulum --}}
                <div class="relative w-full sm:w-60 xl:w-72 flex items-center shrink-0" x-data="{ ayQuery: @js($ayearSearch ?? '') }">
                    <i data-lucide="search"
                        class="absolute left-3.5 size-4 transition-colors pointer-events-none"
                        :class="ayQuery.length > 0 ? 'text-primary' : 'text-secondary'"></i>

                    <input
                        x-ref="ayInput"
                        type="text"
                        name="ay_search"
                        x-model="ayQuery"
                        placeholder="Cari tahun ajaran..."
                        autocomplete="off"
                        hx-get="{{ route('rapor.curricula.index') }}"
                        hx-trigger="keyup changed delay:400ms, search"
                        hx-target="#academic-years-container"
                        hx-select="#academic-years-container"
                        hx-swap="outerHTML"
                        hx-include="#curricula-filter-form, [name=search]"
                        hx-push-url="true"
                        class="h-10 sm:h-11 w-full bg-white border rounded-xl pl-10 pr-9 text-xs sm:text-sm focus:outline-none focus:border-primary transition-all"
                        :class="ayQuery.length > 0 ? 'border-primary/60 text-foreground font-medium' : 'border-border text-foreground'">

                    <button
                        type="button"
                        x-show="ayQuery.length > 0"
                        x-cloak
                        title="Hapus pencarian"
                        aria-label="Hapus pencarian"
                        @click="ayQuery = ''; $nextTick(() => $refs.ayInput.dispatchEvent(new Event('search')))"
                        class="absolute right-2.5 flex items-center justify-center size-5 rounded-full bg-slate-100 hover:bg-error/10 text-secondary hover:text-error transition-colors cursor-pointer focus:outline-none">
                        <i data-lucide="x" class="size-3"></i>
                    </button>
                </div>
            </div>

            @include('pages.academics.relations.academic-years._table', compact('academicYears'))
        </div>
    </div>

    @include('pages.academics.relations.curriculum._filter-modal', [
    'filterStatus' => $filterStatus ?? '',
    'statusOptions' => $statusOptions ?? [],
    ])

    {{-- Tempat modal tambah / edit / hapus dimuat lewat HTMX --}}
    <div id="modal-container"></div>
</div>
@endsection