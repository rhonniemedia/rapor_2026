<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>e-Rapor - @yield('title', 'Beranda')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body class="bg-muted font-sans text-foreground antialiased" x-data="{ sidebarOpen: false }">

    <x-layout.app-sidebar />

    {{-- Backdrop for the mobile sidebar --}}
    <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="sidebarOpen = false"></div>

    <div class="min-h-screen lg:pl-[280px]">
        <x-layout.app-topbar />

        <main class="p-5 lg:p-8">
            @yield('content')
        </main>
    </div>

    {{-- Global SweetAlert + target of htmx modals (loaded with hx-get into this container) --}}
    <x-ui.sweet-alert />
    <div id="modal-form-container"></div>

    <script>
        // Bridge: a controller answers an htmx request with the header HX-Trigger {"showAlert": {...}}.
        document.body.addEventListener('showAlert', (event) => {
            const detail = event.detail?.value ?? event.detail ?? {}
            window.ShowAlert?.({
                type: detail.icon || detail.type || 'info',
                title: detail.title || '',
                message: detail.text || detail.message || '',
            })
        })
    </script>
    @stack('scripts')
</body>

</html>
