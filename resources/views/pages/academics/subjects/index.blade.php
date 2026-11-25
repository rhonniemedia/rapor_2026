@extends('layouts.main.app')

@section('title', 'Mata Pelajaran')
@section('page_title', 'Mata Pelajaran')
@section('page_subtitle', 'Kelola mata pelajaran, kelompok, serta relasinya dengan kurikulum dan jurusan')

@php
$tabs = [
'subject' => ['label' => 'Mata Pelajaran', 'icon' => 'book-open', 'add' => 'Tambah Mata Pelajaran'],
'group' => ['label' => 'Kelompok Mapel', 'icon' => 'layers', 'add' => 'Tambah Kelompok'],
'curriculum' => ['label' => 'Relasi Kurikulum', 'icon' => 'swatch-book', 'add' => 'Tambah Relasi Kurikulum'],
'major' => ['label' => 'Relasi Jurusan', 'icon' => 'graduation-cap', 'add' => 'Tambah Relasi Jurusan'],
];

// Header kartu tiap tab (judul, deskripsi, placeholder pencarian)
$cards = [
'subject' => ['title' => 'Daftar Mata Pelajaran', 'desc' => 'Master mata pelajaran beserta kelompok dan jenisnya.', 'search' => 'Cari mata pelajaran...'],
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
        }
    }">

    {{-- 1. PAGE HEADER --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div class="min-w-0 max-w-2xl">
            <h1 class="text-2xl md:text-3xl font-bold text-foreground tracking-tight mb-1">Mata Pelajaran</h1>
            <p class="text-xs sm:text-sm text-secondary leading-relaxed">
                Kelola master mata pelajaran, pengelompokannya, serta relasi dengan kurikulum dan jurusan.
            </p>
        </div>

        <div class="grid grid-cols-2 sm:flex sm:items-center gap-2.5 shrink-0">
            <a href="{{ url()->current() }}"
                title="Segarkan halaman"
                onclick="document.getElementById('refresh-icon')?.classList.add('animate-spin');"
                class="inline-flex items-center justify-center gap-2 h-10 sm:h-11 px-4 rounded-full border border-border bg-white text-foreground font-semibold text-xs sm:text-sm hover:border-primary hover:text-primary transition-all cursor-pointer whitespace-nowrap">
                <i id="refresh-icon" data-lucide="refresh-cw" class="size-4 shrink-0"></i>
                <span>Segarkan</span>
            </a>

            {{-- Tombol tambah mengikuti tab aktif (dummy, belum ada route) --}}
            @foreach ($tabs as $key => $tab)
            <button type="button"
                x-show="activeTab === '{{ $key }}'"
                @if (! $loop->first) x-cloak @endif
                class="inline-flex items-center justify-center gap-2 h-10 sm:h-11 px-4 sm:px-5 rounded-full bg-primary text-white font-semibold text-xs sm:text-sm hover:bg-primary/90 shadow-sm shadow-primary/25 transition-all cursor-pointer whitespace-nowrap">
                <i data-lucide="plus" class="size-4 shrink-0"></i>
                <span>{{ $tab['add'] }}</span>
            </button>
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
    <div x-show="activeTab === '{{ $key }}'" @if (! $loop->first) x-cloak @endif role="tabpanel">
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

                {{-- Pencarian (dummy, filter sisi klien) --}}
                <div class="relative w-full sm:w-60 xl:w-72 flex items-center shrink-0">
                    <i data-lucide="search" class="absolute left-3.5 size-4 text-secondary pointer-events-none"></i>
                    <input type="text" placeholder="{{ $cards[$key]['search'] }}" autocomplete="off"
                        oninput="const q=this.value.toLowerCase();this.closest('[data-tab-card]').querySelectorAll('[data-search]').forEach(el=>{el.style.display=el.dataset.search.toLowerCase().includes(q)?'':'none'})"
                        class="h-10 sm:h-11 w-full bg-white border border-border rounded-xl pl-10 pr-4 text-xs sm:text-sm text-foreground focus:outline-none focus:border-primary transition-all">
                </div>
            </div>

            @include('pages.academics.subjects.' . $key . '._table')
        </div>
    </div>
    @endforeach

    {{-- Tempat modal tambah / edit / hapus dimuat lewat HTMX --}}
    <div id="modal-container"></div>
</div>
@endsection