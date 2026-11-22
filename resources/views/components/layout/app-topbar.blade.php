<header class="sticky top-0 z-30 flex h-[90px] items-center justify-between gap-4 border-b border-border bg-white px-5 lg:px-8">

    <div class="flex min-w-0 items-center gap-3">
        <button @click="sidebarOpen = true"
            class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl ring-1 ring-border transition-all hover:ring-primary lg:hidden">
            <i data-lucide="menu" class="size-5 text-secondary"></i>
        </button>
        <h2 class="truncate text-lg font-bold text-foreground">@yield('page_title', 'Beranda')</h2>
    </div>

    {{-- Semester being viewed (a per-session preference; the active semester of the master data is not changed) --}}
    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
        <button type="button" @click="open = !open" aria-label="Pilih semester"
            class="flex cursor-pointer items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold ring-1 ring-border transition-all hover:ring-primary">
            <i data-lucide="calendar-days" class="size-4 text-secondary"></i>
            <span>{{ $currentAcademicPeriod?->label ?? 'Belum ada semester' }}</span>
            @if ($isAcademicPeriodOverridden ?? false)
                <span class="size-2 rounded-full bg-warning" title="Sedang melihat semester lain dari semester aktif"></span>
            @endif
            <i data-lucide="chevron-down" class="size-4 text-secondary"></i>
        </button>

        <div x-show="open" x-cloak x-transition
            class="absolute right-0 mt-2 w-72 rounded-2xl border border-border bg-white p-2 shadow-xl">
            @if ($isAcademicPeriodOverridden ?? false)
                <div class="mb-2 rounded-xl bg-warning-light px-3 py-2 text-xs text-warning-dark">
                    Anda sedang melihat semester lain, bukan semester aktif.
                </div>
            @endif

            @forelse ($academicPeriodOptions ?? [] as $option)
                <form method="POST" action="{{ route('academic-period.update') }}" class="m-0 p-0">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $option->id }}">
                    <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                    <button type="submit"
                        class="flex w-full cursor-pointer items-center justify-between rounded-xl px-3 py-2 text-left text-sm {{ ($currentAcademicPeriod?->id === $option->id) ? 'bg-primary/10 font-bold text-primary' : 'text-secondary hover:bg-muted hover:text-primary' }}">
                        <span>{{ $option->label }}</span>
                        @if ($option->status === 'active')
                            <span class="rounded-full bg-success-light px-2 py-0.5 text-[10px] font-semibold text-success-dark">Aktif</span>
                        @endif
                    </button>
                </form>
            @empty
                <p class="px-2 py-2 text-sm text-secondary">Belum ada data semester.</p>
            @endforelse

            @if ($isAcademicPeriodOverridden ?? false)
                <form method="POST" action="{{ route('academic-period.reset') }}" class="m-0 mt-2 border-t border-border p-0 pt-2">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                    <button type="submit" class="w-full cursor-pointer rounded-xl px-3 py-2 text-left text-sm font-semibold text-primary hover:bg-primary/10">
                        Kembali ke semester aktif
                    </button>
                </form>
            @endif
        </div>
    </div>
</header>
