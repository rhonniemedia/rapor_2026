<?php

namespace App\Support\Rapor;

use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Reader for data encrypted with the LEGACY report card key. Read-only, used during migration.
 * Writes to the target tables must use Crypt (shared key), never this class.
 */
class LegacyCrypt
{
    private Encrypter $encrypter;

    public function __construct()
    {
        $key = (string) config('rapor_migration.legacy_key');
        if ($key === '') {
            throw new RuntimeException('RAPOR_LEGACY_APP_KEY is not set in .env.');
        }

        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if ($raw === false) {
            throw new RuntimeException('RAPOR_LEGACY_APP_KEY is not valid base64.');
        }

        $this->encrypter = new Encrypter($raw, (string) config('app.cipher', 'AES-256-CBC'));
    }

    /** Without serialize: 'encrypted' cast / Crypt::encryptString. Throws on failure. */
    public function plain(string $value): string
    {
        $output = $this->encrypter->decrypt($value, false);

        // Sign of a wrong read: the value was actually serialized (s:N:"...";).
        if (preg_match('/^s:\d+:"/', $output) === 1) {
            throw new RuntimeException('Value looks serialized; read it with serialized().');
        }

        return $output;
    }

    /** With serialize: encrypt()/decrypt(). Throws on failure. */
    public function serialized(string $value): mixed
    {
        return $this->encrypter->decrypt($value, true);
    }
}
