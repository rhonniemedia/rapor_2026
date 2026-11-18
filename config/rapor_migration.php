<?php

return [
    // OLD APP_KEY of the legacy report card app (read-only, used only during migration).
    // Set it in .env, never commit it or paste it in chat:
    // RAPOR_LEGACY_APP_KEY=base64:....
    'legacy_key' => env('RAPOR_LEGACY_APP_KEY'),

    // Table prefix of the staging import of the legacy report card database.
    'staging_prefix' => env('RAPOR_STAGING_PREFIX', 'old_'),
];
