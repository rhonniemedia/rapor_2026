@extends('layouts.main.app')

@section('title', 'Beranda')
@section('page_title', 'Beranda')

@section('content')
    <div class="mx-auto flex max-w-6xl flex-col gap-6">

        @unless ($semester)
            <div class="rounded-2xl border border-border bg-white p-8 text-center text-secondary">
                Belum ada semester aktif pada data master. Hubungi administrator.
            </div>
        @else
            <div class="rounded-2xl border border-border bg-white p-6">
                <p class="text-sm text-secondary">Semester yang sedang dilihat</p>
                <p class="mt-1 text-xl font-bold">{{ $semester->label }}</p>
            </div>

            {{-- Admin overview --}}
            @if ($isAdmin && $stats)
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    @foreach ([
                        ['Rombel', $stats['class_groups'], 'layout-grid'],
                        ['Siswa', $stats['students'], 'users'],
                        ['Penugasan guru', $stats['assignments'], 'clipboard-list'],
                        ['Tanpa guru', $stats['assignments_without_teacher'], 'user-x'],
                        ['Nilai tercatat', $stats['scores'], 'graduation-cap'],
                    ] as [$label, $value, $icon])
                        <div class="rounded-2xl border border-border bg-white p-5">
                            <i data-lucide="{{ $icon }}" class="size-5 text-secondary"></i>
                            <p class="mt-3 text-2xl font-bold">{{ number_format($value, 0, ',', '.') }}</p>
                            <p class="text-xs text-secondary">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- A user without a staff record has no working menu --}}
            @if (! $isAdmin && ! $hasStaff)
                <div class="rounded-2xl border border-warning bg-warning-light p-6 text-sm text-warning-dark">
                    Akun Anda belum terhubung dengan data staf, sehingga belum ada menu yang dapat digunakan.
                    Hubungi administrator sekolah.
                </div>
            @endif

            {{-- Homeroom classes --}}
            @if ($homeroom->isNotEmpty())
                <section class="rounded-2xl border border-border bg-white p-6">
                    <h3 class="mb-4 font-bold">Kelas yang Anda walikan</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($homeroom as $classGroup)
                            <span class="rounded-xl bg-primary/10 px-4 py-2 text-sm font-semibold text-primary">{{ $classGroup->name }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Subjects taught --}}
            @if ($teaching->isNotEmpty())
                <section class="rounded-2xl border border-border bg-white p-6">
                    <h3 class="mb-4 font-bold">Mapel yang Anda ajar</h3>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($teaching as $className => $assignments)
                            <div class="rounded-xl bg-muted p-4">
                                <p class="font-semibold">{{ $className }}</p>
                                <ul class="mt-2 list-inside list-disc text-sm text-secondary">
                                    @foreach ($assignments as $assignment)
                                        <li>{{ $assignment->subject_name }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Extracurriculars supervised --}}
            @if ($supervised->isNotEmpty())
                <section class="rounded-2xl border border-border bg-white p-6">
                    <h3 class="mb-4 font-bold">Ekstrakurikuler yang Anda bina</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($supervised as $extracurricular)
                            <span class="rounded-xl bg-muted px-4 py-2 text-sm font-semibold">{{ $extracurricular->name }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (! $isAdmin && $hasStaff && $teaching->isEmpty() && $homeroom->isEmpty() && $supervised->isEmpty())
                <div class="rounded-2xl border border-border bg-white p-8 text-center text-sm text-secondary">
                    Belum ada penugasan mengajar, perwalian kelas, atau pembinaan ekskul untuk Anda pada semester ini.
                </div>
            @endif
        @endunless
    </div>
@endsection
