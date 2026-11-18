<?php

namespace App\Traits;

use App\Enums\Student\Religion;

/**
 * STANDAR HASH PINTAR (jangan "diseragamkan" dengan SIMKA):
 *
 * - Siswa & wali (acd_students_vault, acd_guardians_vault): HMAC-SHA256
 *   dengan kunci app.key, lewat trait ini. SEMUA kolom *_hash di dua tabel
 *   itu wajib ditulis lewat blindIndexHash().
 * - Staf (SIMKA, DataVault): SHA-256 tanpa kunci. Sengaja berbeda,
 *   JANGAN diubah.
 *
 * Konsekuensi: jika APP_KEY dirotasi, seluruh *_hash siswa & wali harus
 * dihitung ulang (php artisan vault:recompute-hashes).
 *
 * Aturan nilai yang di-hash:
 * - umum  : mb_strtolower(trim(nilai)) (dilakukan di blindIndexHash)
 * - dob   : selalu format Y-m-d (lihat normalizeDob)
 * - agama : selalu nilai enum Religion (lihat canonicalReligion);
 *           hash dihitung dari nilai enum yang tersimpan, bukan input mentah
 */
trait HasBlindIndex
{
    protected function blindIndexHash(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return hash_hmac('sha256', $normalized, config('app.key'));
    }

    /**
     * Normalisasi tanggal lahir ke Y-m-d. Hanya menerima Y-m-d, atau
     * Y-m-d dengan jam 00:00:00 (mis. "2008-05-03 00:00:00"). Format lain
     * (ISO dengan jam bukan nol, d/m/Y, dst) TIDAK ditebak dan
     * mengembalikan null, karena bisa bergeser sehari atau tertukar
     * dd/mm dengan mm/dd.
     */
    protected function normalizeDob(?string $value): ?string
    {
        $value = trim((string) $value);

        $pattern = '/^(\d{4})-(\d{2})-(\d{2})(?:[ T]00:00:00(?:\.0+)?(?:Z|[+-]00:?00)?)?$/';

        if (! preg_match($pattern, $value, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            ? "{$m[1]}-{$m[2]}-{$m[3]}"
            : null;
    }

    /**
     * Nilai enum Religion untuk sebuah input, atau null bila di luar enum.
     * Toleran huruf besar/kecil dan spasi saja.
     */
    protected function canonicalReligion(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Religion::tryFrom(mb_strtolower(trim($value)))?->value;
    }
}
