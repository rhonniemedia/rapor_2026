<?php
// Run from the project root:  php artisan tinker key-check.php
// Prints only 8-character fingerprints and YES/NO. No key material is shown.

use App\Support\Rapor\LegacyCrypt;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$fp = fn($value) => substr(hash('sha256', (string) $value), 0, 8);

foreach (['app.key' => config('app.key'), 'legacy' => config('rapor_migration.legacy_key')] as $name => $key) {
    $key = (string) $key;
    $noPrefix = str_starts_with($key, 'base64:') ? substr($key, 7) : $key;
    $raw = base64_decode($noPrefix, true);
    echo str_pad($name, 8) . ' full=' . $fp($key) . ' no_prefix=' . $fp($noPrefix) . ' raw=' . $fp($raw === false ? '' : $raw) . "\n";
}

// Functional test 1: can THIS project's APP_KEY read PINTAR's encrypted data?
$vault = DB::table('acd_students_vault')->whereNotNull('nisn_encrypted')->value('nisn_encrypted');
try {
    Crypt::decryptString((string) $vault);
    echo "app.key decrypts acd_students_vault.nisn_encrypted: YES\n";
} catch (\Throwable $e) {
    echo "app.key decrypts acd_students_vault.nisn_encrypted: NO\n";
}

// Functional test 2: can the LEGACY key read the staged report card data?
if (Schema::hasTable('old_pelajars')) {
    $legacy = DB::table('old_pelajars')->whereNotNull('nisn')->value('nisn');
    try {
        (new LegacyCrypt())->plain((string) $legacy);
        echo "legacy key decrypts old_pelajars.nisn: YES\n";
    } catch (\Throwable $e) {
        echo "legacy key decrypts old_pelajars.nisn: NO\n";
    }
} else {
    echo "old_pelajars does not exist yet (run sql/01-import-staging-old.sql first).\n";
}
