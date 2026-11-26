@extends('layouts.main.app')

@section('title', 'Mata Pelajaran')
@section('page_title', 'Mata Pelajaran')
@section('page_subtitle', 'Kelola master mata pelajaran, kelompok, serta relasinya dengan kurikulum dan jurusan')

@php
$tabs = [
'subject' => ['label' => 'Mata Pelajaran', 'icon' => 'book-open', 'add' => 'Tambah Mata Pelajaran'],
'group' => ['label' => 'Kelompok Mapel', 'icon' => 'layers', 'add' => 'Tambah Kelompok'],
'curriculum' => ['label' => 'Relasi Kurikulum', 'icon' => 'swatch-book', 'add' => 'Tambah Relasi Kurikulum'],
'major' => ['label' => 'Relasi Jurusan', 'icon' => 'graduation-cap', 'add' => 'Tambah Relasi Jurusan'],
];

$cards = [
'subject' => ['title' => 'Daftar Mata Pelajaran', 'desc' => 'Master mata pelajaran umum dan agama beserta statusnya.', 'search' => 'Cari mata pelajaran, kode, atau agama...'],
'group' => ['title' => 'Kelompok Mata Pelajaran', 'desc' => 'Pengelompokan mata pelajaran yang tampil pada rapor.', 'search' => 'Cari kelompok...'],
'curriculum' => ['title' => 'Relasi Kurikulum', 'desc' => 'Mata pelajaran yang dipasang pada kurikulum, per tingkat kelas.', 'search' => 'Cari kurikulum atau mapel...'],
'major' => ['title' => 'Relasi Jurusan', 'desc' => 'Mata pelajaran yang berlaku untuk masing-masing jurusan.', 'search' => 'Cari jurusan atau mapel...'],
];
@endphp

@section('content')
<div class="px-4 py-6 sm:px-6 md:p-8"
    x-data="{
        activeTab: (['subject', 'group', 'curriculum', 'major'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'subject'),
        setTab(tab) {
            this.activeTab = tab;
            history.replaceState(null, '', location.pathname + location.search + '#' + tab);
            this.$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
        },
        filterModalOpen: false,
        isFilterActive: {{ (!empty($filterStatus) || !empty($filterCategory)) ? 'true' : 'false' }},
        checkFilterStatus() {
            const status = document.querySelector('[name=filter_status]')?.value || '';
            const category = document.querySelector('[name=filter_category]')?.value || '';
            this.isFilterActive = (status !== '' || category !== '');
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
            <h1 class="text-2xl md:text-3xl font-bold text-foreground tracking-tight mb-1">Mata Pelajaran</h1>
            <p class="text-xs sm:text-sm text-secondary leading-relaxed">
                Kelola master mata pelajaran, pengelompokannya, serta relasi dengan kurikulum dan jurusan.
            </p>
        </div>

        <div class="grid grid-cols-2 sm:flex sm:items-center gap-2.5 shrink-0">
            <a href="{{ route('academics.subjects.index') }}"
                title="Segarkan halaman"
                onclick="document.getElementById('refresh-icon')?.classList.add('animate-spin');"
                class="inline-flex items-center justify-center gap-2 h-10 sm:h-11 px-4 rounded-full border border-border bg-white text-foreground font-semibold text-xs sm:text-sm hover:border-primary hover:text-primary transition-all cursor-pointer whitespace-nowrap">
                <i id="refresh-icon" data-lucide="refresh-cw" class="size-4 shrink-0"></i>
                <span>Segarkan</span>
            </a>

            <button type="button"
                x-show="activeTab === 'subject'"
                hx-get="{{ route('academics.subjects.create') }}"
                hx-target="#modal-container"
                hx-swap="innerHTML"
                class="inline-flex items-center justify-center gap-2 h-10 sm:h-11 px-4 sm:px-5 rounded-full bg-primary text-white font-semibold text-xs sm:text-sm hover:bg-primary/90 shadow-sm shadow-primary/25 transition-all cursor-pointer whitespace-nowrap">
                <i data-lucide="plus" class="size-4 shrink-0"></i>
                <span>Tambah Mata Pelajaran</span>
            </button>

            @foreach ($tabs as $key => $tab)
            @if ($key !== 'subject')
            <button type="button"
                x-show="activeTab === '{{ $key }}'"
                x-cloak
                class="inline-flex items-center justify-center gap-2 h-10 sm:h-11 px-4 sm:px-5 rounded-full bg-primary text-white font-semibold text-xs sm:text-sm hover:bg-primary/90 shadow-sm shadow-primary/25 transition-all cursor-pointer whitespace-nowrap">
                <i data-lucide="plus" class="size-4 shrink-0"></i>
                <span>{{ $tab['add'] }}</span>
            </button>
            @endif
            @endforeach
        </div>
    </div>

    {{-- 2. TAB NAVIGASI --}}
    <div class="mb-5 border-b border-border overflow-x-auto" role="tablist">
        <div class="flex items-center gap-1 min-w-max">
            @foreach ($tabs as $key => $tab)
            <button type="button" role="tab"
                @click="setTab('{{ $key }}')"
                :aria-selected="activeTab === '{{ $key }}'"
                :class="activeTab === '{{ $key }}' ? 'border-primary text-primary' : 'border-transparent text-secondary hover:text-foreground'"
                class="relative -mb-px inline-flex items-center gap-2 px-4 py-3 border-b-2 text-xs sm:text-sm font-semibold transition-colors cursor-pointer focus:outline-none whitespace-nowrap">
                <i data-lucide="{{ $tab['icon'] }}" class="size-4 shrink-0"></i>
                <span>{{ $tab['label'] }}</span>
            </button>
            @endforeach
        </div>
    </div>

    {{-- 3. PANEL TAB --}}
    @foreach ($tabs as $key => $tab)
    <div x-show="activeTab === '{{ $key }}'" @if ($key !=='subject' ) x-cloak @endif role="tabpanel">
        <div data-tab-card class="bg-white rounded-2xl border border-border p-4 sm:p-5 md:p-6 shadow-2xs">

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-5">
                <div class="flex items-start sm:items-center gap-3 sm:gap-3.5 min-w-0">
                    <div class="size-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5 sm:mt-0">
                        <i data-lucide="{{ $tab['icon'] }}" class="size-5"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base sm:text-lg font-bold text-foreground leading-snug">{{ $cards[$key]['title'] }}</h2>
                        <p class="text-xs sm:text-sm text-secondary mt-0.5 leading-relaxed">{{ $cards[$key]['desc'] }}</p>
                    </div>
                </div>

                @if ($key === 'subject')
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
                            placeholder="{{ $cards[$key]['search'] }}"
                            autocomplete="off"
                            hx-get="{{ route('academics.subjects.index') }}"
                            hx-trigger="keyup changed delay:400ms, search"
                            hx-target="#subjects-container"
                            hx-select="#subjects-container"
                            hx-swap="outerHTML"
                            hx-include="#subjects-filter-form"
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

                    <button
                        type="button"
                        @click="filterModalOpen = true"
                        title="Filter mata pelajaran"
                        aria-label="Filter mata pelajaran"
                        class="relative flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-xl border border-border bg-white hover:bg-muted/60 hover:border-primary/40 transition-colors cursor-pointer focus:outline-none">
                        <i data-lucide="filter" class="size-4 text-secondary"></i>
                        <span x-show="isFilterActive" x-cloak class="absolute -top-1 -right-1 flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-primary border-2 border-white"></span>
                        </span>
                    </button>
                </div>
                @else
                <div class="relative w-full sm:w-60 xl:w-72 flex items-center shrink-0">
                    <i data-lucide="search" class="absolute left-3.5 size-4 text-secondary pointer-events-none"></i>
                    <input type="text" placeholder="{{ $cards[$key]['search'] }}" autocomplete="off"
                        oninput="const q=this.value.toLowerCase();this.closest('[data-tab-card]').querySelectorAll('[data-search]').forEach(el=>{el.style.display=el.dataset.search.toLowerCase().includes(q)?'':'none'})"
                        class="h-10 sm:h-11 w-full bg-white border border-border rounded-xl pl-10 pr-4 text-xs sm:text-sm text-foreground focus:outline-none focus:border-primary transition-all">
                </div>
                @endif
            </div>

            @if ($key === 'subject')
            @include('pages.academics.subjects.subject._table', compact('subjects'))
            @else
            @include('pages.academics.subjects.' . $key . '._table')
            @endif
        </div>
    </div>
    @endforeach

    @include('pages.academics.subjects.subject._filter-modal', [
    'filterStatus' => $filterStatus ?? '',
    'filterCategory' => $filterCategory ?? '',
    'statusOptions' => $statusOptions ?? [],
    ])

    <div id="modal-container"></div>
</div>
@endsection