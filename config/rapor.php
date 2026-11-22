<?php

return [
    // id of the report card app in core_apps (code 'rapor'); decides which role in user_app_roles applies here.
    'core_id' => env('CORE_APP_ID'),

    // roles of the shared role table that grant access to this app, strongest first.
    'roles' => ['superadmin', 'admin', 'user'],
];
