import Alpine from 'alpinejs'
import collapse from '@alpinejs/collapse'
import 'htmx.org'

// Alpine
window.Alpine = Alpine
Alpine.plugin(collapse)
Alpine.start()

// Lucide
import { createIcons, icons } from 'lucide'

// Simpan referensi agar bisa dipanggil ulang dari mana saja (misal dari x-init Alpine)
window.renderIcons = () => createIcons({ icons })

// 1. Jalankan sekali saat DOM siap (initial page load)
document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons })
})

// 2. Jalankan ulang setiap kali htmx selesai menukar konten
//    (misal: search/filter tabel, buka modal via hx-get, dsb)
document.addEventListener('htmx:afterSwap', () => {
    createIcons({ icons })
})

// 3. Jalankan ulang setelah htmx selesai "settle"
//    (menjaga-jaga untuk kasus swap ke innerHTML seperti #modal-container)
document.addEventListener('htmx:afterSettle', () => {
    createIcons({ icons })
})

// 4. Jalankan ulang setiap ada perubahan DOM oob-swap (out of band)
//    berguna kalau ada bagian lain di luar target utama yang ikut berubah
document.addEventListener('htmx:oobAfterSwap', () => {
    createIcons({ icons })
})

// ===== e-Rapor additions =====

// Send the CSRF token with every htmx request (forms already include @csrf, plain hx-post buttons do not).
document.addEventListener('htmx:configRequest', (event) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content
    if (token) event.detail.headers['X-CSRF-TOKEN'] = token
})

// Friendly handling of the common htmx failures.
document.addEventListener('htmx:responseError', (event) => {
    const status = event.detail.xhr.status
    const messages = {
        401: 'Sesi Anda berakhir. Silakan masuk kembali.',
        403: 'Anda tidak memiliki akses untuk tindakan ini.',
        419: 'Sesi halaman kedaluwarsa. Muat ulang halaman lalu coba lagi.',
        429: 'Terlalu banyak permintaan. Coba lagi sebentar lagi.',
        500: 'Terjadi kesalahan pada server.',
    }
    if (status === 401 || status === 419) {
        window.ShowAlert?.({ type: 'warning', title: 'Sesi berakhir', message: messages[status] })
        return
    }
    window.ShowAlert?.({ type: 'error', title: 'Gagal', message: messages[status] || 'Permintaan gagal diproses.' })
})
