@php
$school = \App\Models\CoreSchool::current();
$user = auth()->user();
$isHome = request()->routeIs('home');
$roleLabel = ['superadmin' => 'Super Admin', 'admin' => 'Admin', 'user' => 'Guru'][$raporRole ?? ''] ?? '';
@endphp

<aside
    class="fixed inset-y-0 left-0 z-50 flex h-screen w-[280px] shrink-0 flex-col overflow-hidden border-r border-border bg-white transition-transform duration-300 ease-in-out"
    :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0 lg:shadow-none'">

    {{-- Logo --}}
    <div class="flex h-[90px] items-center justify-between gap-3 border-b border-border px-5">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-purple-700 shadow-sm">
                <i data-lucide="book-open-check" class="size-5 text-white"></i>
            </div>
            <div class="min-w-0">
                <h1 class="text-base font-bold leading-tight text-foreground">E-Rapor</h1>
                <p class="truncate text-xs text-secondary">{{ $school->name ?? 'Aplikasi Rapor Digital' }}</p>
            </div>
        </div>

        <button @click="sidebarOpen = false"
            class="flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-white p-[10px] ring-1 ring-border transition-all duration-300 hover:ring-primary lg:hidden">
            <i data-lucide="x" class="size-6 text-secondary"></i>
        </button>
    </div>

    {{-- Navigasi utama: pola grup collapsible, hover, dan status aktif mengikuti sidebar referensi. --}}
    <div class="flex flex-1 flex-col gap-2 overflow-y-auto p-5 pb-28 scrollbar-hide">

        {{-- Dashboard --}}
        <div x-data="{ open: true }" class="flex flex-col gap-1">
            <button @click="open = !open"
                class="mb-2 flex w-full cursor-pointer items-center justify-between px-3 text-sm font-bold text-foreground focus:outline-none">
                <span>Dashboard</span>
                <i data-lucide="chevron-down" class="size-4 text-secondary transition-transform duration-300"
                    :class="open ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="open" x-collapse class="flex flex-col gap-1">
                <a href="{{ route('home') }}" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ $isHome ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="layout-dashboard"
                                class="size-5 transition-all duration-300 {{ $isHome ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ $isHome ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Beranda</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div x-data="{ open: {{ request()->routeIs('academic.*') ? 'true' : 'false' }} }" class="mt-4 flex flex-col gap-1">
            <button @click="open = !open"
                class="mb-2 flex w-full cursor-pointer items-center justify-between px-3 text-sm font-bold text-foreground focus:outline-none">
                <span>Master Akademik</span>
                <i data-lucide="chevron-down" class="size-4 text-secondary transition-transform duration-300"
                    :class="open ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="open" x-collapse class="flex flex-col gap-1">
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('academic.relations.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="git-branch"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('academic.relations.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('academic.relations.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Relasi Akademik</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('academic.subjects.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="book-open"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('academic.subjects.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('academic.subjects.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Mata Pelajaran</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('academic.achievements.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="target"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('academic.achievements.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('academic.achievements.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Capaian Pembelajaran</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('academic.extracurriculars.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="trophy"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('academic.extracurriculars.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('academic.extracurriculars.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Ekstrakurikuler</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div x-data="{ open: {{ request()->routeIs('rombel.*') ? 'true' : 'false' }} }" class="mt-4 flex flex-col gap-1">
            <button @click="open = !open"
                class="mb-2 flex w-full cursor-pointer items-center justify-between px-3 text-sm font-bold text-foreground focus:outline-none">
                <span>Rombongan Belajar</span>
                <i data-lucide="chevron-down" class="size-4 text-secondary transition-transform duration-300"
                    :class="open ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="open" x-collapse class="flex flex-col gap-1">
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rombel.index') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="users"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rombel.index') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rombel.index') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Daftar Rombel</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rombel.teachers.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="user-round-check"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rombel.teachers.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rombel.teachers.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Daftar Pendidik</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div x-data="{ open: {{ request()->routeIs('rapor.input.*') ? 'true' : 'false' }} }" class="mt-4 flex flex-col gap-1">
            <button @click="open = !open"
                class="mb-2 flex w-full cursor-pointer items-center justify-between px-3 text-sm font-bold text-foreground focus:outline-none">
                <span>Pengisian Rapor</span>
                <i data-lucide="chevron-down" class="size-4 text-secondary transition-transform duration-300"
                    :class="open ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="open" x-collapse class="flex flex-col gap-1">
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rapor.input.grades.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="clipboard-list"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rapor.input.grades.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rapor.input.grades.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Nilai Akhir</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rapor.input.cocurricular.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="notebook-pen"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rapor.input.cocurricular.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rapor.input.cocurricular.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Kokurikuler</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rapor.input.attendance.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="calendar-check"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rapor.input.attendance.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rapor.input.attendance.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Kehadiran</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rapor.input.homeroom-notes.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="message-square-text"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rapor.input.homeroom-notes.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rapor.input.homeroom-notes.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Catatan Wali Kelas</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rapor.input.extracurricular.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="medal"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rapor.input.extracurricular.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rapor.input.extracurricular.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Ekstrakurikuler</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div x-data="{ open: {{ request()->routeIs('rapor.finalization.*') ? 'true' : 'false' }} }" class="mt-4 flex flex-col gap-1">
            <button @click="open = !open"
                class="mb-2 flex w-full cursor-pointer items-center justify-between px-3 text-sm font-bold text-foreground focus:outline-none">
                <span>Finalisasi Rapor</span>
                <i data-lucide="chevron-down" class="size-4 text-secondary transition-transform duration-300"
                    :class="open ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="open" x-collapse class="flex flex-col gap-1">
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rapor.finalization.configuration.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="settings-2"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rapor.finalization.configuration.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rapor.finalization.configuration.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Konfigurasi Rapor</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rapor.finalization.grades.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="list-checks"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rapor.finalization.grades.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rapor.finalization.grades.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Daftar Nilai</span>
                        </div>
                    </div>
                </a>
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('rapor.finalization.print.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="printer"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('rapor.finalization.print.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('rapor.finalization.print.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">Cetak Rapor</span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        {{-- Pengguna --}}
        <div x-data="{ open: {{ request()->routeIs('users.*', 'profile.*') ? 'true' : 'false' }} }" class="mt-4 flex flex-col gap-1">
            <button @click="open = !open"
                class="mb-2 flex w-full cursor-pointer items-center justify-between px-3 text-sm font-bold text-foreground focus:outline-none">
                <span>Pengguna</span>
                <i data-lucide="chevron-down" class="size-4 text-secondary transition-transform duration-300"
                    :class="open ? 'rotate-180' : ''"></i>
            </button>

            <div x-show="open" x-collapse class="flex flex-col gap-1">
                @if (($raporRole ?? '') === 'superadmin')
                {{-- Daftar Pengguna (route dimatikan sementara ke #) --}}
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('users.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="layout-list"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('users.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('users.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">
                                Daftar Pengguna
                            </span>
                        </div>
                    </div>
                </a>
                @endif

                {{-- Profil Saya (route dimatikan sementara ke #) --}}
                <a href="#" class="group cursor-pointer">
                    <div class="flex items-center justify-between rounded-xl p-3 transition-all duration-300 {{ request()->routeIs('profile.*') ? 'bg-primary/10 hover:bg-primary/20' : 'hover:bg-muted' }}">
                        <div class="flex items-center gap-3">
                            <i data-lucide="user-round-pen"
                                class="size-5 transition-all duration-300 {{ request()->routeIs('profile.*') ? 'text-primary' : 'text-secondary group-hover:text-foreground' }}"></i>
                            <span class="text-sm font-medium transition-all duration-300 {{ request()->routeIs('profile.*') ? 'font-semibold text-primary' : 'text-secondary group-hover:text-foreground' }}">
                                Profil Saya
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        </div>

    </div>

    <div class="absolute inset-x-0 bottom-0 border-t border-border bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground" title="{{ $user->staff?->name_with_title ?? $user->username }}">
                    {{ $user->staff?->name_with_title ?? $user->username }}
                </p>
                <p class="mt-0.5 truncate text-xs text-secondary">{{ $roleLabel }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="m-0 p-0">
                @csrf
                <button type="submit" title="Keluar"
                    class="flex size-10 cursor-pointer items-center justify-center rounded-xl ring-1 ring-border transition-all hover:ring-primary">
                    <i data-lucide="log-out" class="size-5 text-secondary"></i>
                </button>
            </form>
        </div>
    </div>
</aside>